<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Tests\Support;

use GoSuccess\Osv\Osv;
use PHPUnit\Framework\TestCase;

/**
 * Base of the live tests: they read the real OSV.dev API (a few read-only
 * requests) and are skipped unless OSV_LIVE=1.
 */
abstract class LiveTestCase extends TestCase
{
    protected Osv $client;

    protected function setUp(): void
    {
        if (getenv('OSV_LIVE') !== '1') {
            self::markTestSkipped('Set OSV_LIVE=1 to query the live OSV.dev API.');
        }

        $this->client = new Osv();
    }
}
