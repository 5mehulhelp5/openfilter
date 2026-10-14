<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Search\Strategy;

use Iranimij\OpenFilter\Api\FacetCountStrategyInterface;
use Iranimij\OpenFilter\Model\Search\QueryClauses;

/**
 * Disjunctive counts in one request: active facet filters move from the query to post_filter (they still narrow
 * the hits), and each aggregation is wrapped in a filter aggregation holding every active facet filter except
 * the ones on its own field.
 */
class SingleQuery implements FacetCountStrategyInterface
{
    public const CODE = 'single_query';

    /** Key of the original aggregation inside the wrapping filter aggregation. */
    public const INNER = 'of_inner';

    /**
     * @param QueryClauses $clauses
     */
    public function __construct(
        private readonly QueryClauses $clauses
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return self::CODE;
    }

    /**
     * @inheritDoc
     */
    public function prepareQuery(array $query, array $facetFilters): array
    {
        if ($facetFilters === []) {
            return $query;
        }
        $prepared = $this->clauses->without($query, $facetFilters);
        if ($prepared === null) {
            return $query;
        }
        $prepared['body']['post_filter'] = ['bool' => ['must' => $this->clauses->clauses($facetFilters)]];

        $fieldByBucket = [];
        foreach ($facetFilters as $filter) {
            $fieldByBucket[$filter->getBucketName()] = $filter->getField();
        }
        foreach ($prepared['body']['aggregations'] ?? [] as $name => $aggregation) {
            $others = $this->clauses->clauses($facetFilters, $fieldByBucket[$name] ?? null);
            if ($others === []) {
                continue;
            }
            $prepared['body']['aggregations'][$name] = [
                'filter' => ['bool' => ['must' => $others]],
                'aggs' => [self::INNER => $aggregation],
            ];
        }

        return $prepared;
    }

    /**
     * @inheritDoc
     */
    public function prepareResponse(array $query, array $response, array $facetFilters): array
    {
        foreach ($response['aggregations'] ?? [] as $name => $aggregation) {
            if (is_array($aggregation) && isset($aggregation[self::INNER])) {
                $response['aggregations'][$name] = $aggregation[self::INNER];
            }
        }

        return $response;
    }
}
