<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Plugin\Search;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\GraphQl\RequestState;
use Iranimij\OpenFilter\Model\Search\BuiltinClauses;
use Iranimij\OpenFilter\Model\Search\BuiltinCounts;
use Iranimij\OpenFilter\Model\Search\FacetFilterCollector;
use Iranimij\OpenFilter\Model\Search\QueryState;
use Iranimij\OpenFilter\Model\Search\RangeLists;
use Magento\Elasticsearch\SearchAdapter\Aggregation\Builder;
use Magento\Elasticsearch\SearchAdapter\QueryContainer;
use Magento\Elasticsearch\SearchAdapter\QueryContainerFactory;
use Magento\Framework\Search\RequestInterface;

/**
 * Hands Magento's aggregation builder disjunctive counts (and its price-interval data provider the original query),
 * and marks layered responses so dynamic buckets come from the in-request stats instead of extra requests.
 */
class AggregationBuilderPlugin
{
    /**
     * @param QueryState $state
     * @param QueryContainerFactory $queryContainerFactory
     * @param FacetFilterCollector $collector
     * @param Config $config
     * @param BuiltinClauses $builtinClauses
     * @param BuiltinCounts $builtinCounts
     * @param RangeLists $rangeLists counts of filters shown as a list of ranges
     * @param RequestState $graphQlState GraphQL searches asking for `openfilter_aggregations` (1.1)
     */
    public function __construct(
        private readonly QueryState $state,
        private readonly QueryContainerFactory $queryContainerFactory,
        private readonly FacetFilterCollector $collector,
        private readonly Config $config,
        private readonly BuiltinClauses $builtinClauses,
        private readonly BuiltinCounts $builtinCounts,
        private readonly RangeLists $rangeLists,
        private readonly RequestState $graphQlState
    ) {
    }

    /**
     * Core's dynamic (price) buckets re-run the query; give them the query core would have sent.
     *
     * @param Builder $subject
     * @param QueryContainer $container
     * @return array{QueryContainer}|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSetQuery(Builder $subject, QueryContainer $container): ?array
    {
        $original = $this->state->originalOf($container->getQuery());

        return $original === null ? null : [$this->queryContainerFactory->create(['query' => $original])];
    }

    /**
     * Disjunctive counts in, layered responses marked while core builds the buckets.
     *
     * @param Builder $subject
     * @param callable $proceed
     * @param RequestInterface $request
     * @param array<string, mixed> $queryResult
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundBuild(Builder $subject, callable $proceed, RequestInterface $request, array $queryResult)
    {
        $state = $this->state->take($request);
        if ($state !== null) {
            $queryResult = $state['strategy']->prepareResponse($state['prepared'], $queryResult, $state['filters']);
        }
        $layered = $this->collector->isLayeredRequest($request) && $this->config->isEnabled();
        $graphQl = $layered && $this->collector->isGraphQlRequest($request);
        $aggregations = (array) ($queryResult['aggregations'] ?? []);
        if ($layered) {
            $this->rangeLists->collect($aggregations);
        }
        if ($graphQl) {
            // Magento's GraphQL `aggregations` keep their price intervals; OpenFilter's field reads the stats.
            $this->graphQlState->keep($aggregations);
        } elseif ($layered) {
            $this->builtinCounts->set($this->builtinClauses->counts($aggregations));
        }
        $this->state->setHandling($layered && !$graphQl);
        try {
            return $proceed($request, $queryResult);
        } finally {
            $this->state->setHandling(false);
        }
    }
}
