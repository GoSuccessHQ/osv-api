<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Model;

use InvalidArgumentException;

/**
 * Names of the OSV ecosystems this package was measured with. Any other
 * ecosystem name that OSV.dev knows works too; see
 * https://ossf.github.io/osv-schema/#defined-ecosystems.
 */
final class Ecosystem
{
    /** Go modules, including the standard library as package `stdlib`. */
    public const string GO = 'Go';

    /** Composer packages from packagist.org. */
    public const string PACKAGIST = 'Packagist';

    private function __construct() {}

    /**
     * The ecosystem of an Ubuntu release, e.g. `Ubuntu:24.04:LTS`. Ubuntu records
     * are keyed by the **source** package (`openssh`, not `openssh-server`).
     *
     * @param string $release e.g. `24.04`
     * @param bool   $lts     whether the release is a long-term support release
     *
     * @return non-empty-string
     */
    public static function ubuntu(string $release, bool $lts = true): string
    {
        if (preg_match('/^\d{2}\.\d{2}$/D', $release) !== 1) {
            throw new InvalidArgumentException('release must look like "24.04".');
        }

        return 'Ubuntu:' . $release . ($lts ? ':LTS' : '');
    }
}
