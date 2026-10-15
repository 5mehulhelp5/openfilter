<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Plugin\GraphQl;

use Iranimij\OpenFilter\Model\GraphQl\RequestState;
use Magento\CatalogGraphQl\DataProvider\Product\SearchCriteriaBuilder;

/**
 * Magento asks the engine for aggregations only when a query selects `aggregations` or `filters`; OpenFilter's
 * field needs them too.
 */
class SearchCriteriaPlugin
{
    /**
     * @param RequestState $state
     */
    public function __construct(
        private readonly RequestState $state
    ) {
    }

    /**
     * @param SearchCriteriaBuilder $subject
     * @param array<string, mixed> $args
     * @param bool $includeAggregation
     * @return array{array<string, mixed>, bool}
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeBuild(SearchCriteriaBuilder $subject, array $args, bool $includeAggregation): array
    {
        return [$args, $includeAggregation || $this->state->isActive()];
    }
}
