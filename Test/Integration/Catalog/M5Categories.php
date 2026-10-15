<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Catalog;

use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * A small tree below M1's category for the 1.1 category tree: Tops (with Tees) and Bottoms, anchors. Products keep
 * M1's category, so every M1 count stays as it was. Simple product i: i % 3 = 0 → Tops (i % 6 = 0 → Tees as well),
 * i % 3 = 1 → Bottoms, i % 3 = 2 → M1's category only; configurables 0 and 1 → Tees.
 */
class M5Categories
{
    public const TOPS = 'Ofmfive Tops';
    public const TEES = 'Ofmfive Tees';
    public const BOTTOMS = 'Ofmfive Bottoms';

    /**
     * @param int $m1Category
     * @return array<string, int> category name => id
     */
    public static function create(int $m1Category): array
    {
        $tops = self::category(self::TOPS, 'of-m5-tops', $m1Category, 1);
        $ids = [
            self::TOPS => $tops,
            self::TEES => self::category(self::TEES, 'of-m5-tees', $tops, 1),
            self::BOTTOMS => self::category(self::BOTTOMS, 'of-m5-bottoms', $m1Category, 2),
        ];
        $om = Bootstrap::getObjectManager();
        $links = $om->get(CategoryLinkManagementInterface::class);
        $products = $om->get(ProductRepositoryInterface::class);
        foreach (self::membership() as $sku => $names) {
            $product = $products->get($sku);
            $current = array_map('intval', (array) $product->getCategoryIds());
            $wanted = array_values(array_unique(array_merge($current, array_map(static fn (string $n): int => $ids[$n], $names))));
            sort($wanted);
            sort($current);
            if ($wanted !== $current) {
                $links->assignProductToCategories($sku, $wanted);
            }
        }
        foreach (['catalog_category_product', 'catalog_product_category', 'catalogsearch_fulltext'] as $indexer) {
            $om->get(IndexerRegistry::class)->get($indexer)->reindexAll();
        }

        return $ids;
    }

    /**
     * Categories each product of M1's category is assigned to (besides M1's), anchors not expanded.
     *
     * @return array<string, string[]> by SKU
     */
    public static function membership(): array
    {
        $membership = [];
        for ($i = 0; $i < 24; $i++) {
            $membership[M1Catalog::SKU . $i] = match ($i % 3) {
                0 => $i % 6 === 0 ? [self::TOPS, self::TEES] : [self::TOPS],
                1 => [self::BOTTOMS],
                default => [],
            };
        }
        $membership[M1Catalog::SKU . 'c0'] = [self::TEES];
        $membership[M1Catalog::SKU . 'c1'] = [self::TEES];

        return $membership;
    }

    /**
     * Whether a product is in a category or below it (anchors: Tops shows the products of Tees).
     *
     * @param string $sku
     * @param string $name
     * @return bool
     */
    public static function inTree(string $sku, string $name): bool
    {
        $names = self::membership()[$sku] ?? [];

        return in_array($name, $names, true) || ($name === self::TOPS && in_array(self::TEES, $names, true));
    }

    /**
     * @param string $name
     * @param string $urlKey
     * @param int $parent
     * @param int $position
     * @return int
     */
    private static function category(string $name, string $urlKey, int $parent, int $position): int
    {
        $om = Bootstrap::getObjectManager();
        $existing = $om->get(CollectionFactory::class)->create()->addAttributeToFilter('url_key', $urlKey)->getFirstItem();
        if ($existing->getId()) {
            return (int) $existing->getId();
        }
        $category = $om->get(CategoryInterfaceFactory::class)->create();
        $category->setName($name)->setParentId($parent)->setIsActive(true)->setIsAnchor(true)->setUrlKey($urlKey)
            ->setPosition($position)->setStoreId(0);

        return (int) $om->get(CategoryRepositoryInterface::class)->save($category)->getId();
    }
}
