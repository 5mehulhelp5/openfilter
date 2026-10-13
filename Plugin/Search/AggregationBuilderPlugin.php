<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Plugin\Search;

use Iranimij\OpenFilter\Model\Search\QueryState;
use Magento\Elasticsearch\SearchAdapter\Aggregation\Builder;
use Magento\Elasticsearch\SearchAdapter\QueryContainer;
use Magento\Elasticsearch\SearchAdapter\QueryContainerFactory;
use Magento\Framework\Search\RequestInterface;

/**
 * Hands Magento's aggregation builder disjunctive counts, and its price-interval data provider the original query.
 */
class AggregationBuilderPlugin
{
    /**
     * @param QueryState $state
     * @param QueryContainerFactory $queryContainerFactory
     */
    public function __construct(
        private readonly QueryState $state,
        private readonly QueryContainerFactory $queryContainerFactory
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
     * @param Builder $subject
     * @param RequestInterface $request
     * @param array<string, mixed> $queryResult
     * @return array{RequestInterface, array<string, mixed>}|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeBuild(Builder $subject, RequestInterface $request, array $queryResult): ?array
    {
        $state = $this->state->take($request);
        if ($state === null) {
            return null;
        }

        return [
            $request,
            $state['strategy']->prepareResponse($state['prepared'], $queryResult, $state['filters']),
        ];
    }
}
