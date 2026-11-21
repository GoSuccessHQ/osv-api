<?php

declare(strict_types=1);

namespace GoSuccess\Osv;

use InvalidArgumentException;

/**
 * Immutable transport and retry settings.
 *
 * Applies to every request of the client, whichever endpoint it reaches.
 */
final readonly class ClientOptions
{
    public const string DEFAULT_USER_AGENT = 'gosuccess/osv-api (+https://github.com/GoSuccessHQ/osv-api)';

    /**
     * The most queries OSV.dev accepts in one `querybatch` request (documented
     * limit of the API).
     */
    public const int MAX_BATCH_SIZE = 1000;

    /**
     * The two timeouts travel with every request (see {@see Http\Request}), so they
     * also apply through a custom transport and take precedence over the defaults
     * the transport was created with.
     *
     * @param float  $timeout        Maximum duration of a request in seconds; `0.0` means
     *                               no limit.
     * @param float  $connectTimeout Maximum duration of the connection phase in seconds.
     * @param int    $maxRetries     How often an idempotent request is retried after a
     *                               network failure or a server error (5xx); `0` disables
     *                               retries. The query endpoints are POST requests but only
     *                               read, so they count as idempotent here. A `429` is never
     *                               retried: the caller decides when to try again (see
     *                               {@see Exception\RateLimitException::$retryAfter}).
     * @param float  $retryBaseDelay Base delay in seconds of the exponential backoff.
     * @param float  $maxRetryDelay  Upper bound in seconds for a single wait.
     * @param string $userAgent      Value of the `User-Agent` header.
     * @param int<1, 1000> $batchSize Queries per `querybatch` request, 1 to
     *                               {@see MAX_BATCH_SIZE}.
     */
    public function __construct(
        public float $timeout = 60.0,
        public float $connectTimeout = 10.0,
        public int $maxRetries = 0,
        public float $retryBaseDelay = 1.0,
        public float $maxRetryDelay = 30.0,
        public string $userAgent = self::DEFAULT_USER_AGENT,
        public int $batchSize = self::MAX_BATCH_SIZE,
    ) {
        foreach (['timeout' => $timeout, 'connectTimeout' => $connectTimeout, 'retryBaseDelay' => $retryBaseDelay, 'maxRetryDelay' => $maxRetryDelay] as $name => $value) {
            // NAN and INF would turn into warnings or endless waits once converted for cURL or sleep().
            if (!is_finite($value) || $value < 0.0) {
                throw new InvalidArgumentException("{$name} must be a finite number that is not negative.");
            }
        }

        if ($maxRetries < 0) {
            throw new InvalidArgumentException('maxRetries must not be negative.');
        }

        if ($batchSize < 1 || $batchSize > self::MAX_BATCH_SIZE) {
            throw new InvalidArgumentException('batchSize must be between 1 and ' . self::MAX_BATCH_SIZE . '.');
        }

        if (trim($userAgent) === '') {
            throw new InvalidArgumentException('userAgent must not be empty.');
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $userAgent) === 1) {
            throw new InvalidArgumentException('userAgent must not contain control characters.');
        }
    }
}
