<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Tests\Unit;

use GoSuccess\Osv\ClientOptions;
use GoSuccess\Osv\Exception\BadRequestException;
use GoSuccess\Osv\Exception\NotFoundException;
use GoSuccess\Osv\Exception\SerializationException;
use GoSuccess\Osv\Http\Method;
use GoSuccess\Osv\Http\Response;
use GoSuccess\Osv\Model\BatchResult;
use GoSuccess\Osv\Model\Ecosystem;
use GoSuccess\Osv\Model\PackageQuery;
use GoSuccess\Osv\Model\VulnerabilityReference;
use GoSuccess\Osv\Osv;
use GoSuccess\Osv\Tests\Support\MockHttpClient;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Osv::class)]
#[CoversClass(BatchResult::class)]
#[CoversClass(VulnerabilityReference::class)]
final class OsvTest extends TestCase
{
    public function testQueryBatchReturnsOneResultPerQueryInOrder(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('querybatch.json')));
        $queries = [
            new PackageQuery(Ecosystem::ubuntu('24.04'), 'openssh', '1:9.6p1-3ubuntu13.20'),
            new PackageQuery(Ecosystem::GO, 'stdlib', '1.26.4'),
            new PackageQuery(Ecosystem::GO, 'github.com/restic/restic', '0.19.1'),
        ];

        $results = new Osv(httpClient: $http)->queryBatch($queries);

        self::assertCount(3, $results);
        self::assertSame($queries[0], $results[0]->query);
        self::assertSame(['UBUNTU-CVE-2026-110928'], array_map(static fn(VulnerabilityReference $reference): string => $reference->id, $results[0]->vulnerabilities));
        self::assertSame('2026-10-30T03:06:21.160262+00:00', $results[0]->vulnerabilities[0]->modified?->format('Y-m-d\TH:i:s.uP'));
        self::assertCount(25, $results[1]->vulnerabilities);
        self::assertSame([], $results[2]->vulnerabilities);

        $request = $http->requests[0];
        self::assertSame(Method::Post, $request->method);
        self::assertSame('https://api.osv.dev/v1/querybatch', $request->uri);
        self::assertSame([
            'package' => ['name' => 'openssh', 'ecosystem' => 'Ubuntu:24.04:LTS'],
            'version' => '1:9.6p1-3ubuntu13.20',
        ], self::sentQueries($http, 0)[0] ?? null);
    }

    public function testQueryBatchSplitsIntoChunks(): void
    {
        $http = new MockHttpClient(
            new Response(200, '{"results":[{"vulns":[{"id":"A-1"}]},{}]}'),
            new Response(200, '{"results":[{"vulns":[{"id":"C-1"}]}]}'),
        );
        $queries = [self::query('a'), self::query('b'), self::query('c')];

        $results = new Osv(new ClientOptions(batchSize: 2), $http)->queryBatch($queries);

        self::assertSame(2, $http->callCount());
        self::assertCount(2, self::sentQueries($http, 0));
        self::assertCount(1, self::sentQueries($http, 1));
        self::assertSame('A-1', self::ids($results[0])[0] ?? null);
        self::assertSame([], $results[1]->vulnerabilities);
        self::assertSame('C-1', self::ids($results[2])[0] ?? null);
    }

    public function testQueryBatchFollowsPageTokensOnlyForTheQueriesThatHaveOne(): void
    {
        $http = new MockHttpClient(
            new Response(200, '{"results":[{"vulns":[{"id":"A-1"}],"next_page_token":"P:1"},{"vulns":[{"id":"B-1"}]}]}'),
            new Response(200, '{"results":[{"vulns":[{"id":"A-2"}]}]}'),
        );

        $results = new Osv(httpClient: $http)->queryBatch([self::query('a'), self::query('b')]);

        self::assertSame([
            'package' => ['name' => 'a', 'ecosystem' => 'Go'],
            'version' => '1.0.0',
            'page_token' => 'P:1',
        ], self::sentQueries($http, 1)[0] ?? null);
        self::assertCount(1, self::sentQueries($http, 1));
        self::assertSame(['A-1', 'A-2'], array_map(static fn(VulnerabilityReference $reference): string => $reference->id, $results[0]->vulnerabilities));
        self::assertSame('B-1', self::ids($results[1])[0] ?? null);
    }

    public function testQueryBatchWithoutQueriesSendsNothing(): void
    {
        $http = new MockHttpClient();

        self::assertSame([], new Osv(httpClient: $http)->queryBatch([]));
        self::assertSame(0, $http->callCount());
    }

    public function testQueryBatchRejectsAMismatchingNumberOfResults(): void
    {
        $http = new MockHttpClient(new Response(200, '{"results":[{}]}'));

        $this->expectException(SerializationException::class);

        new Osv(httpClient: $http)->queryBatch([self::query('a'), self::query('b')]);
    }

    public function testQueryBatchRejectsAReferenceWithoutId(): void
    {
        $http = new MockHttpClient(new Response(200, '{"results":[{"vulns":[{"modified":"2026-11-21T00:00:00Z"}]}]}'));

        $this->expectException(SerializationException::class);

        new Osv(httpClient: $http)->queryBatch([self::query('a')]);
    }

    public function testQueryBatchStopsAnEndlessChainOfPages(): void
    {
        $responses = array_fill(0, Osv::MAX_PAGES, new Response(200, '{"results":[{"vulns":[],"next_page_token":"P"}]}'));
        $http = new MockHttpClient(...$responses);

        try {
            new Osv(httpClient: $http)->queryBatch([self::query('a')]);
            self::fail('Expected a SerializationException.');
        } catch (SerializationException) {
            self::assertSame(Osv::MAX_PAGES, $http->callCount());
        }
    }

    public function testQueryBatchPassesTheErrorOfAnUnknownEcosystem(): void
    {
        $http = new MockHttpClient(new Response(400, '{"code":3,"message":"error in query at index 0: rpc error: code = InvalidArgument desc = invalid ecosystem"}'));

        try {
            new Osv(httpClient: $http)->queryBatch([new PackageQuery('NoSuch', 'x', '1')]);
            self::fail('Expected a BadRequestException.');
        } catch (BadRequestException $exception) {
            self::assertSame('3', $exception->errorCode);
            self::assertStringContainsString('invalid ecosystem', $exception->getMessage());
        }
    }

    public function testQueryBatchRejectsAMap(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // A map instead of a list is the point of the test.
        new Osv(httpClient: new MockHttpClient())->queryBatch([1 => self::query('a')]); // @phpstan-ignore argument.type
    }

    public function testQueryReturnsFullRecordsAcrossPages(): void
    {
        $first = self::fixture('UBUNTU-CVE-2026-110928.json');
        $second = self::fixture('UBUNTU-CVE-2026-119618.json');
        $http = new MockHttpClient(
            new Response(200, "{\"vulns\":[{$first}],\"next_page_token\":\"P:2\"}"),
            new Response(200, "{\"vulns\":[{$second}]}"),
        );
        $query = new PackageQuery(Ecosystem::ubuntu('24.04'), 'openssh', '1:9.6p1-3ubuntu13.20');

        $vulnerabilities = new Osv(httpClient: $http)->query($query);

        self::assertSame(['UBUNTU-CVE-2026-110928', 'UBUNTU-CVE-2026-119618'], [$vulnerabilities[0]->id ?? null, $vulnerabilities[1]->id ?? null]);
        self::assertSame('https://api.osv.dev/v1/query', $http->requests[0]->uri);
        self::assertArrayNotHasKey('page_token', $http->jsonBody(0));
        self::assertSame('P:2', $http->jsonBody(1)['page_token'] ?? null);
    }

    public function testQueryWithoutVulnerabilities(): void
    {
        self::assertSame([], new Osv(httpClient: new MockHttpClient(new Response(200, '{}')))->query(self::query('a')));
    }

    public function testVulnerabilityReadsOneRecord(): void
    {
        $http = new MockHttpClient(new Response(200, self::fixture('GO-2026-4970.json')));

        $vulnerability = new Osv(httpClient: $http, baseUri: 'https://osv.example/')->vulnerability('GO-2026-4970');

        self::assertSame('GO-2026-4970', $vulnerability->id);
        self::assertSame(Method::Get, $http->requests[0]->method);
        self::assertSame('https://osv.example/v1/vulns/GO-2026-4970', $http->requests[0]->uri);
    }

    public function testVulnerabilityThatDoesNotExist(): void
    {
        $http = new MockHttpClient(new Response(404, '{"code":5,"message":"Vulnerability not found"}'));

        $this->expectException(NotFoundException::class);

        new Osv(httpClient: $http)->vulnerability('DOES-NOT-EXIST-1');
    }

    public function testVulnerabilityRejectsAnIdThatWouldChangeThePath(): void
    {
        $http = new MockHttpClient();

        try {
            new Osv(httpClient: $http)->vulnerability('../query');
            self::fail('Expected an InvalidArgumentException.');
        } catch (InvalidArgumentException) {
            self::assertSame(0, $http->callCount());
        }
    }

    public function testVulnerabilityRejectsAnotherRecord(): void
    {
        $http = new MockHttpClient(new Response(200, '{"id":"GO-2026-1"}'));

        $this->expectException(SerializationException::class);

        new Osv(httpClient: $http)->vulnerability('GO-2026-4970');
    }

    public function testVulnerabilityRejectsAnHtmlPage(): void
    {
        $http = new MockHttpClient(new Response(200, '<html>maintenance</html>'));

        $this->expectException(SerializationException::class);

        new Osv(httpClient: $http)->vulnerability('GO-2026-4970');
    }

    /**
     * @return list<string>
     */
    private static function ids(BatchResult $result): array
    {
        return array_map(static fn(VulnerabilityReference $reference): string => $reference->id, $result->vulnerabilities);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function sentQueries(MockHttpClient $http, int $index): array
    {
        $queries = $http->jsonBody($index)['queries'] ?? null;

        return \is_array($queries) ? $queries : [];
    }

    /**
     * @param non-empty-string $name
     */
    private static function query(string $name): PackageQuery
    {
        return new PackageQuery(Ecosystem::GO, $name, '1.0.0');
    }

    private static function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__ . '/../Fixtures/' . $name);
    }
}
