<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Http;

/**
 * HTTP methods. The client reads records with GET and POSTs its queries as
 * JSON; the others exist for custom transports.
 */
enum Method: string
{
    case Get = 'GET';
    case Post = 'POST';
    case Put = 'PUT';
    case Patch = 'PATCH';
    case Delete = 'DELETE';

    /**
     * Whether repeating the request has the same effect as sending it once by
     * the semantics of HTTP. The client itself retries its POST queries too,
     * because they only read.
     */
    public function isIdempotent(): bool
    {
        return match ($this) {
            self::Get, self::Put, self::Delete => true,
            self::Post, self::Patch => false,
        };
    }
}
