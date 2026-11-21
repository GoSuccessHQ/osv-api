<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Model;

use GoSuccess\Osv\Internal\Read;

/**
 * A link of a record, e.g. an advisory or a fix.
 */
final readonly class Reference
{
    /**
     * @param string|null $type e.g. `ADVISORY`, `REPORT`, `FIX`, `WEB`
     */
    public function __construct(
        public ?string $type,
        public ?string $url,
    ) {}

    /**
     * @internal
     */
    public static function fromArray(mixed $data): self
    {
        $data = Read::object($data);

        return new self(Read::string($data['type'] ?? null), Read::string($data['url'] ?? null));
    }
}
