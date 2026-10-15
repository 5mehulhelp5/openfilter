<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Plugin\Search;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Search\AndLogic;
use Iranimij\OpenFilter\Model\Search\BuiltinFilters;
use Iranimij\OpenFilter\Model\Search\CategoryPicks;
use Iranimij\OpenFilter\Model\Search\FacetFilter;
use Iranimij\OpenFilter\Model\Search\FacetFilterCollector;
use Iranimij\OpenFilter\Model\Search\QueryState;
use Iranimij\OpenFilter\Model\Search\RangeListQuery;
use Iranimij\OpenFilter\Model\Search\StrategyResolver;
use Magento\Elasticsearch\ElasticAdapter\SearchAdapter\Mapper;
use Magento\Framework\Search\RequestInterface;

/**
 * Lets the facet count strategy rewrite the engine query of layered navigation requests, after adding the built-in
 * filters (rating, stock, sale, new) as clauses and count aggregations and the "all of" clauses of attributes set to
 * "Products must match all" (those stay out of the disjunctive counts). The mapper is shared by the OpenSearch and
 * Elasticsearch 8 adapters on Magento 2.4.7 to 2.4.9.
 */
class MapperPlugin
{
    /**
     * @param Config $config
     * @param FacetFilterCollector $collector
     * @param StrategyResolver $resolver
     * @param QueryState $state
     * @param BuiltinFilters $builtins
     * @param AndLogic $andLogic attributes set to "all of the picked values"
     * @param RangeListQuery $rangeLists filters shown as a list of ranges
     * @param CategoryPicks $categoryPicks categories picked in the category tree
     */
    public function __construct(
        private readonly Config $config,
        private readonly FacetFilterCollector $collector,
        private readonly StrategyResolver $resolver,
        private readonly QueryState $state,
        private readonly BuiltinFilters $builtins,
        private readonly AndLogic $andLogic,
        private readonly RangeListQuery $rangeLists,
        private readonly CategoryPicks $categoryPicks
    ) {
    }

    /**
     * @param Mapper $subject
     * @param array<string, mixed> $query
     * @param RequestInterface $request
     * @return array<string, mixed>
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterBuildQuery(Mapper $subject, $query, RequestInterface $request)
    {
        if (!is_array($query) || !$this->config->isEnabled() || !$this->collector->isLayeredRequest($request)) {
            return $query;
        }
        [$filters, $allOf] = $this->andLogic->split($this->collector->collect($request));
        foreach ($allOf as $clause) {
            $query['body']['query']['bool']['must'][] = $clause;
        }
        [$query, $filters] = $this->rangeLists->apply($query, $filters);
        [$query, $filters] = $this->categoryPicks->apply($query, $filters);
        // Built-ins depend on the shopper's session (customer group), which GraphQL does not use.
        $builtins = $this->collector->isGraphQlRequest($request) ? [] : $this->builtins->forRequest();
        foreach ($builtins as $builtin) {
            $query['body']['aggregations'][$builtin['bucket']] = $builtin['aggregation'];
            if ($builtin['clause'] !== null) {
                $query['body']['query']['bool']['must'][] = $builtin['clause'];
                $filters[] = new FacetFilter($builtin['field'], $builtin['bucket'], [$builtin['clause']]);
            }
        }
        if ($filters === []) {
            return $query;
        }
        $strategy = $this->resolver->resolve();
        $prepared = $strategy->prepareQuery($query, $filters);
        $this->state->remember($request, $query, $prepared, $filters, $strategy);

        return $prepared;
    }
}
