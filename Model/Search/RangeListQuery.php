<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Search;

use Iranimij\OpenFilter\Api\Data\FacetFilterInterface;

/**
 * Filters shown as a list of ranges (1.1) in the engine query: picked ranges become one "any of" clause of
 * [from, to) ranges in place of core's single range, and the ranges to show are counted by an aggregation of the same
 * request (a range aggregation for the merchant's list, a histogram in display units for automatic ranges), which
 * counts disjunctively like every other facet.
 */
class RangeListQuery
{
    /**
     * @param RangeLists $lists
     */
    public function __construct(
        private readonly RangeLists $lists
    ) {
    }

    /**
     * @param array<string, mixed> $query engine query (body.query.bool.must, body.aggregations)
     * @param FacetFilterInterface[] $filters active facet filters
     * @return array{array<string, mixed>, FacetFilterInterface[]}
     */
    public function apply(array $query, array $filters): array
    {
        foreach ($this->lists->take() as $field => $list) {
            $engineField = $this->engineField($query, $field);
            if ($engineField === null) {
                continue;
            }
            $bucket = RangeLists::BUCKET_PREFIX . $field;
            $query['body']['aggregations'][$bucket] = $this->aggregation($engineField, $list['ranges'], $list['interval']);
            foreach ($filters as $index => $filter) {
                if ($filter->getField() !== $field || $list['selected'] === []) {
                    continue;
                }
                $clause = ['bool' => ['should' => array_map(
                    fn (array $range): array => $this->range($engineField, $range),
                    $list['selected']
                ), 'minimum_should_match' => 1]];
                $must = $query['body']['query']['bool']['must'] ?? [];
                foreach ($filter->getClauses() as $old) {
                    $position = array_search($old, $must, true);
                    if ($position !== false) {
                        unset($must[$position]);
                    }
                }
                $must[] = $clause;
                $query['body']['query']['bool']['must'] = array_values($must);
                $filters[$index] = new FacetFilter($field, $filter->getBucketName(), [$clause]);
                $filters[] = new FacetFilter($field, $bucket, []);
            }
        }

        return [$query, array_values($filters)];
    }

    /**
     * The engine field behind a request field, read from core's aggregation for it ("<field>_bucket").
     *
     * @param array<string, mixed> $query
     * @param string $field
     * @return string|null
     */
    private function engineField(array $query, string $field): ?string
    {
        $aggregation = $query['body']['aggregations'][$field . '_bucket'] ?? null;
        if (!is_array($aggregation)) {
            return null;
        }
        foreach ($aggregation as $definition) {
            if (is_array($definition) && isset($definition['field']) && is_string($definition['field'])) {
                return $definition['field'];
            }
        }

        return null;
    }

    /**
     * @param string $engineField
     * @param array<int, array{?float, ?float}>|null $ranges
     * @param float $interval
     * @return array<string, mixed>
     */
    private function aggregation(string $engineField, ?array $ranges, float $interval): array
    {
        if ($ranges === null) {
            // Buckets one display unit wide, shifted a thousandth down so a value on a round bound (50.00 after
            // currency and tax conversion) lands in the bucket that starts there, as the [from, to) filter does.
            return ['histogram' => [
                'field' => $engineField,
                'interval' => $interval,
                'offset' => $interval - $interval / 1000,
                'min_doc_count' => 1,
            ]];
        }

        return ['range' => ['field' => $engineField, 'ranges' => array_map(
            static fn (array $range): array => array_filter(
                ['from' => $range[0], 'to' => $range[1]],
                static fn (?float $bound): bool => $bound !== null
            ),
            $ranges
        )]];
    }

    /**
     * @param string $engineField
     * @param array{?float, ?float} $range
     * @return array<string, mixed>
     */
    private function range(string $engineField, array $range): array
    {
        return ['range' => [$engineField => array_filter(
            ['gte' => $range[0], 'lt' => $range[1]],
            static fn (?float $bound): bool => $bound !== null
        )]];
    }
}
