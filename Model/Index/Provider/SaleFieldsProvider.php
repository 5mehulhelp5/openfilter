<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Index\Provider;

use Iranimij\OpenFilter\Model\ResourceModel\BuiltinDataSource;
use Iranimij\OpenFilter\Model\Index\Fields;
use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;

/**
 * of_on_sale_<group>_<website> = 1 where the price index's final price is below the regular price (special price,
 * catalog rule, tier price for one piece), per customer group of the store view's website.
 */
class SaleFieldsProvider implements AdditionalFieldsProviderInterface
{
    private const EPSILON = 0.0001;

    /**
     * @param BuiltinDataSource $source
     * @param Fields $fields
     */
    public function __construct(
        private readonly BuiltinDataSource $source,
        private readonly Fields $fields
    ) {
    }

    /**
     * @param int[] $productIds
     * @param int $storeId
     * @return array<int, array<string, int>>
     */
    public function getFields(array $productIds, $storeId)
    {
        $websiteId = $this->source->websiteId((int) $storeId);
        $result = [];
        foreach ($this->source->prices($productIds, (int) $storeId) as $productId => $groups) {
            foreach ($groups as $groupId => $prices) {
                if ($prices['final_price'] < $prices['price'] - self::EPSILON) {
                    $result[$productId][$this->fields->onSale($groupId, $websiteId)] = 1;
                }
            }
        }

        return $result;
    }
}
