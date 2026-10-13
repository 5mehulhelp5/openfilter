<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Url;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * Table access for openfilter_option_slug and openfilter_attribute_slug.
 */
class SlugStorage
{
    public const TABLE_OPTION = 'openfilter_option_slug';
    public const TABLE_ATTRIBUTE = 'openfilter_attribute_slug';

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Option labels of one attribute: admin labels in option sort order, and store view labels where set.
     *
     * @param int $attributeId
     * @return array{admin: array<int, string>, stores: array<int, array<int, string>>}
     */
    public function optionLabels(int $attributeId): array
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from(['o' => $this->table('eav_attribute_option')], ['option_id'])
            ->join(['v' => $this->table('eav_attribute_option_value')], 'v.option_id = o.option_id', ['store_id', 'value'])
            ->where('o.attribute_id = ?', $attributeId)
            ->order(['o.sort_order ASC', 'o.option_id ASC', 'v.store_id ASC']);
        $admin = [];
        $stores = [];
        foreach ($connection->fetchAll($select) as $row) {
            $storeId = (int) $row['store_id'];
            if ($storeId === 0) {
                $admin[(int) $row['option_id']] = (string) $row['value'];
            } elseif ((string) $row['value'] !== '') {
                $stores[$storeId][(int) $row['option_id']] = (string) $row['value'];
            }
        }

        return ['admin' => $admin, 'stores' => $stores];
    }

    /**
     * @param int $optionId
     * @return int|null the attribute of an option
     */
    public function attributeOfOption(int $optionId): ?int
    {
        $connection = $this->connection();
        $id = $connection->fetchOne(
            $connection->select()->from($this->table('eav_attribute_option'), ['attribute_id'])
                ->where('option_id = ?', $optionId)
        );

        return $id === false ? null : (int) $id;
    }

    /**
     * Stored option slugs of one attribute by store.
     *
     * @param int $attributeId
     * @return array<int, array<int, string>> store id => option id => slug
     */
    public function optionSlugRows(int $attributeId): array
    {
        $connection = $this->connection();
        $rows = [];
        $select = $connection->select()->from($this->table(self::TABLE_OPTION))->where('attribute_id = ?', $attributeId);
        foreach ($connection->fetchAll($select) as $row) {
            $rows[(int) $row['store_id']][(int) $row['option_id']] = (string) $row['slug'];
        }

        return $rows;
    }

    /**
     * Effective option slugs of every attribute in a store view.
     *
     * @param int $storeId
     * @return array<int, array<int, string>> attribute id => option id => slug
     */
    public function optionSlugsForStore(int $storeId): array
    {
        $connection = $this->connection();
        $select = $connection->select()->from($this->table(self::TABLE_OPTION))
            ->where('store_id IN (?)', array_unique([0, $storeId]))
            ->order(['store_id ASC', 'attribute_id ASC', 'option_id ASC']);
        $slugs = [];
        foreach ($connection->fetchAll($select) as $row) {
            $slugs[(int) $row['attribute_id']][(int) $row['option_id']] = (string) $row['slug'];
        }

        return $slugs;
    }

    /**
     * Effective attribute slugs in a store view (rows only; defaults are the attribute codes).
     *
     * @param int $storeId
     * @return array<int, string> attribute id => slug
     */
    public function attributeSlugsForStore(int $storeId): array
    {
        $connection = $this->connection();
        $select = $connection->select()->from($this->table(self::TABLE_ATTRIBUTE))
            ->where('store_id IN (?)', array_unique([0, $storeId]))
            ->order('store_id ASC');
        $slugs = [];
        foreach ($connection->fetchAll($select) as $row) {
            $slugs[(int) $row['attribute_id']] = (string) $row['slug'];
        }

        return $slugs;
    }

    /**
     * @param int $attributeId
     * @param int $storeId
     * @param array<int, string> $slugs option id => slug to write
     * @param int[] $remove option ids whose row in this store goes away (they inherit the admin slug)
     * @return void
     */
    public function writeOptionSlugs(int $attributeId, int $storeId, array $slugs, array $remove = []): void
    {
        $connection = $this->connection();
        $table = $this->table(self::TABLE_OPTION);
        if ($remove !== []) {
            $connection->delete($table, ['store_id = ?' => $storeId, 'option_id IN (?)' => $remove]);
        }
        if ($slugs === []) {
            return;
        }
        // Free the names first so swapping two slugs does not hit the unique key.
        $connection->delete($table, ['store_id = ?' => $storeId, 'option_id IN (?)' => array_keys($slugs)]);
        $rows = [];
        foreach ($slugs as $optionId => $slug) {
            $rows[] = ['option_id' => $optionId, 'attribute_id' => $attributeId, 'store_id' => $storeId, 'slug' => $slug];
        }
        $connection->insertMultiple($table, $rows);
    }

    /**
     * @param int $attributeId
     * @param int $storeId
     * @param string|null $slug null removes the store row
     * @return void
     */
    public function writeAttributeSlug(int $attributeId, int $storeId, ?string $slug): void
    {
        $table = $this->table(self::TABLE_ATTRIBUTE);
        if ($slug === null) {
            $this->connection()->delete($table, ['attribute_id = ?' => $attributeId, 'store_id = ?' => $storeId]);

            return;
        }
        $this->connection()->insertOnDuplicate(
            $table,
            ['attribute_id' => $attributeId, 'store_id' => $storeId, 'slug' => $slug],
            ['slug']
        );
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
