<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Model;

use GoSuccess\Osv\Internal\Read;

/**
 * One event of a {@see Range}: exactly one of the four fields is set in a
 * valid record.
 */
final readonly class Event
{
    public function __construct(
        public ?string $introduced = null,
        public ?string $fixed = null,
        public ?string $lastAffected = null,
        public ?string $limit = null,
    ) {}

    /**
     * @internal
     */
    public static function fromArray(mixed $data): self
    {
        $data = Read::object($data);

        return new self(
            Read::string($data['introduced'] ?? null),
            Read::string($data['fixed'] ?? null),
            Read::string($data['last_affected'] ?? null),
            Read::string($data['limit'] ?? null),
        );
    }
}
