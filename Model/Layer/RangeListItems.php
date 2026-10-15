<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Layer;

use Iranimij\OpenFilter\Model\Search\NiceRanges;
use Iranimij\OpenFilter\Model\Search\RangeLists;
use Iranimij\OpenFilter\Model\Url\Range;
use Iranimij\OpenFilter\Model\Url\RangeList;

/**
 * A price or decimal filter shown as a list of ranges (1.1): registers the picked ranges and the ranges to count for
 * the search request, and turns the counts back into options. Ranges are [from, to) in what shoppers see; the
 * conversions to and from index values (currency, tax) come from the filter.
 */
class RangeListItems
{
    /**
     * @param RangeLists $lists
     * @param RangeList $rangeList
     * @param NiceRanges $niceRanges
     * @param Range $range
     */
    public function __construct(
        private readonly RangeLists $lists,
        private readonly RangeList $rangeList,
        private readonly NiceRanges $niceRanges,
        private readonly Range $range
    ) {
    }

    /**
     * Picked ranges as [from, to) pairs in display values; unreadable values are left out.
     *
     * @param string[] $values
     * @return array<int, array{?float, ?float}>
     */
    public function picked(array $values): array
    {
        return array_values(array_filter(array_map(fn (string $value): ?array => $this->range->parse($value), $values)));
    }

    /**
     * @param string $field request field
     * @param string[] $values picked ranges ("0-50")
     * @param string|null $merchantList the merchant's ranges, null = automatic
     * @param callable(float): float $toIndex display value to index value
     * @return void
     */
    public function register(string $field, array $values, ?string $merchantList, callable $toIndex): void
    {
        $index = static fn (array $range): array => [
            $range[0] === null ? null : $toIndex($range[0]),
            $range[1] === null ? null : $toIndex($range[1]),
        ];
        $list = $merchantList === null ? null : $this->rangeList->parse($merchantList);
        $interval = $toIndex(1.0) - $toIndex(0.0);
        $this->lists->register(
            $field,
            array_map($index, $this->picked($values)),
            $list === null ? null : array_map($index, $list),
            $interval > 0 ? $interval : 1.0
        );
    }

    /**
     * Ranges with their counts, in order; ranges without products are left out unless picked.
     *
     * @param string $field request field
     * @param string[] $values picked ranges
     * @param string|null $merchantList the merchant's ranges, null = automatic
     * @param array{min: float, max: float}|null $stats display bounds of the products found (automatic ranges)
     * @param callable(float): float $toDisplay index value to display value
     * @return array<int, array{from: ?float, to: ?float, value: string, count: int, selected: bool}>
     */
    public function items(string $field, array $values, ?string $merchantList, ?array $stats, callable $toDisplay): array
    {
        $buckets = $this->lists->counts($field) ?? [];
        $list = $merchantList === null ? null : $this->rangeList->parse($merchantList);
        if ($list !== null) {
            $counts = array_map(static fn (array $bucket): int => (int) ($bucket['doc_count'] ?? 0), $buckets);
        } else {
            if ($stats === null) {
                return [];
            }
            $list = $this->niceRanges->between($stats['min'], $stats['max']);
            $counts = array_fill(0, count($list), 0);
            foreach ($buckets as $bucket) {
                $value = round($toDisplay((float) ($bucket['key'] ?? 0)), 2);
                foreach ($list as $position => [$from, $to]) {
                    if (($from === null || $value >= $from) && ($to === null || $value < $to)) {
                        $counts[$position] += (int) ($bucket['doc_count'] ?? 0);
                        break;
                    }
                }
            }
        }
        $picked = array_flip($values);
        $items = [];
        foreach ($list as $position => [$from, $to]) {
            $value = $this->range->format($from, $to);
            $count = $counts[$position] ?? 0;
            $selected = isset($picked[$value]);
            if ($count > 0 || $selected) {
                $items[] = ['from' => $from, 'to' => $to, 'value' => $value, 'count' => $count, 'selected' => $selected];
            }
        }

        return $items;
    }
}
