<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Api;

use Iranimij\OpenFilter\Api\Data\FacetFilterInterface;

/**
 * Turns Magento's conjunctive facet counts (every aggregation under every active filter) into disjunctive counts
 * (an attribute's aggregation ignores that attribute's own filter), so multi-select filters show real numbers.
 *
 * Works on the engine query Magento's search adapter mapped (OpenSearch / Elasticsearch JSON) and on the raw
 * engine response, before Magento turns it into aggregation buckets.
 *
 * @api
 */
interface FacetCountStrategyInterface
{
    /**
     * Strategy code, e.g. "single_query" or "per_attribute".
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * Rewrites the mapped query before it is sent.
     *
     * @param array<string, mixed> $query
     * @param FacetFilterInterface[] $facetFilters
     * @return array<string, mixed>
     */
    public function prepareQuery(array $query, array $facetFilters): array;

    /**
     * Returns the raw response with disjunctive counts in its aggregations, in the shape Magento expects.
     *
     * @param array<string, mixed> $query the query prepareQuery() returned
     * @param array<string, mixed> $response raw engine response
     * @param FacetFilterInterface[] $facetFilters
     * @return array<string, mixed>
     */
    public function prepareResponse(array $query, array $response, array $facetFilters): array;
}
