<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Model;

use GoSuccess\Osv\Cvss\Cvss3;
use GoSuccess\Osv\Internal\Read;

/**
 * One severity rating of a record: a CVSS vector or, for Ubuntu, a priority.
 */
final readonly class Severity
{
    public const string CVSS_V2 = 'CVSS_V2';

    public const string CVSS_V3 = 'CVSS_V3';

    public const string CVSS_V4 = 'CVSS_V4';

    public const string UBUNTU = 'Ubuntu';

    /**
     * @param string|null $type  one of the constants of this class, or a type OSV.dev added later
     * @param string|null $score a CVSS vector, or for {@see UBUNTU} a priority such as `high`
     */
    public function __construct(
        public ?string $type,
        public ?string $score,
    ) {}

    /**
     * @internal
     */
    public static function fromArray(mixed $data): self
    {
        $data = Read::object($data);

        return new self(Read::string($data['type'] ?? null), Read::string($data['score'] ?? null));
    }

    /**
     * The Ubuntu priority, if this is an Ubuntu rating with a known value.
     */
    public function ubuntuPriority(): ?UbuntuPriority
    {
        return $this->type === self::UBUNTU && $this->score !== null ? UbuntuPriority::tryFrom($this->score) : null;
    }

    /**
     * The CVSS base score computed from a v3.0 or v3.1 vector; `null` for other
     * types and for vectors that are not valid. CVSS v2 and v4 are not computed.
     */
    public function cvssBaseScore(): ?float
    {
        return $this->type === self::CVSS_V3 && $this->score !== null ? Cvss3::baseScore($this->score) : null;
    }
}
