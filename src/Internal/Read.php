<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Internal;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Turns foreign field values into typed values without ever throwing: anything
 * that does not fit becomes `null` or an empty collection.
 *
 * @internal
 */
final class Read
{
    private function __construct() {}

    /**
     * A string that is not empty after trimming, else `null`.
     *
     * @return non-empty-string|null
     */
    public static function string(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' && trim($value) !== '' ? $value : null;
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function array(mixed $value): array
    {
        return \is_array($value) ? $value : [];
    }

    /**
     * A JSON object as an array with string keys; anything else is empty.
     *
     * @return array<string, mixed>
     */
    public static function object(mixed $value): array
    {
        $object = [];

        foreach (self::array($value) as $key => $item) {
            if (\is_string($key)) {
                $object[$key] = $item;
            }
        }

        return $object;
    }

    /**
     * The strings of a list that are not empty, in their order.
     *
     * @return list<non-empty-string>
     */
    public static function stringList(mixed $value): array
    {
        return array_values(array_filter(array_map(self::string(...), self::array($value)), \is_string(...)));
    }

    /**
     * Parse an RFC 3339 timestamp as OSV.dev writes it, with or without
     * fractional seconds (up to nanoseconds, cut to microseconds) and with `Z` or
     * an offset. Anything else, including impossible dates, is `null`.
     */
    public static function timestamp(mixed $value): ?DateTimeImmutable
    {
        if (!\is_string($value)) {
            return null;
        }

        $pattern = '/^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})(?:\.(\d{1,9}))?(Z|[+-]\d{2}:\d{2})$/D';

        if (preg_match($pattern, $value, $matches) !== 1) {
            return null;
        }

        $fraction = str_pad(substr($matches[3], 0, 6), 6, '0');
        $offset = $matches[4] === 'Z' ? '+00:00' : $matches[4];
        $normalized = "{$matches[1]}T{$matches[2]}.{$fraction}{$offset}";
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s.uP', $normalized);

        // The round trip rejects overflowing fields such as 2026-02-30 or 25:00:00.
        if ($date === false || $date->format('Y-m-d\TH:i:s.uP') !== $normalized) {
            return null;
        }

        return $date->setTimezone(new DateTimeZone('UTC'));
    }
}
