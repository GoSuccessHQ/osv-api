<?php

declare(strict_types=1);

/**
 * Checks a few installed packages of an Ubuntu 24.04 server and prints every
 * known vulnerability with Ubuntu's priority, the CVSS base score and the
 * fixed version, if there is one.
 *
 * Usage: php examples/server-packages.php
 */

use GoSuccess\Osv\Model\Ecosystem;
use GoSuccess\Osv\Model\PackageQuery;
use GoSuccess\Osv\Osv;

/** @var Osv $osv */
$osv = require __DIR__ . '/bootstrap.php';

$ubuntu = Ecosystem::ubuntu('24.04');

// Ubuntu records are keyed by the source package: openssh, not openssh-server.
$queries = [
    new PackageQuery($ubuntu, 'openssh', '1:9.6p1-3ubuntu13.20'),
    new PackageQuery($ubuntu, 'apache2', '2.4.58-1ubuntu8.18'),
    new PackageQuery(Ecosystem::GO, 'stdlib', '1.26.4'),
];

foreach ($osv->queryBatch($queries) as $result) {
    $query = $result->query;
    printf("%s %s (%s): %d\n", $query->name, $query->version, $query->ecosystem, count($result->vulnerabilities));

    foreach ($result->vulnerabilities as $reference) {
        $vulnerability = $osv->vulnerability($reference->id);
        $fixed = $vulnerability->fixedVersions($query->ecosystem, $query->name);

        printf(
            "  %s %s priority=%s cvss=%s fixed=%s\n",
            $vulnerability->id,
            implode(',', $vulnerability->cveIds()),
            $vulnerability->ubuntuPriority()->value ?? '-',
            $vulnerability->cvssBaseScore() ?? '-',
            $fixed === [] ? 'no fix yet' : implode(',', $fixed),
        );
    }
}
