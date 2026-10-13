<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Index;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Reads what the built-in filters index: review summaries, salable flags (MSI stock of the website, legacy stock
 * status otherwise), price index rows and news dates. Batched by product ids, like Magento's own providers.
 */
class BuiltinDataSource
{
    private const PRODUCT_ENTITY_TYPE = 1;

    /**
     * @param ResourceConnection $resource
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Rating summary (0–100) of products with reviews in a store view.
     *
     * @param int[] $productIds
     * @param int $storeId
     * @return array<int, int>
     */
    public function ratings(array $productIds, int $storeId): array
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from($this->table('review_entity_summary'), ['entity_pk_value', 'rating_summary'])
            ->where('entity_type = ?', self::PRODUCT_ENTITY_TYPE)
            ->where('store_id = ?', $storeId)
            ->where('entity_pk_value IN (?)', $productIds)
            ->where('reviews_count > 0');

        return array_map('intval', $connection->fetchPairs($select));
    }

    /**
     * Salable flag per product for the store view's website.
     *
     * @param int[] $productIds
     * @param int $storeId
     * @return array<int, int>
     */
    public function salable(array $productIds, int $storeId): array
    {
        $connection = $this->connection();
        $stockTable = $this->msiStockTable($storeId);
        if ($stockTable !== null) {
            $select = $connection->select()
                ->from(['e' => $this->table('catalog_product_entity')], ['entity_id'])
                ->join(['s' => $stockTable], 's.sku = e.sku', ['is_salable'])
                ->where('e.entity_id IN (?)', $productIds);
        } else {
            $select = $connection->select()
                ->from($this->table('cataloginventory_stock_status'), ['product_id', 'stock_status'])
                ->where('product_id IN (?)', $productIds)
                ->where('website_id = ?', 0);
        }

        return array_map('intval', $connection->fetchPairs($select));
    }

    /**
     * Price index rows of the store view's website.
     *
     * @param int[] $productIds
     * @param int $storeId
     * @return array<int, array<int, array{price: float, final_price: float}>> product id => group id => prices
     */
    public function prices(array $productIds, int $storeId): array
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from($this->table('catalog_product_index_price'), ['entity_id', 'customer_group_id', 'price', 'final_price'])
            ->where('entity_id IN (?)', $productIds)
            ->where('website_id = ?', $this->websiteId($storeId));
        $prices = [];
        foreach ($connection->fetchAll($select) as $row) {
            $prices[(int) $row['entity_id']][(int) $row['customer_group_id']] = [
                'price' => (float) $row['price'],
                'final_price' => (float) $row['final_price'],
            ];
        }

        return $prices;
    }

    /**
     * news_from_date / news_to_date per product (store value, else admin value).
     *
     * @param int[] $productIds
     * @param int $storeId
     * @return array<int, array{from: ?string, to: ?string}>
     */
    public function newsDates(array $productIds, int $storeId): array
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from(['d' => $this->table('catalog_product_entity_datetime')], ['store_id', 'value'])
            ->join(['a' => $this->table('eav_attribute')], 'a.attribute_id = d.attribute_id', ['attribute_code'])
            ->join(['t' => $this->table('eav_entity_type')], 't.entity_type_id = a.entity_type_id', [])
            ->join(['e' => $this->table('catalog_product_entity')], $this->linkCondition(), ['entity_id'])
            ->where('t.entity_type_code = ?', 'catalog_product')
            ->where('a.attribute_code IN (?)', ['news_from_date', 'news_to_date'])
            ->where('d.store_id IN (?)', array_unique([0, $storeId]))
            ->where('e.entity_id IN (?)', $productIds)
            ->order('d.store_id ASC');
        $dates = [];
        foreach ($connection->fetchAll($select) as $row) {
            $key = $row['attribute_code'] === 'news_from_date' ? 'from' : 'to';
            $dates[(int) $row['entity_id']][$key] = $row['value'] === null ? null : (string) $row['value'];
        }

        return array_map(static fn (array $d): array => ['from' => $d['from'] ?? null, 'to' => $d['to'] ?? null], $dates);
    }

    /**
     * Customer group ids.
     *
     * @return int[]
     */
    public function groupIds(): array
    {
        $connection = $this->connection();

        return array_map('intval', $connection->fetchCol(
            $connection->select()->from($this->table('customer_group'), ['customer_group_id'])
        ));
    }

    /**
     * @param int $storeId
     * @return int
     */
    public function websiteId(int $storeId): int
    {
        return (int) $this->storeManager->getStore($storeId)->getWebsiteId();
    }

    /**
     * Website ids without admin.
     *
     * @return int[]
     */
    public function websiteIds(): array
    {
        return array_map(static fn ($website): int => (int) $website->getId(), array_values($this->storeManager->getWebsites()));
    }

    /**
     * The MSI stock index table of the store view's website, or null without MSI.
     *
     * @param int $storeId
     * @return string|null
     */
    private function msiStockTable(int $storeId): ?string
    {
        $connection = $this->connection();
        $channels = $this->table('inventory_stock_sales_channel');
        if (!$connection->isTableExists($channels)) {
            return null;
        }
        $websiteCode = (string) $this->storeManager->getWebsite($this->websiteId($storeId))->getCode();
        $stockId = (int) $connection->fetchOne(
            $connection->select()->from($channels, ['stock_id'])->where('type = ?', 'website')->where('code = ?', $websiteCode)
        );
        $table = $this->table('inventory_stock_' . ($stockId ?: 1));

        return $connection->isTableExists($table) ? $table : null;
    }

    /**
     * Join of EAV values to products (entity_id or row_id on Adobe Commerce).
     *
     * @return string
     */
    private function linkCondition(): string
    {
        $link = $this->connection()->tableColumnExists($this->table('catalog_product_entity'), 'row_id')
            ? 'row_id'
            : 'entity_id';

        return sprintf('e.%1$s = d.%1$s', $link);
    }

    /**
     * @param string $name
     * @return string
     */
    private function table(string $name): string
    {
        return $this->resource->getTableName($name);
    }

    /**
     * @return AdapterInterface
     */
    private function connection(): AdapterInterface
    {
        return $this->resource->getConnection();
    }
}
