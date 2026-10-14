<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Search;

/**
 * Automatic ranges for the "ranges" display type: about five ranges whose bounds are round numbers in the
 * currency or unit shoppers see (steps of 1, 2 or 5 × 10^n: 0–50, 50–100, …), the last one open ("100 and above").
 */
class NiceRanges
{
    /** About this many ranges. */
    public const TARGET = 5;

    /**
     * @param float $min lowest value found
     * @param float $max highest value found
     * @return array<int, array{float, ?float}> [from, to) ranges; the last has no upper bound
     */
    public function between(float $min, float $max): array
    {
        if ($max <= $min) {
            return [[$this->floor($min, 1.0), null]];
        }
        $step = $this->step(($max - $min) / self::TARGET);
        $from = $this->floor($min, $step);
        $ranges = [];
        while ($from + $step <= $max) {
            $ranges[] = [$from, $from + $step];
            $from += $step;
        }
        $ranges[] = [$from, null];

        return $ranges;
    }

    /**
     * The smallest round step (1, 2, 5 × 10^n) of at least $raw.
     *
     * @param float $raw
     * @return float
     */
    public function step(float $raw): float
    {
        $magnitude = 10 ** floor(log10(max($raw, 0.01)));
        foreach ([1, 2, 5, 10] as $factor) {
            if ($factor * $magnitude >= $raw) {
                return round($factor * $magnitude, 2);
            }
        }

        return round(10 * $magnitude, 2);
    }

    /**
     * @param float $value
     * @param float $step
     * @return float
     */
    private function floor(float $value, float $step): float
    {
        return max(0.0, floor(round($value / $step, 6)) * $step);
    }
}
