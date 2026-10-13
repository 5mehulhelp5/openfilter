<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Search;

use Iranimij\OpenFilter\Api\Data\FacetFilterInterface;
use Magento\Elasticsearch\SearchAdapter\Filter\Builder as FilterBuilder;
use Magento\Framework\Search\Request\Filter\Term;
use Magento\Framework\Search\Request\Query\BoolExpression;
use Magento\Framework\Search\Request\Query\Filter as FilterQuery;
use Magento\Framework\Search\Request\QueryInterface;
use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Request\BucketInterface;

/**
 * Finds the active term filters of a layered navigation request whose field also has a term aggregation.
 */
class FacetFilterCollector
{
    /**
     * @param FilterBuilder $filterBuilder
     * @param string[] $requestNames search requests that render layered navigation
     * @param string[] $excludedFields fields that stay conjunctive (category drill-down, visibility)
     */
    public function __construct(
        private readonly FilterBuilder $filterBuilder,
        private readonly array $requestNames = ['catalog_view_container', 'quick_search_container'],
        private readonly array $excludedFields = ['category_ids', 'visibility']
    ) {
    }

    /**
     * @param RequestInterface $request
     * @return FacetFilterInterface[]
     */
    public function collect(RequestInterface $request): array
    {
        if (!in_array($request->getName(), $this->requestNames, true)) {
            return [];
        }
        $buckets = [];
        foreach ($request->getAggregation() as $bucket) {
            if ($bucket->getType() === BucketInterface::TYPE_TERM) {
                $buckets[$bucket->getField()] = $bucket->getName();
            }
        }
        $filters = [];
        foreach ($this->activeTermFilters($request->getQuery()) as $term) {
            $field = (string) $term->getField();
            if (!isset($buckets[$field]) || in_array($field, $this->excludedFields, true)) {
                continue;
            }
            $built = $this->filterBuilder->build($term, BoolExpression::QUERY_CONDITION_MUST);
            $filters[] = new FacetFilter($field, $buckets[$field], array_values($built['bool']['must'] ?? []));
        }

        return $filters;
    }

    /**
     * Term filters reachable through must conditions only (should / must_not filters are not facet selections).
     *
     * @param QueryInterface $query
     * @return Term[]
     */
    private function activeTermFilters(QueryInterface $query): array
    {
        if ($query instanceof BoolExpression) {
            $terms = [];
            foreach ($query->getMust() as $must) {
                array_push($terms, ...$this->activeTermFilters($must));
            }

            return $terms;
        }
        if ($query instanceof FilterQuery) {
            $reference = $query->getReference();
            if ($query->getReferenceType() === FilterQuery::REFERENCE_QUERY && $reference instanceof QueryInterface) {
                return $this->activeTermFilters($reference);
            }
            if ($reference instanceof Term) {
                return [$reference];
            }
        }

        return [];
    }
}
