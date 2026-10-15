<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Resolver;

use Iranimij\OpenFilter\Model\GraphQl\AggregationsBuilder;
use Iranimij\OpenFilter\Plugin\GraphQl\ProductsPlugin;
use Magento\CatalogGraphQl\Model\Resolver\Products\SearchResult;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;

/**
 * `products { openfilter_aggregations }` (1.1). Empty when OpenFilter is off in the store view or nothing was found.
 */
class Aggregations implements ResolverInterface
{
    /**
     * @param AggregationsBuilder $builder
     */
    public function __construct(
        private readonly AggregationsBuilder $builder
    ) {
    }

    /**
     * @param Field $field
     * @param ContextInterface $context
     * @param ResolveInfo $info
     * @param array<string, mixed>|null $value the `products` result
     * @param array<string, mixed>|null $args
     * @return array<int, array<string, mixed>>
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $search = $value['search_result'] ?? null;
        $openFilter = $value[ProductsPlugin::RESULT_KEY] ?? null;
        if (!$search instanceof SearchResult || !is_array($openFilter) || (int) ($value['total_count'] ?? 0) === 0) {
            return [];
        }

        return $this->builder->build(
            $search->getSearchAggregation(),
            (array) $openFilter['aggregations'],
            (array) $openFilter['filter'],
            (int) $context->getExtensionAttributes()->getStore()->getId()
        );
    }
}
