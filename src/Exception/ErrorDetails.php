<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Exception;

/**
 * Normalizes the error bodies of OSV.dev, e.g. `{"code": 5, "message":
 * "Vulnerability not found"}`: the `message` forms the exception message and
 * the numeric `code` (a gRPC status code) the error code; plain text or HTML
 * (e.g. from a proxy) is shortened.
 *
 * @internal
 */
final readonly class ErrorDetails
{
    private const int MAX_MESSAGE_BYTES = 500;

    private function __construct(
        public ?string $message,
        public ?string $errorCode,
    ) {}

    public static function parse(string $body): self
    {
        if (trim($body) === '') {
            return new self(null, null);
        }

        $decoded = json_decode($body, true);

        if (!\is_array($decoded)) {
            // Plain text or an HTML error page.
            return new self(self::excerpt($body), null);
        }

        $message = self::text($decoded['message'] ?? null);
        $code = $decoded['code'] ?? null;

        return new self(
            $message === null ? null : self::excerpt($message),
            \is_int($code) ? (string) $code : self::text($code),
        );
    }

    private static function text(mixed $value): ?string
    {
        return \is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private static function excerpt(string $text): string
    {
        $text = trim($text);

        if (\strlen($text) <= self::MAX_MESSAGE_BYTES) {
            return $text;
        }

        $cut = substr($text, 0, self::MAX_MESSAGE_BYTES);

        // Drop a trailing, incomplete multi-byte sequence (ext-mbstring is not required).
        return (preg_replace('/[\xC0-\xFF][\x80-\xBF]*$/', '', $cut) ?? $cut) . '…';
    }
}
