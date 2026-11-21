<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Tests\Unit\Internal;

use GoSuccess\Osv\ClientOptions;
use GoSuccess\Osv\Exception\NotFoundException;
use GoSuccess\Osv\Exception\RateLimitException;
use GoSuccess\Osv\Exception\SerializationException;
use GoSuccess\Osv\Exception\ServerException;
use GoSuccess\Osv\Exception\TransportException;
use GoSuccess\Osv\Http\Method;
use GoSuccess\Osv\Http\Response;
use GoSuccess\Osv\Internal\Connection;
use GoSuccess\Osv\Tests\Support\MockHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Connection::class)]
final class ConnectionTest extends TestCase
{
    private const string URI = 'https://api.osv.dev/v1/query';

    public function testEveryRequestCarriesTimeoutsAndUserAgent(): void
    {
        $http = new MockHttpClient(new Response(200, '{}'));
        $connection = new Connection(new ClientOptions(timeout: 5.0, connectTimeout: 2.0), $http);

        $connection->get(self::URI);

        $request = $http->requests[0];
        self::assertSame(Method::Get, $request->method);
        self::assertSame(self::URI, $request->uri);
        self::assertSame(5.0, $request->timeout);
        self::assertSame(2.0, $request->connectTimeout);
        self::assertSame(ClientOptions::DEFAULT_USER_AGENT, $request->headers['User-Agent']);
        self::assertSame('application/json', $request->headers['Accept']);
    }

    public function testPostJsonSendsAJsonBody(): void
    {
        $http = new MockHttpClient(new Response(200, '{}'));

        new Connection(new ClientOptions(), $http)->postJson(self::URI, ['version' => '1:9.6p1-3ubuntu13.20', 'package' => ['name' => 'a/b']]);

        $request = $http->requests[0];
        self::assertSame(Method::Post, $request->method);
        self::assertSame('application/json', $request->headers['Content-Type']);
        self::assertSame('{"version":"1:9.6p1-3ubuntu13.20","package":{"name":"a/b"}}', $request->body);
    }

    public function testPayloadThatCannotBeEncodedThrows(): void
    {
        $http = new MockHttpClient();

        $this->expectException(SerializationException::class);

        new Connection(new ClientOptions(), $http)->postJson(self::URI, ['name' => "\xB1"]);
    }

    public function testErrorStatusThrows(): void
    {
        $connection = new Connection(new ClientOptions(), new MockHttpClient(new Response(404, '{"code":5,"message":"Vulnerability not found"}')));

        try {
            $connection->get('https://api.osv.dev/v1/vulns/NOPE');
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $exception) {
            self::assertSame('5', $exception->errorCode);
            self::assertStringContainsString('Vulnerability not found', $exception->getMessage());
        }
    }

    public function testRateLimitIsNeverRetried(): void
    {
        $http = new MockHttpClient(new Response(429, '', ['retry-after' => '120']));
        $connection = new Connection(new ClientOptions(maxRetries: 3), $http);

        try {
            $connection->get(self::URI);
            self::fail('Expected a RateLimitException.');
        } catch (RateLimitException $exception) {
            self::assertSame(120, $exception->retryAfter);
        }

        self::assertSame(1, $http->callCount());
    }

    public function testServerErrorIsRetriedExactlyMaxRetriesTimes(): void
    {
        $http = new MockHttpClient(new Response(503), new Response(503), new Response(503));
        $connection = new Connection(new ClientOptions(maxRetries: 2, retryBaseDelay: 0.0), $http);

        try {
            $connection->postJson(self::URI, []);
            self::fail('Expected a ServerException.');
        } catch (ServerException) {
            self::assertSame(3, $http->callCount());
        }
    }

    public function testTransportErrorIsRetried(): void
    {
        $http = new MockHttpClient(new TransportException('connection reset'), new Response(200, '{}'));
        $connection = new Connection(new ClientOptions(maxRetries: 1, retryBaseDelay: 0.0), $http);

        self::assertSame(200, $connection->get(self::URI)->statusCode);
        self::assertSame(2, $http->callCount());
    }

    public function testNothingIsRetriedByDefault(): void
    {
        $http = new MockHttpClient(new Response(503));

        try {
            new Connection(new ClientOptions(), $http)->get(self::URI);
            self::fail('Expected a ServerException.');
        } catch (ServerException) {
            self::assertSame(1, $http->callCount());
        }
    }
}
