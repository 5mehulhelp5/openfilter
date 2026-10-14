<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Url;

/**
 * A list of price or decimal ranges as merchants write it for the "ranges" display type and as URLs carry it:
 * "0-50,50-100,100-". Ranges are in ascending order and do not overlap (one range may end where the next starts);
 * only the first may be open below and only the last open above.
 */
class RangeList
{
    /** Most ranges in one list (a longer list is not a filter anyone reads). */
    public const MAX_RANGES = 20;

    /**
     * @param Range $range
     */
    public function __construct(
        private readonly Range $range
    ) {
    }

    /**
     * @param string $value
     * @return array<int, array{?float, ?float}>|null ranges in order; null when the list is not valid
     */
    public function parse(string $value): ?array
    {
        $tokens = array_map('trim', explode(',', $value));
        if ($tokens === [''] || count($tokens) > self::MAX_RANGES) {
            return null;
        }
        $ranges = [];
        foreach ($tokens as $token) {
            $range = $this->range->parse($token);
            if ($range === null || ($range[0] !== null && $range[0] === $range[1])) {
                return null;
            }
            $ranges[] = $range;
        }
        $last = count($ranges) - 1;
        foreach ($ranges as $index => [$from, $to]) {
            if (($from === null && $index !== 0) || ($to === null && $index !== $last)) {
                return null;
            }
            $next = $ranges[$index + 1][0] ?? null;
            if ($index < $last && ($next === null || $to === null || $next < $to)) {
                return null;
            }
        }

        return $ranges;
    }

    /**
     * Canonical form ("0-50,50-100,100-"), or null when the list is not valid.
     *
     * @param string $value
     * @return string|null
     */
    public function normalise(string $value): ?string
    {
        $ranges = $this->parse($value);
        if ($ranges === null) {
            return null;
        }

        return implode(',', array_map(fn (array $range): string => $this->range->format($range[0], $range[1]), $ranges));
    }
}
