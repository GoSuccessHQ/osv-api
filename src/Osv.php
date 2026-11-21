<?php

declare(strict_types=1);

namespace GoSuccess\Osv;

use GoSuccess\Osv\Exception\ApiException;
use GoSuccess\Osv\Exception\SerializationException;
use GoSuccess\Osv\Exception\TransportException;
use GoSuccess\Osv\Http\CurlHttpClient;
use GoSuccess\Osv\Http\HttpClient;
use GoSuccess\Osv\Internal\Connection;
use GoSuccess\Osv\Internal\Json;
use GoSuccess\Osv\Internal\Read;
use GoSuccess\Osv\Model\BatchResult;
use GoSuccess\Osv\Model\PackageQuery;
use GoSuccess\Osv\Model\Vulnerability;
use GoSuccess\Osv\Model\VulnerabilityReference;
use InvalidArgumentException;

/**
 * Client for the OSV.dev API (https://google.github.io/osv.dev/api/); entry
 * point of the package. OSV.dev needs no API key.
 *
 * Every method only reads. Errors are thrown as {@see Exception\OsvException}.
 */
final class Osv
{
    public const string DEFAULT_BASE_URI = 'https://api.osv.dev';

    /**
     * Upper bound of pages followed for one query. 1000 ids per page, so this
     * allows 100,000 records for one package version; the Linux kernel
     * `6.8.0-31.31` of Ubuntu 24.04 has 10,348 (measured 2026-11-21). The bound
     * only protects against an API that keeps returning page tokens.
     */
    public const int MAX_PAGES = 100;

    private readonly Connection $connection;

    private readonly string $baseUri;

    /**
     * @param HttpClient|null $httpClient Defaults to {@see CurlHttpClient}.
     */
    public function __construct(
        private readonly ClientOptions $options = new ClientOptions(),
        ?HttpClient $httpClient = null,
        string $baseUri = self::DEFAULT_BASE_URI,
    ) {
        $this->connection = new Connection($options, $httpClient ?? new CurlHttpClient($options->timeout, $options->connectTimeout));
        $this->baseUri = rtrim($baseUri, '/');
    }

    /**
     * The full records of every vulnerability that affects one package version
     * (`POST /v1/query`), across all pages.
     *
     * @return list<Vulnerability>
     *
     * @throws ApiException           e.g. {@see Exception\BadRequestException} for an unknown ecosystem
     * @throws SerializationException for a response that is not the expected JSON
     * @throws TransportException
     */
    public function query(PackageQuery $query): array
    {
        $vulnerabilities = [];
        $pageToken = null;

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $response = $this->connection->postJson($this->uri('/v1/query'), $query->toArray($pageToken));
            $data = Json::object($response, 'query');

            foreach (Read::array($data['vulns'] ?? null) as $record) {
                $vulnerabilities[] = Vulnerability::fromArray($record);
            }

            $pageToken = Read::string($data['next_page_token'] ?? null);

            if ($pageToken === null) {
                return $vulnerabilities;
            }
        }

        throw new SerializationException('query: more than ' . self::MAX_PAGES . ' pages.');
    }

    /**
     * The ids of the vulnerabilities that affect each of many package versions
     * (`POST /v1/querybatch`), across all pages. The queries are sent in chunks
     * of {@see ClientOptions::$batchSize}; a query with more results than one
     * page is followed up with its page token.
     *
     * @param list<PackageQuery> $queries
     *
     * @return list<BatchResult> one result per query, in the order of the queries
     *
     * @throws ApiException
     * @throws SerializationException
     * @throws TransportException
     */
    public function queryBatch(array $queries): array
    {
        if (!array_is_list($queries)) {
            throw new InvalidArgumentException('queries must be a list.');
        }

        /** @var array<int, list<VulnerabilityReference>> $found */
        $found = [];
        /** @var array<int, string|null> $pending index of the query => page token, null for the first page */
        $pending = array_fill_keys(array_keys($queries), null);
        $pages = array_fill_keys(array_keys($queries), 0);

        while ($pending !== []) {
            foreach (array_chunk($pending, $this->options->batchSize, true) as $chunk) {
                $indexes = array_keys($chunk);
                $payload = [];

                foreach ($chunk as $index => $pageToken) {
                    $payload[] = $queries[$index]->toArray($pageToken);
                }

                $results = Read::array(Json::object(
                    $this->connection->postJson($this->uri('/v1/querybatch'), ['queries' => $payload]),
                    'querybatch',
                )['results'] ?? null);

                // An empty batch answers with `{}`; anything but one result per query is a broken response.
                if (\count($results) !== \count($indexes) || !array_is_list($results)) {
                    throw new SerializationException(\sprintf('querybatch: %d results for %d queries.', \count($results), \count($indexes)));
                }

                foreach ($indexes as $position => $index) {
                    $result = Read::object($results[$position]);

                    foreach (Read::array($result['vulns'] ?? null) as $reference) {
                        $found[$index][] = self::reference($reference);
                    }

                    $pageToken = Read::string($result['next_page_token'] ?? null);

                    if ($pageToken === null) {
                        unset($pending[$index]);

                        continue;
                    }

                    if (++$pages[$index] >= self::MAX_PAGES) {
                        throw new SerializationException('querybatch: more than ' . self::MAX_PAGES . ' pages for one query.');
                    }

                    $pending[$index] = $pageToken;
                }
            }
        }

        $batch = [];

        foreach ($queries as $index => $query) {
            $batch[] = new BatchResult($query, $found[$index] ?? []);
        }

        return $batch;
    }

    /**
     * One full record (`GET /v1/vulns/{id}`).
     *
     * @throws Exception\NotFoundException for an unknown id
     * @throws ApiException
     * @throws SerializationException
     * @throws TransportException
     */
    public function vulnerability(string $id): Vulnerability
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/D', $id) !== 1) {
            throw new InvalidArgumentException('id must be an OSV id such as "UBUNTU-CVE-2026-119618".');
        }

        $vulnerability = Vulnerability::fromArray(Json::object($this->connection->get($this->uri('/v1/vulns/' . rawurlencode($id))), 'vulns'));

        if ($vulnerability->id !== $id) {
            throw new SerializationException("vulns: asked for {$id}, got {$vulnerability->id}.");
        }

        return $vulnerability;
    }

    /**
     * @param non-empty-string $path
     *
     * @return non-empty-string
     */
    private function uri(string $path): string
    {
        return $this->baseUri . $path;
    }

    /**
     * @throws SerializationException without an id
     */
    private static function reference(mixed $data): VulnerabilityReference
    {
        $data = Read::object($data);
        $id = Read::string($data['id'] ?? null);

        if ($id === null) {
            throw new SerializationException('querybatch: vulnerability without an id.');
        }

        return new VulnerabilityReference($id, Read::timestamp($data['modified'] ?? null));
    }
}
