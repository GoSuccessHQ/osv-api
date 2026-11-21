# OSV.dev API for PHP

A modern, strongly-typed, dependency-free PHP client for the
[OSV.dev](https://osv.dev) vulnerability API: which known vulnerabilities affect
an installed package version, and the full records behind them.

## Features

- **Package queries**: `query()` returns the full records for one package
  version, `queryBatch()` the ids for many versions at once. Both follow
  `next_page_token` until the last page.
- **Records**: `vulnerability()` reads one record in the
  [OSV schema](https://ossf.github.io/osv-schema/): aliases, upstream ids,
  severity, affected packages with ranges and fixed versions, references.
- **Ubuntu and CVSS**: `ubuntuPriority()` reads Ubuntu's priority, and
  `cvssBaseScore()` computes the CVSS v3.x base score from the vector, which is
  all OSV.dev ships.
- **No dependencies**: only `ext-curl` and `ext-json`; bring your own transport
  if you like.

## Requirements

- PHP 8.4 or newer
- `ext-curl`, `ext-json`

## Installation

```bash
composer require gosuccess/osv-api
```

## Quick start

OSV.dev needs no API key.

```php
use GoSuccess\Osv\Model\Ecosystem;
use GoSuccess\Osv\Model\PackageQuery;
use GoSuccess\Osv\Osv;

$osv = new Osv();
$ubuntu = Ecosystem::ubuntu('24.04'); // "Ubuntu:24.04:LTS"

$results = $osv->queryBatch([
    new PackageQuery($ubuntu, 'openssh', '1:9.6p1-3ubuntu13.20'),
    new PackageQuery(Ecosystem::GO, 'stdlib', '1.26.4'),
]);

foreach ($results as $result) {
    foreach ($result->vulnerabilities as $reference) {
        $vulnerability = $osv->vulnerability($reference->id);

        echo $vulnerability->id, ' ',
            $vulnerability->ubuntuPriority()?->value ?? '-', ' ',
            implode(',', $vulnerability->fixedVersions($result->query->ecosystem, $result->query->name)) ?: 'no fix yet',
            "\n";
    }
}
```

See [examples/server-packages.php](examples/server-packages.php) for a runnable
version.

## Queries

- **Ecosystems.** `Ecosystem::ubuntu('24.04')`, `Ecosystem::GO` and
  `Ecosystem::PACKAGIST` were measured; any other ecosystem name OSV.dev knows
  works too. An unknown ecosystem is refused with `400`
  (`BadRequestException`, error code `3`, measured 2026-11-21).
- **Ubuntu packages are source packages.** Query `openssh`, not
  `openssh-server`; use the source package and its version as `dpkg-query`
  reports them (`${source:Package}`, `${source:Version}`). The version includes
  the epoch (`1:9.6p1-3ubuntu13.20`).
- **Go**: the standard library is the package `stdlib` with the Go version
  (`1.26.4`, without the `go` prefix); a binary such as restic needs both its
  module and the Go version it was built with.
- **`queryBatch()`** sends at most `batchSize` queries per request (default and
  maximum 1000, the limit of the API) and returns one `BatchResult` per query,
  in the order of the queries. Each result lists only `id` and `modified`; fetch
  a record with `vulnerability()` when it is new or changed. A query with more
  than 1000 results is followed up with its page token; the other queries are
  not sent again.
- **`query()`** returns full records, which is convenient for a single version
  but much larger.
- **Size and duration.** OSV.dev reports every vulnerability that affects a
  version, including ones without a fix. On 2026-11-21 the Ubuntu 24.04 kernel
  `linux 6.8.0-31.31` had 10,348 entries and `6.8.0-88.89` 7,517; the two
  together took 50 s with all pages. Size your timeout and budget for the
  kernel accordingly.
- **Pages are bounded**: after `Osv::MAX_PAGES` (100) pages for one query the
  client gives up with a `SerializationException` instead of looping.

## Records

`Vulnerability` holds `id`, `summary`, `details`, `aliases`, `upstream`,
`related`, `published`, `modified`, `withdrawn`, `severity`, `affected` and
`references`. Helpers:

- `cveIds()`: the CVE ids from `id`, `aliases` and `upstream`. Ubuntu records
  carry the CVE in `upstream`, not in `aliases` (measured 2026-11-21).
- `ubuntuPriority()`: `UbuntuPriority` (`untriaged` < `negligible` < `low` <
  `medium` < `high` < `critical`) with `isAtLeast()` for thresholds; `null` when
  the record has no Ubuntu rating. Ubuntu security notices (`USN-…`) also show
  up as results and carry no priority.
- `cvssBaseScore()`: the highest CVSS v3.0/v3.1 base score of the record,
  computed with the formula of the v3.1 specification; `null` without a v3
  vector. CVSS v2 and v4 vectors are kept in `severity` but not computed. Many
  records have no CVSS vector at all (Go records, many Ubuntu records).
- `affectedPackage($ecosystem, $name)` and `fixedVersions($ecosystem, $name)`:
  a record usually lists the same package for several releases; an empty list of
  fixed versions means that no fix is known yet.
- `isWithdrawn()`.

Field problems never throw: a missing field or a value of the wrong type becomes
`null` or empty. Free-form data (`ecosystem_specific`, `database_specific`) is
kept as an array, e.g. Ubuntu's `priority_reason`.

## Error handling

Every exception implements `GoSuccess\Osv\Exception\OsvException`. Problems with
the **whole response** throw; problems with a **field** become `null`.

| Exception | When |
|---|---|
| `BadRequestException` | `400`, e.g. an unknown ecosystem |
| `NotFoundException` | `404`, e.g. `vulnerability()` with an unknown id |
| `RateLimitException` | `429`, never retried; `retryAfter` holds the announced seconds |
| `ServerException` | `5xx` after the retries |
| other `ApiException` subclasses | other error statuses; `errorCode` holds OSV.dev's numeric code |
| `SerializationException` | the body is not the expected JSON, the number of batch results does not match, a record has no id, or the pages do not end |
| `TransportException` | network failure after the retries |

`maxRetries` defaults to `0`. With a higher value, network failures and `5xx`
are retried with exponential backoff (`retryBaseDelay`, `maxRetryDelay`); the
query endpoints are POST requests but only read, so they are retried too.

## Configuration and custom transport

`ClientOptions` sets `timeout` (60 s), `connectTimeout` (10 s), `maxRetries`
(0), `retryBaseDelay`, `maxRetryDelay`, `userAgent` and `batchSize` (1000). The
timeouts travel with every request, so they also apply through a custom
transport.

A custom transport implements `GoSuccess\Osv\Http\HttpClient`:

```php
use GoSuccess\Osv\ClientOptions;
use GoSuccess\Osv\Http\HttpClient;
use GoSuccess\Osv\Http\Request;
use GoSuccess\Osv\Http\Response;
use GoSuccess\Osv\Osv;

final class MyTransport implements HttpClient
{
    public function send(Request $request): Response
    {
        // Send it with your HTTP stack and return status, body and lower-cased headers.
    }
}

$osv = new Osv(new ClientOptions(maxRetries: 2), new MyTransport());
```

The contract: throw `TransportException` only for network failures (never for
HTTP error statuses, which are returned as a `Response`), return header names in
lower case, and never follow redirects. Honor `$request->timeout` and
`$request->connectTimeout` when they are set.

## Not covered

- `POST /v1/query` by commit hash and `determineversion`: not needed for
  installed packages.
- Downloading whole ecosystems (the GCS export): far larger than querying the
  installed versions.

## Development

```bash
composer check            # php-cs-fixer, PHPStan (max), unit tests
OSV_LIVE=1 composer test:integration   # four read-only requests to api.osv.dev
```

The unit tests run against responses of api.osv.dev recorded on 2026-11-21
(`tests/Fixtures`).

## License

MIT, see [LICENSE](LICENSE).
