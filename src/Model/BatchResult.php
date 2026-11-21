<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Model;

/**
 * The answer to one query of a batch: every vulnerability that affects the
 * queried package version, across all pages.
 */
final readonly class BatchResult
{
    /**
     * @param list<VulnerabilityReference> $vulnerabilities
     */
    public function __construct(
        public PackageQuery $query,
        public array $vulnerabilities,
    ) {}
}
