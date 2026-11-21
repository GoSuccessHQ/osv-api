<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Model;

use GoSuccess\Osv\Internal\Read;

/**
 * The package an {@see Affected} entry is about.
 */
final readonly class Package
{
    public function __construct(
        public ?string $ecosystem,
        public ?string $name,
        public ?string $purl,
    ) {}

    /**
     * @internal
     */
    public static function fromArray(mixed $data): self
    {
        $data = Read::object($data);

        return new self(Read::string($data['ecosystem'] ?? null), Read::string($data['name'] ?? null), Read::string($data['purl'] ?? null));
    }
}
