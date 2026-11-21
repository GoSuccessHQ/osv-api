<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Internal;

use GoSuccess\Osv\ClientOptions;
use GoSuccess\Osv\Exception\ApiException;
use GoSuccess\Osv\Exception\SerializationException;
use GoSuccess\Osv\Exception\TransportException;
use GoSuccess\Osv\Http\HttpClient;
use GoSuccess\Osv\Http\Method;
use GoSuccess\Osv\Http\Request;
use GoSuccess\Osv\Http\Response;
use JsonException;

/**
 * Sends the requests of the client: adds the shared headers and timeouts,
 * retries transient failures and turns error statuses into exceptions.
 *
 * Every OSV.dev endpoint the client uses only reads, including the POST
 * queries, so all of them may be retried.
 *
 * @internal
 */
final class Connection
{
    public function __construct(
        private readonly ClientOptions $options,
        private readonly HttpClient $httpClient,
    ) {}

    /**
     * @param non-empty-string $uri
     *
     * @throws ApiException       for an error status
     * @throws TransportException when the request keeps failing
     */
    public function get(string $uri): Response
    {
        return $this->send(Method::Get, $uri, [], null);
    }

    /**
     * POST a JSON object to an endpoint that only reads.
     *
     * @param non-empty-string     $uri
     * @param array<string, mixed> $payload
     *
     * @throws ApiException
     * @throws SerializationException when the payload cannot be encoded
     * @throws TransportException
     */
    public function postJson(string $uri, array $payload): Response
    {
        try {
            $body = json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new SerializationException('Could not encode the request: ' . $exception->getMessage(), 0, $exception);
        }

        return $this->send(Method::Post, $uri, ['Content-Type' => 'application/json'], $body);
    }

    /**
     * @param non-empty-string      $uri
     * @param array<string, string> $extraHeaders
     */
    private function send(Method $method, string $uri, array $extraHeaders, ?string $body): Response
    {
        $request = new Request(
            method: $method,
            uri: $uri,
            headers: [
                'User-Agent' => $this->options->userAgent,
                'Accept' => 'application/json',
                ...$extraHeaders,
            ],
            body: $body,
            timeout: $this->options->timeout,
            connectTimeout: $this->options->connectTimeout,
        );

        for ($attempt = 0; ; $attempt++) {
            $canRetry = $attempt < $this->options->maxRetries;

            try {
                $response = $this->httpClient->send($request);
            } catch (TransportException $exception) {
                if (!$canRetry) {
                    throw $exception;
                }

                $this->wait($attempt);

                continue;
            }

            if ($response->isSuccessful) {
                return $response;
            }

            if ($response->statusCode < 500 || !$canRetry) {
                throw ApiException::fromResponse($response, $request);
            }

            $this->wait($attempt);
        }
    }

    private function wait(int $attempt): void
    {
        $delay = min($this->options->maxRetryDelay, $this->options->retryBaseDelay * (2 ** $attempt));

        if ($delay > 0.0) {
            usleep((int) ($delay * 1_000_000));
        }
    }
}
