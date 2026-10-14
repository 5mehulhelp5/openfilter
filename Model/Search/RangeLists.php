<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Search;

/**
 * Request-scoped hand-over for filters shown as a list of ranges (1.1): the layer filter registers its field before
 * the search runs (picked ranges and the ranges to count, in index units), the mapper turns that into a clause and
 * an aggregation of the same search request, and the aggregation builder hands the counts back to the filter.
 */
class RangeLists
{
    /** Aggregation name prefix; the rest is the request field ("of_ranges_price"). */
    public const BUCKET_PREFIX = 'of_ranges_';

    /**
     * @var array<string, array{selected: array<int, array{?float, ?float}>, ranges: ?array<int, array{?float, ?float}>,
     *     interval: float}>
     */
    private array $lists = [];

    /** @var array<string, array<int, array<string, mixed>>> raw buckets by request field */
    private array $counts = [];

    /**
     * @param string $field request field (price, or a decimal attribute code)
     * @param array<int, array{?float, ?float}> $selected picked [from, to) ranges in index units
     * @param array<int, array{?float, ?float}>|null $ranges ranges to count in index units, null = automatic
     * @param float $interval index units per display unit (automatic ranges count in display units)
     * @return void
     */
    public function register(string $field, array $selected, ?array $ranges, float $interval): void
    {
        $this->lists[$field] = ['selected' => $selected, 'ranges' => $ranges, 'interval' => $interval];
        unset($this->counts[$field]);
    }

    /**
     * Registrations for the next search request; they are handed over once.
     *
     * @return array<string, array{selected: array<int, array{?float, ?float}>,
     *     ranges: ?array<int, array{?float, ?float}>, interval: float}>
     */
    public function take(): array
    {
        $lists = $this->lists;
        $this->lists = [];

        return $lists;
    }

    /**
     * Reads the range aggregations of a search response.
     *
     * @param array<string, mixed> $aggregations
     * @return void
     */
    public function collect(array $aggregations): void
    {
        foreach ($aggregations as $name => $aggregation) {
            if (is_string($name) && str_starts_with($name, self::BUCKET_PREFIX) && is_array($aggregation)) {
                $this->counts[substr($name, strlen(self::BUCKET_PREFIX))] = array_values((array) ($aggregation['buckets'] ?? []));
            }
        }
    }

    /**
     * Raw buckets of a field's range aggregation (range: in the registered order; histogram: by key), or null.
     *
     * @param string $field
     * @return array<int, array<string, mixed>>|null
     */
    public function counts(string $field): ?array
    {
        return $this->counts[$field] ?? null;
    }
}
