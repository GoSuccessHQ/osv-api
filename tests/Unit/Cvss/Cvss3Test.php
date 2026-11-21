<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Tests\Unit\Cvss;

use GoSuccess\Osv\Cvss\Cvss3;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Cvss3::class)]
final class Cvss3Test extends TestCase
{
    /**
     * Scores as the calculator of FIRST shows them for the same vectors.
     *
     * @return iterable<string, array{string, float}>
     */
    public static function vectors(): iterable
    {
        yield 'network, all high' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H', 9.8];
        yield 'regreSSHion-like, high complexity' => ['CVSS:3.1/AV:N/AC:H/PR:N/UI:N/S:U/C:H/I:H/A:H', 8.1];
        yield 'reflected xss' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:R/S:C/C:L/I:L/A:N', 6.1];
        yield 'stored xss' => ['CVSS:3.1/AV:N/AC:L/PR:L/UI:R/S:C/C:L/I:L/A:N', 5.4];
        yield 'local privilege escalation, v3.0' => ['CVSS:3.0/AV:L/AC:L/PR:L/UI:N/S:U/C:H/I:H/A:H', 7.8];
        yield 'scope changed, capped at 10' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:C/C:H/I:H/A:H', 10.0];
        yield 'information disclosure' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:N/A:N', 7.5];
        yield 'no impact' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:N/A:N', 0.0];
        yield 'with temporal metrics' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H/E:P/RL:O', 9.8];
    }

    #[DataProvider('vectors')]
    public function testBaseScore(string $vector, float $score): void
    {
        self::assertSame($score, Cvss3::baseScore($vector));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidVectors(): iterable
    {
        yield 'v2' => ['AV:N/AC:L/Au:N/C:P/I:P/A:P'];
        yield 'v4' => ['CVSS:4.0/AV:N/AC:L/AT:N/PR:N/UI:N/VC:H/VI:H/VA:H/SC:N/SI:N/SA:N'];
        yield 'missing metric' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H'];
        yield 'repeated metric' => ['CVSS:3.1/AV:N/AV:L/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H'];
        yield 'unknown value' => ['CVSS:3.1/AV:X/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H'];
        yield 'unknown scope' => ['CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:X/C:H/I:H/A:H'];
        yield 'garbage' => ['high'];
    }

    #[DataProvider('invalidVectors')]
    public function testInvalidVectorHasNoScore(string $vector): void
    {
        self::assertNull(Cvss3::baseScore($vector));
    }
}
