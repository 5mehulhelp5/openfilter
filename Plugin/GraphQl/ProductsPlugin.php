<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Plugin\GraphQl;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\GraphQl\RangeFilters;
use Iranimij\OpenFilter\Model\GraphQl\RequestState;
use Magento\CatalogGraphQl\Model\Resolver\Products;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * A `products` field that selects `openfilter_aggregations` runs its search as a layered navigation request
 * (disjunctive counts, "all of" filters, range lists in the same request); the result carries the `filter`
 * argument and the engine's aggregations for OpenFilter's resolver. Other queries are left alone.
 */
class ProductsPlugin
{
    public const RESULT_KEY = 'openfilter';

    /**
     * @param Config $config
     * @param RequestState $state
     * @param RangeFilters $ranges
     */
    public function __construct(
        private readonly Config $config,
        private readonly RequestState $state,
        private readonly RangeFilters $ranges
    ) {
    }

    /**
     * @param Products $subject
     * @param callable $proceed
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array<string, mixed>|null $value
     * @param array<string, mixed>|null $args
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundResolve(
        Products $subject,
        callable $proceed,
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $storeId = (int) $context->getExtensionAttributes()->getStore()->getId();
        if (!isset($info->getFieldSelection(1)['openfilter_aggregations']) || !$this->config->isEnabled($storeId)) {
            return $proceed($field, $context, $info, $value, $args);
        }
        $filterArgs = (array) ($args['filter'] ?? []);
        $this->ranges->register($storeId, $filterArgs);
        $this->state->start();
        try {
            $result = $proceed($field, $context, $info, $value, $args);
        } finally {
            $aggregations = $this->state->finish();
        }
        if (is_array($result)) {
            $result[self::RESULT_KEY] = ['filter' => $filterArgs, 'aggregations' => $aggregations];
        }

        return $result;
    }
}
