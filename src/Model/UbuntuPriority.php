<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Model;

/**
 * The priority Ubuntu gives a CVE (Ubuntu CVE tracker). OSV.dev carries it as
 * a severity of type `Ubuntu`.
 */
enum UbuntuPriority: string
{
    /** Not assessed yet; ranks below every assessed priority. */
    case Untriaged = 'untriaged';
    case Negligible = 'negligible';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /**
     * Position in the order untriaged < negligible < low < medium < high <
     * critical, for thresholds.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Untriaged => 0,
            self::Negligible => 1,
            self::Low => 2,
            self::Medium => 3,
            self::High => 4,
            self::Critical => 5,
        };
    }

    /**
     * Whether this priority reaches the given threshold.
     */
    public function isAtLeast(self $threshold): bool
    {
        return $this->rank() >= $threshold->rank();
    }
}
