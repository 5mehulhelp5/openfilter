<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Index\Provider;

use Iranimij\OpenFilter\Model\Index\BuiltinDataSource;
use Iranimij\OpenFilter\Model\Index\Fields;
use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;

/**
 * of_rating: review summary 0–100 (products without reviews get no value, so no rating filter matches them).
 */
class RatingFieldsProvider implements AdditionalFieldsProviderInterface
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
        $fields = [];
        foreach ($this->source->ratings($productIds, (int) $storeId) as $productId => $rating) {
            if ($rating > 0) {
                $fields[$productId] = [Fields::RATING => $rating];
            }
        }

        return $fields;
    }
}
