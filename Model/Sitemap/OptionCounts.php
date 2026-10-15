<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Sitemap;

use Magento\Catalog\Model\Layer\CategoryFactory as CategoryLayerFactory;

/**
 * Products per option on a category page, as shoppers see it (one search request per category; the counts of every
 * filter come from the same request).
 */
class OptionCounts
{
    /**
     * @param CategoryLayerFactory $layerFactory
     * @param CategoryCollectionFactory $collectionFactory
     */
    public function __construct(
        private readonly CategoryLayerFactory $layerFactory,
        private readonly CategoryCollectionFactory $collectionFactory
    ) {
    }

    /**
     * @param int $categoryId
     * @param string[] $codes attribute codes
     * @return array<string, array<int, int>> code => option id => products
     */
    public function forCategory(int $categoryId, array $codes): array
    {
        $layer = $this->layerFactory->create();
        $layer->setCurrentCategory($categoryId);
        $collection = $this->collectionFactory->create();
        $collection->addCategoryFilter($layer->getCurrentCategory());
        $layer->prepareProductCollection($collection);
        $counts = [];
        foreach ($codes as $code) {
            foreach ($collection->getFacetedData($code) as $optionId => $data) {
                $count = (int) ($data['count'] ?? 0);
                if ($count > 0) {
                    $counts[$code][(int) $optionId] = $count;
                }
            }
        }

        return $counts;
    }
}
