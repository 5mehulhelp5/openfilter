<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Plugin\Search;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Search\BuiltinClauses;
use Iranimij\OpenFilter\Model\Search\BuiltinCounts;
use Iranimij\OpenFilter\Model\Search\FacetFilterCollector;
use Iranimij\OpenFilter\Model\Search\QueryState;
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
     */
    public function __construct(
        private readonly QueryState $state,
        private readonly QueryContainerFactory $queryContainerFactory,
        private readonly FacetFilterCollector $collector,
        private readonly Config $config,
        private readonly BuiltinClauses $builtinClauses,
        private readonly BuiltinCounts $builtinCounts
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
        $handling = $this->collector->isLayeredRequest($request) && $this->config->isEnabled();
        if ($handling) {
            $this->builtinCounts->set($this->builtinClauses->counts((array) ($queryResult['aggregations'] ?? [])));
        }
        $this->state->setHandling($handling);
        try {
            return $proceed($request, $queryResult);
        } finally {
            $this->state->setHandling(false);
        }
    }
}
