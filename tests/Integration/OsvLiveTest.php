<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Tests\Integration;

use GoSuccess\Osv\Exception\NotFoundException;
use GoSuccess\Osv\Model\Ecosystem;
use GoSuccess\Osv\Model\PackageQuery;
use GoSuccess\Osv\Model\UbuntuPriority;
use GoSuccess\Osv\Osv;
use GoSuccess\Osv\Tests\Support\LiveTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Four read-only requests against api.osv.dev.
 */
#[CoversClass(Osv::class)]
final class OsvLiveTest extends LiveTestCase
{
    public function testQueryBatchFindsKnownVulnerabilities(): void
    {
        // An early 24.04 build of OpenSSH and Go 1.26.0 have known, fixed vulnerabilities.
        $results = $this->client->queryBatch([
            new PackageQuery(Ecosystem::ubuntu('24.04'), 'openssh', '1:9.6p1-3ubuntu13'),
            new PackageQuery(Ecosystem::GO, 'stdlib', '1.26.0'),
        ]);

        self::assertCount(2, $results);
        self::assertNotEmpty($results[0]->vulnerabilities);
        self::assertNotEmpty($results[1]->vulnerabilities);
    }

    public function testQueryReturnsFullRecords(): void
    {
        $vulnerabilities = $this->client->query(new PackageQuery(Ecosystem::ubuntu('24.04'), 'openssh', '1:9.6p1-3ubuntu13'));

        self::assertNotEmpty($vulnerabilities);
        self::assertNotNull($vulnerabilities[0]->modified);
    }

    public function testVulnerabilityReadsAUbuntuRecord(): void
    {
        $vulnerability = $this->client->vulnerability('UBUNTU-CVE-2024-6387');

        self::assertContains('CVE-2024-6387', $vulnerability->cveIds());
        self::assertInstanceOf(UbuntuPriority::class, $vulnerability->ubuntuPriority());
        self::assertNotSame([], $vulnerability->fixedVersions(Ecosystem::ubuntu('24.04'), 'openssh'));
    }

    public function testUnknownVulnerability(): void
    {
        $this->expectException(NotFoundException::class);

        $this->client->vulnerability('DOES-NOT-EXIST-1');
    }
}
