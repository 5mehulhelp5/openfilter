<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Plugin\Search;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Search\FacetFilterCollector;
use Iranimij\OpenFilter\Model\Search\QueryState;
use Iranimij\OpenFilter\Model\Search\StrategyResolver;
use Magento\Elasticsearch\ElasticAdapter\SearchAdapter\Mapper;
use Magento\Framework\Search\RequestInterface;

/**
 * Lets the facet count strategy rewrite the engine query of layered navigation requests. The mapper is shared by
 * the OpenSearch and Elasticsearch 8 adapters on Magento 2.4.7 to 2.4.9.
 */
class MapperPlugin
{
    /**
     * @param Config $config
     * @param FacetFilterCollector $collector
     * @param StrategyResolver $resolver
     * @param QueryState $state
     */
    public function __construct(
        private readonly Config $config,
        private readonly FacetFilterCollector $collector,
        private readonly StrategyResolver $resolver,
        private readonly QueryState $state
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
        if (!is_array($query) || !$this->config->isEnabled()) {
            return $query;
        }
        $filters = $this->collector->collect($request);
        if ($filters === []) {
            return $query;
        }
        $strategy = $this->resolver->resolve();
        $prepared = $strategy->prepareQuery($query, $filters);
        $this->state->remember($request, $query, $prepared, $filters, $strategy);

        return $prepared;
    }
}
