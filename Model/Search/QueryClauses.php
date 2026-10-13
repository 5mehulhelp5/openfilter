<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Search;

use Iranimij\OpenFilter\Api\Data\FacetFilterInterface;

/**
 * Moves facet filter clauses out of a mapped query's top-level bool.must.
 */
class QueryClauses
{
    /**
     * Removes the clauses of the given filters from body.query.bool.must.
     *
     * @param array<string, mixed> $query
     * @param FacetFilterInterface[] $filters
     * @return array<string, mixed>|null the query without them, or null when a clause is not found there
     */
    public function without(array $query, array $filters): ?array
    {
        $must = $query['body']['query']['bool']['must'] ?? [];
        if (!is_array($must)) {
            return null;
        }
        foreach ($filters as $filter) {
            foreach ($filter->getClauses() as $clause) {
                $index = array_search($clause, $must, true);
                if ($index === false) {
                    return null;
                }
                unset($must[$index]);
            }
        }
        $query['body']['query']['bool']['must'] = array_values($must);

        return $query;
    }

    /**
     * All clauses of the given filters, except those on the excluded field.
     *
     * @param FacetFilterInterface[] $filters
     * @param string|null $exceptField
     * @return array<int, array<string, mixed>>
     */
    public function clauses(array $filters, ?string $exceptField = null): array
    {
        $clauses = [];
        foreach ($filters as $filter) {
            if ($filter->getField() !== $exceptField) {
                array_push($clauses, ...$filter->getClauses());
            }
        }

        return $clauses;
    }
}
