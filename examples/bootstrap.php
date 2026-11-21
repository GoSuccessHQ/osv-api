<?php

declare(strict_types=1);

/**
 * Shared setup of the examples: the autoloader and a client with the default
 * options. OSV.dev needs no API key. The examples send real, read-only requests
 * when run by hand.
 */

use GoSuccess\Osv\Osv;

require __DIR__ . '/../vendor/autoload.php';

return new Osv();
