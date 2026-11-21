<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Tests\Unit\Model;

use GoSuccess\Osv\Model\Ecosystem;
use GoSuccess\Osv\Model\PackageQuery;
use GoSuccess\Osv\Model\UbuntuPriority;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ecosystem::class)]
#[CoversClass(PackageQuery::class)]
#[CoversClass(UbuntuPriority::class)]
final class QueryModelTest extends TestCase
{
    public function testUbuntuEcosystem(): void
    {
        self::assertSame('Ubuntu:24.04:LTS', Ecosystem::ubuntu('24.04'));
        self::assertSame('Ubuntu:25.10', Ecosystem::ubuntu('25.10', lts: false));
    }

    public function testUbuntuReleaseMustLookLikeOne(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Ecosystem::ubuntu('noble');
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidQueries(): iterable
    {
        yield 'empty ecosystem' => ['', 'a', '1'];
        yield 'blank name' => ['Go', ' ', '1'];
        yield 'empty version' => ['Go', 'a', ''];
        yield 'line break' => ['Go', "a\nb", '1'];
    }

    #[DataProvider('invalidQueries')]
    public function testQueryRejectsInvalidValues(string $ecosystem, string $name, string $version): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Invalid values are the point of the test.
        new PackageQuery($ecosystem, $name, $version); // @phpstan-ignore argument.type, argument.type, argument.type
    }

    public function testPriorityOrder(): void
    {
        self::assertTrue(UbuntuPriority::High->isAtLeast(UbuntuPriority::Medium));
        self::assertTrue(UbuntuPriority::Medium->isAtLeast(UbuntuPriority::Medium));
        self::assertFalse(UbuntuPriority::Low->isAtLeast(UbuntuPriority::Medium));
        self::assertFalse(UbuntuPriority::Untriaged->isAtLeast(UbuntuPriority::Negligible));
        self::assertTrue(UbuntuPriority::Critical->isAtLeast(UbuntuPriority::High));
    }
}
