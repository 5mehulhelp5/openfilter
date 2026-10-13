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
 * of_in_stock: salable in the website's stock (MSI) or by legacy stock status.
 */
class StockFieldsProvider implements AdditionalFieldsProviderInterface
{
    /**
     * @param BuiltinDataSource $source
     */
    public function __construct(
        private readonly BuiltinDataSource $source
    ) {
    }

    /**
     * @param int[] $productIds
     * @param int $storeId
     * @return array<int, array<string, int>>
     */
    public function getFields(array $productIds, $storeId)
    {
        $salable = $this->source->salable($productIds, (int) $storeId);
        $fields = [];
        foreach ($productIds as $productId) {
            $fields[(int) $productId] = [Fields::IN_STOCK => (int) (($salable[(int) $productId] ?? 0) > 0)];
        }

        return $fields;
    }
}
