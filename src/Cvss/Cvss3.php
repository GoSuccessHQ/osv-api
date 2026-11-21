<?php

declare(strict_types=1);

namespace GoSuccess\Osv\Cvss;

/**
 * Computes the base score of a CVSS v3.0 or v3.1 vector, because OSV.dev only
 * ships the vector. Formula and rounding follow the CVSS v3.1 specification,
 * section 7 and appendix A (https://www.first.org/cvss/v3.1/specification-document);
 * v3.0 vectors use the same formula.
 */
final class Cvss3
{
    /**
     * Weights of the base metrics. The privileges required depend on the scope
     * and are handled separately.
     */
    private const array WEIGHTS = [
        'AV' => ['N' => 0.85, 'A' => 0.62, 'L' => 0.55, 'P' => 0.2],
        'AC' => ['L' => 0.77, 'H' => 0.44],
        'UI' => ['N' => 0.85, 'R' => 0.62],
        'C' => ['H' => 0.56, 'L' => 0.22, 'N' => 0.0],
        'I' => ['H' => 0.56, 'L' => 0.22, 'N' => 0.0],
        'A' => ['H' => 0.56, 'L' => 0.22, 'N' => 0.0],
    ];

    private function __construct() {}

    /**
     * The base score from 0.0 to 10.0, or `null` if the vector is not a valid
     * v3.0/v3.1 vector with all eight base metrics exactly once. Temporal and
     * environmental metrics in the vector are ignored.
     */
    public static function baseScore(string $vector): ?float
    {
        $metrics = self::parse($vector);

        if ($metrics === null) {
            return null;
        }

        $changed = $metrics['S'] === 'C';
        $privileges = match ($metrics['PR']) {
            'N' => 0.85,
            'L' => $changed ? 0.68 : 0.62,
            'H' => $changed ? 0.5 : 0.27,
            default => null,
        };

        $weights = [];

        foreach (self::WEIGHTS as $metric => $values) {
            $weight = $values[$metrics[$metric]] ?? null;

            if ($weight === null) {
                return null;
            }

            $weights[$metric] = $weight;
        }

        if ($privileges === null || !\in_array($metrics['S'], ['U', 'C'], true)) {
            return null;
        }

        $iss = 1 - ((1 - $weights['C']) * (1 - $weights['I']) * (1 - $weights['A']));
        $impact = $changed
            ? 7.52 * ($iss - 0.029) - 3.25 * (($iss - 0.02) ** 15)
            : 6.42 * $iss;
        $exploitability = 8.22 * $weights['AV'] * $weights['AC'] * $privileges * $weights['UI'];

        if ($impact <= 0) {
            return 0.0;
        }

        return $changed
            ? self::roundUp(min(1.08 * ($impact + $exploitability), 10))
            : self::roundUp(min($impact + $exploitability, 10));
    }

    /**
     * The base metrics of a vector, or null if one is missing, repeated or the
     * prefix is not `CVSS:3.0` or `CVSS:3.1`.
     *
     * @return array<string, string>|null
     */
    private static function parse(string $vector): ?array
    {
        $parts = explode('/', trim($vector));

        if (!\in_array(array_shift($parts), ['CVSS:3.0', 'CVSS:3.1'], true)) {
            return null;
        }

        $base = ['AV', 'AC', 'PR', 'UI', 'S', 'C', 'I', 'A'];
        $metrics = [];

        foreach ($parts as $part) {
            if (preg_match('/^([A-Z]{1,3}):([A-Z])$/D', $part, $matches) !== 1) {
                return null;
            }

            if (!\in_array($matches[1], $base, true)) {
                continue;
            }

            if (isset($metrics[$matches[1]])) {
                return null;
            }

            $metrics[$matches[1]] = $matches[2];
        }

        return \count($metrics) === \count($base) ? $metrics : null;
    }

    /**
     * Round up to one decimal as in appendix A of the v3.1 specification, which
     * avoids errors of floating point arithmetic.
     */
    private static function roundUp(float $value): float
    {
        $integer = (int) round($value * 100_000);

        if ($integer % 10_000 === 0) {
            return $integer / 100_000;
        }

        return (floor($integer / 10_000) + 1) / 10.0;
    }
}
