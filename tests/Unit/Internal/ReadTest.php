<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Tests\Unit\Internal;

use GoSuccess\Osv\Internal\Read;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Read::class)]
final class ReadTest extends TestCase
{
    public function testStringKeepsOnlyStringsThatAreNotBlank(): void
    {
        self::assertSame('a', Read::string('a'));
        self::assertNull(Read::string(' '));
        self::assertNull(Read::string(5));
        self::assertNull(Read::string(null));
    }

    public function testObjectKeepsStringKeys(): void
    {
        self::assertSame(['a' => 1], Read::object(['a' => 1, 0 => 2]));
        self::assertSame([], Read::object('x'));
    }

    public function testStringListSkipsEverythingElse(): void
    {
        self::assertSame(['a', 'b'], Read::stringList(['a', 1, '', null, 'b']));
        self::assertSame([], Read::stringList('a'));
    }

    /**
     * @return iterable<string, array{mixed, ?string}>
     */
    public static function timestamps(): iterable
    {
        yield 'nanoseconds' => ['2026-11-18T14:20:09.446439022Z', '2026-11-18T14:20:09.446439+00:00'];
        yield 'microseconds' => ['2026-10-30T03:06:21.160262Z', '2026-10-30T03:06:21.160262+00:00'];
        yield 'whole seconds' => ['2026-11-12T00:00:00Z', '2026-11-12T00:00:00.000000+00:00'];
        yield 'offset' => ['2026-11-12T02:00:00+02:00', '2026-11-12T00:00:00.000000+00:00'];
        yield 'impossible date' => ['2026-02-30T00:00:00Z', null];
        yield 'without zone' => ['2026-11-12T00:00:00', null];
        yield 'date only' => ['2026-11-12', null];
        yield 'not a string' => [1_800_000_000, null];
    }

    #[DataProvider('timestamps')]
    public function testTimestamp(mixed $input, ?string $expected): void
    {
        self::assertSame($expected, Read::timestamp($input)?->format('Y-m-d\TH:i:s.uP'));
    }
}
