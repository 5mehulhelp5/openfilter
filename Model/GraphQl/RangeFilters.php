<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\GraphQl;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Layer\RangeListItems;
use Iranimij\OpenFilter\Model\Url\Range;

/**
 * Price and decimal filters shown as a list of ranges, for a GraphQL search: registers the ranges to count and the
 * picked range of the `filter` argument ({from, to}) before the search runs, and reads the counted ranges back.
 * GraphQL works in the units of Magento's price filter (no display currency or tax conversion).
 */
class RangeFilters
{
    /**
     * @param FilterConfigProvider $filters
     * @param RangeListItems $items
     * @param Range $range
     */
    public function __construct(
        private readonly FilterConfigProvider $filters,
        private readonly RangeListItems $items,
        private readonly Range $range
    ) {
    }

    /**
     * @param int $storeId
     * @param array<string, mixed> $filterArgs the `filter` argument of `products`
     * @return void
     */
    public function register(int $storeId, array $filterArgs): void
    {
        foreach ($this->rangeFilters($storeId) as $filter) {
            $this->items->register(
                $filter->getCode(),
                $this->picked($filterArgs, $filter->getCode()),
                $filter->getRanges(),
                static fn (float $value): float => $value
            );
        }
    }

    /**
     * @param Filter $filter
     * @param array<string, mixed> $filterArgs
     * @param array{min: float, max: float}|null $stats
     * @return array<int, array{from: ?float, to: ?float, value: string, count: int, selected: bool}>
     */
    public function options(Filter $filter, array $filterArgs, ?array $stats): array
    {
        return $this->items->items(
            $filter->getCode(),
            $this->picked($filterArgs, $filter->getCode()),
            $filter->getRanges(),
            $stats,
            static fn (float $value): float => $value
        );
    }

    /**
     * The picked range of a filter argument ({from, to}) in the range format ("10-30"), or none.
     *
     * @param array<string, mixed> $filterArgs
     * @param string $code
     * @return string[]
     */
    public function picked(array $filterArgs, string $code): array
    {
        $arg = $filterArgs[$code] ?? null;
        if (!is_array($arg) || (!isset($arg['from']) && !isset($arg['to']))) {
            return [];
        }
        $from = is_numeric($arg['from'] ?? null) ? (float) $arg['from'] : null;
        $to = is_numeric($arg['to'] ?? null) ? (float) $arg['to'] : null;

        return $from === null && $to === null ? [] : [$this->range->format($from, $to)];
    }

    /**
     * @param int $storeId
     * @return Filter[]
     */
    private function rangeFilters(int $storeId): array
    {
        return array_values(array_filter(
            $this->filters->getActive($storeId),
            static fn (Filter $filter): bool => $filter->getDisplayMode() === Filter::DISPLAY_RANGES
        ));
    }
}
