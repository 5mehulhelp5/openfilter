<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Catalog;

use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterfaceFactory;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\TestFramework\Helper\Bootstrap;

/**
 * Catalog for the built-in filters: 12 products in their own category, price 10 + 5i.
 * Rating: i % 4 = 0 → 100, 1 → 80, 2 → 50, 3 → no reviews. Out of stock: 5 and 11.
 * Sale: i % 3 = 0 special price −30 % (every group); i % 3 = 1 tier price −20 % for Wholesale only.
 * New: 0, 2, 4, 6 from yesterday to tomorrow; 1 from tomorrow (future); 3 ended ten days ago.
 */
class M1Builtins
{
    public const SKU = 'of-m1b-';
    public const COUNT = 12;
    public const OUT_OF_STOCK = [5, 11];
    public const WHOLESALE = 2;

    /**
     * @return array{category: int, ids: array<int, int>}
     */
    public static function create(): array
    {
        $om = Bootstrap::getObjectManager();
        $category = self::category();
        $repository = $om->get(ProductRepositoryInterface::class);
        $ids = [];
        for ($i = 0; $i < self::COUNT; $i++) {
            try {
                $ids[$i] = (int) $repository->get(self::SKU . $i)->getId();
                continue;
            } catch (NoSuchEntityException) {
                // create below
            }
            $price = 10.0 + 5 * $i;
            $inStock = !in_array($i, self::OUT_OF_STOCK, true);
            /** @var Product $product */
            $product = $om->get(ProductInterfaceFactory::class)->create();
            $product->setTypeId('simple')->setAttributeSetId(4)->setSku(self::SKU . $i)->setName('Ofmtwo built-in ' . $i)
                ->setPrice($price)->setVisibility(Visibility::VISIBILITY_BOTH)->setStatus(Status::STATUS_ENABLED)
                ->setWebsiteIds([1])->setCategoryIds([$category])
                ->setStockData(['use_config_manage_stock' => 1, 'qty' => $inStock ? 100 : 0, 'is_in_stock' => (int) $inStock]);
            if ($i % 3 === 0) {
                $product->setData('special_price', $price * 0.7);
            } elseif ($i % 3 === 1) {
                $product->setData('tier_price', [[
                    'website_id' => 0, 'cust_group' => self::WHOLESALE, 'price_qty' => 1, 'price' => $price * 0.8,
                ]]);
            }
            [$from, $to] = self::news($i);
            if ($from !== null) {
                $product->setData('news_from_date', $from);
            }
            if ($to !== null) {
                $product->setData('news_to_date', $to);
            }
            $ids[$i] = (int) $repository->save($product)->getId();
        }
        self::ratings($ids);
        self::reindex();

        return ['category' => $category, 'ids' => $ids];
    }

    /**
     * Re-indexes what the built-in filters read (after configuration changes such as "display out of stock").
     *
     * @return void
     */
    public static function reindex(): void
    {
        $om = Bootstrap::getObjectManager();
        SearchIndexReset::forNewAttributes();
        foreach (['catalog_category_product', 'catalog_product_price', 'cataloginventory_stock', 'catalogsearch_fulltext'] as $indexer) {
            $om->get(IndexerRegistry::class)->get($indexer)->reindexAll();
        }
    }

    /**
     * The engine-independent expectation per product index.
     *
     * @return array<int, array{rating: int, inStock: bool, saleAll: bool, saleWholesale: bool, new: bool}>
     */
    public static function model(): array
    {
        $model = [];
        for ($i = 0; $i < self::COUNT; $i++) {
            $model[$i] = [
                'rating' => [100, 80, 50, 0][$i % 4],
                'inStock' => !in_array($i, self::OUT_OF_STOCK, true),
                'saleAll' => $i % 3 === 0,
                'saleWholesale' => $i % 3 !== 2,
                'new' => in_array($i, [0, 2, 4, 6], true),
            ];
        }

        return $model;
    }

    /**
     * @param int $i
     * @return array{?string, ?string}
     */
    private static function news(int $i): array
    {
        $day = static fn (int $offset): string => date('Y-m-d', strtotime(sprintf('%+d day', $offset)));

        return match (true) {
            in_array($i, [0, 2, 4, 6], true) => [$day(-1), $day(1)],
            $i === 1 => [$day(1), null],
            $i === 3 => [$day(-20), $day(-10)],
            default => [null, null],
        };
    }

    /**
     * Review summaries for the default store view, written directly (no votes needed for the filter).
     *
     * @param array<int, int> $ids
     * @return void
     */
    private static function ratings(array $ids): void
    {
        $connection = Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
        $table = $connection->getTableName('review_entity_summary');
        foreach ($ids as $i => $id) {
            $rating = [100, 80, 50, 0][$i % 4];
            $connection->delete($table, ['entity_pk_value = ?' => $id, 'entity_type = ?' => 1]);
            if ($rating > 0) {
                $connection->insert($table, [
                    'entity_pk_value' => $id, 'entity_type' => 1, 'reviews_count' => 1,
                    'rating_summary' => $rating, 'store_id' => 1,
                ]);
            }
        }
    }

    /**
     * @return int
     */
    private static function category(): int
    {
        $om = Bootstrap::getObjectManager();
        $existing = $om->get(\Magento\Catalog\Model\ResourceModel\Category\CollectionFactory::class)->create()
            ->addAttributeToFilter('url_key', 'of-m1-builtins')->getFirstItem();
        if ($existing->getId()) {
            return (int) $existing->getId();
        }
        $category = $om->get(CategoryInterfaceFactory::class)->create();
        $category->setName('OpenFilter M1 built-ins')->setParentId(2)->setIsActive(true)->setIsAnchor(true)
            ->setUrlKey('of-m1-builtins')->setStoreId(0);

        return (int) $om->get(CategoryRepositoryInterface::class)->save($category)->getId();
    }
}
