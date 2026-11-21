<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Model;

use GoSuccess\Osv\Internal\Read;

/**
 * One affected package of a record, with its ranges and the listed versions.
 */
final readonly class Affected
{
    /**
     * @param list<Range>          $ranges
     * @param list<string>         $versions          versions listed as affected
     * @param array<string, mixed> $ecosystemSpecific free-form data of the ecosystem, e.g.
     *                                                Ubuntu's `priority_reason` and `binaries`
     * @param array<string, mixed> $databaseSpecific  free-form data of the source database
     */
    public function __construct(
        public Package $package,
        public array $ranges,
        public array $versions,
        public array $ecosystemSpecific,
        public array $databaseSpecific,
    ) {}

    /**
     * @internal
     */
    public static function fromArray(mixed $data): self
    {
        $data = Read::object($data);

        return new self(
            Package::fromArray($data['package'] ?? null),
            array_values(array_map(Range::fromArray(...), Read::array($data['ranges'] ?? null))),
            Read::stringList($data['versions'] ?? null),
            Read::object($data['ecosystem_specific'] ?? null),
            Read::object($data['database_specific'] ?? null),
        );
    }

    /**
     * The versions that fix this package, across all ranges. Empty means: no
     * fix is known (yet).
     *
     * @return list<string>
     */
    public function fixedVersions(): array
    {
        $fixed = [];

        foreach ($this->ranges as $range) {
            foreach ($range->fixedVersions() as $version) {
                $fixed[] = $version;
            }
        }

        return array_values(array_unique($fixed));
    }
}
