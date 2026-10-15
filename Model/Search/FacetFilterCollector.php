<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Search;

use Iranimij\OpenFilter\Api\Data\FacetFilterInterface;
use Iranimij\OpenFilter\Model\GraphQl\RequestState;
use Magento\Elasticsearch\SearchAdapter\Filter\Builder as FilterBuilder;
use Magento\Framework\Search\Request\Filter\Range;
use Magento\Framework\Search\Request\Filter\Term;
use Magento\Framework\Search\Request\Query\BoolExpression;
use Magento\Framework\Search\Request\Query\Filter as FilterQuery;
use Magento\Framework\Search\Request\QueryInterface;
use Magento\Framework\Search\RequestInterface;
use Magento\Framework\Search\Request\BucketInterface;

/**
 * Finds the active filters of a layered navigation request that facet counts must ignore per field: term filters
 * on fields with a term aggregation, and range filters (price, decimal sliders) on fields with a dynamic one.
 */
class FacetFilterCollector
{
    /**
     * @param FilterBuilder $filterBuilder
     * @param RequestState $graphQl a GraphQL `products` field asking for `openfilter_aggregations` (1.1)
     * @param string[] $requestNames search requests that render layered navigation
     * @param string[] $excludedFields fields that stay conjunctive (category drill-down, visibility)
     * @param string[] $graphQlRequestNames GraphQL search requests, layered only while `openfilter_aggregations` is asked
     */
    public function __construct(
        private readonly FilterBuilder $filterBuilder,
        private readonly RequestState $graphQl,
        private readonly array $requestNames = ['catalog_view_container', 'quick_search_container'],
        private readonly array $excludedFields = ['category_ids', 'visibility'],
        private readonly array $graphQlRequestNames = ['graphql_product_search_with_aggregation']
    ) {
    }

    /**
     * @param RequestInterface $request
     * @return FacetFilterInterface[]
     */
    public function collect(RequestInterface $request): array
    {
        if (!$this->isLayeredRequest($request)) {
            return [];
        }
        $buckets = [];
        foreach ($request->getAggregation() as $bucket) {
            $type = $bucket->getType();
            if ($type === BucketInterface::TYPE_TERM || $type === BucketInterface::TYPE_DYNAMIC) {
                $buckets[$type][$bucket->getField()] = $bucket->getName();
            }
        }
        $filters = [];
        foreach ($this->activeFilters($request->getQuery()) as $filter) {
            $field = (string) $filter->getField();
            $type = $filter instanceof Range ? BucketInterface::TYPE_DYNAMIC : BucketInterface::TYPE_TERM;
            if (!isset($buckets[$type][$field]) || in_array($field, $this->excludedFields, true)) {
                continue;
            }
            $built = $this->filterBuilder->build($filter, BoolExpression::QUERY_CONDITION_MUST);
            $filters[] = new FacetFilter($field, $buckets[$type][$field], array_values($built['bool']['must'] ?? []));
        }

        return $filters;
    }

    /**
     * Whether a search request renders layered navigation (category and search result pages, and GraphQL product
     * searches that ask for OpenFilter's aggregations).
     *
     * @param RequestInterface $request
     * @return bool
     */
    public function isLayeredRequest(RequestInterface $request): bool
    {
        return in_array($request->getName(), $this->requestNames, true) || $this->isGraphQlRequest($request);
    }

    /**
     * A GraphQL product search that asks for OpenFilter's aggregations (1.1).
     *
     * @param RequestInterface $request
     * @return bool
     */
    public function isGraphQlRequest(RequestInterface $request): bool
    {
        return $this->graphQl->isActive() && in_array($request->getName(), $this->graphQlRequestNames, true);
    }

    /**
     * Term and range filters reachable through must conditions only (should / must_not are not selections).
     *
     * @param QueryInterface $query
     * @return array<int, Term|Range>
     */
    private function activeFilters(QueryInterface $query): array
    {
        if ($query instanceof BoolExpression) {
            $filters = [];
            foreach ($query->getMust() as $must) {
                array_push($filters, ...$this->activeFilters($must));
            }

            return $filters;
        }
        if ($query instanceof FilterQuery) {
            $reference = $query->getReference();
            if ($query->getReferenceType() === FilterQuery::REFERENCE_QUERY && $reference instanceof QueryInterface) {
                return $this->activeFilters($reference);
            }
            if ($reference instanceof Term || $reference instanceof Range) {
                return [$reference];
            }
        }

        return [];
    }
}
