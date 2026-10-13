<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * Table access for filter settings: openfilter_attribute(_store), openfilter_builtin(_store) and the filterable
 * product attributes. Only used to build the per-store cache and to save.
 */
class FilterConfigStorage
{
    public const TABLE_ATTRIBUTE = 'openfilter_attribute';
    public const TABLE_ATTRIBUTE_STORE = 'openfilter_attribute_store';
    public const TABLE_BUILTIN = 'openfilter_builtin';
    public const TABLE_BUILTIN_STORE = 'openfilter_builtin_store';

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Product attributes used in layered navigation (category or search), with the label of one store view.
     *
     * @param int $storeId
     * @return array<int, array{attribute_id: int, code: string, input_type: string, swatch_type: ?string,
     *     position: int, label: ?string}>
     */
    public function filterableAttributes(int $storeId): array
    {
        $connection = $this->connection();
        $select = $connection->select()
            ->from(['ea' => $this->resource->getTableName('eav_attribute')], [
                'attribute_id', 'attribute_code', 'frontend_input', 'backend_type', 'frontend_label',
            ])
            ->join(
                ['et' => $this->resource->getTableName('eav_entity_type')],
                'et.entity_type_id = ea.entity_type_id',
                []
            )
            ->join(
                ['cea' => $this->resource->getTableName('catalog_eav_attribute')],
                'cea.attribute_id = ea.attribute_id',
                ['position', 'additional_data']
            )
            ->joinLeft(
                ['eal' => $this->resource->getTableName('eav_attribute_label')],
                $connection->quoteInto('eal.attribute_id = ea.attribute_id AND eal.store_id = ?', $storeId),
                ['store_label' => 'value']
            )
            ->where('et.entity_type_code = ?', 'catalog_product')
            ->where('cea.is_filterable > 0 OR cea.is_filterable_in_search > 0')
            ->order(['cea.position ASC', 'ea.attribute_id ASC']);

        $attributes = [];
        foreach ($connection->fetchAll($select) as $row) {
            $label = $row['store_label'] ?? null;
            $attributes[(int) $row['attribute_id']] = [
                'attribute_id' => (int) $row['attribute_id'],
                'code' => (string) $row['attribute_code'],
                'input_type' => $this->inputType((string) $row['frontend_input'], (string) $row['backend_type']),
                'swatch_type' => $this->swatchType($row['additional_data'] ?? null),
                'position' => (int) $row['position'],
                'label' => $label !== null && $label !== '' ? (string) $label : ($row['frontend_label'] ?? null),
            ];
        }

        return $attributes;
    }

    /**
     * @return array<int, array<string, mixed>> openfilter_attribute rows by attribute id
     */
    public function attributeRows(): array
    {
        $connection = $this->connection();
        $rows = $connection->fetchAll($connection->select()->from($this->table(self::TABLE_ATTRIBUTE)));

        return array_column($rows, null, 'attribute_id');
    }

    /**
     * @return array<string, array<string, mixed>> openfilter_builtin rows by code
     */
    public function builtinRows(): array
    {
        $connection = $this->connection();
        $rows = $connection->fetchAll($connection->select()->from($this->table(self::TABLE_BUILTIN)));

        return array_column($rows, null, 'code');
    }

    /**
     * Label overrides for one store view, falling back to the admin (store 0) row.
     *
     * @param int $storeId
     * @return array{attributes: array<int, string>, builtins: array<string, string>}
     */
    public function labels(int $storeId): array
    {
        $connection = $this->connection();
        $stores = array_unique([0, $storeId]);
        $attributes = [];
        $select = $connection->select()
            ->from($this->table(self::TABLE_ATTRIBUTE_STORE), ['attribute_id', 'label_override'])
            ->where('store_id IN (?)', $stores)
            ->where("label_override IS NOT NULL AND label_override <> ''")
            ->order('store_id ASC');
        foreach ($connection->fetchAll($select) as $row) {
            $attributes[(int) $row['attribute_id']] = (string) $row['label_override'];
        }
        $builtins = [];
        $select = $connection->select()
            ->from($this->table(self::TABLE_BUILTIN_STORE), ['code', 'label'])
            ->where('store_id IN (?)', $stores)
            ->where("label IS NOT NULL AND label <> ''")
            ->order('store_id ASC');
        foreach ($connection->fetchAll($select) as $row) {
            $builtins[(string) $row['code']] = (string) $row['label'];
        }

        return ['attributes' => $attributes, 'builtins' => $builtins];
    }

    /**
     * @param int $attributeId
     * @return int
     */
    public function optionCount(int $attributeId): int
    {
        $connection = $this->connection();

        return (int) $connection->fetchOne(
            $connection->select()
                ->from($this->table('eav_attribute_option'), ['COUNT(*)'])
                ->where('attribute_id = ?', $attributeId)
        );
    }

    /**
     * @param int $attributeId
     * @param array<string, int|string|null> $row
     * @return void
     */
    public function saveAttribute(int $attributeId, array $row): void
    {
        $this->connection()->insertOnDuplicate(
            $this->table(self::TABLE_ATTRIBUTE),
            ['attribute_id' => $attributeId] + $row,
            array_keys($row)
        );
    }

    /**
     * @param string $code
     * @param bool $active
     * @param array<string, int|string|null> $row
     * @return void
     */
    public function saveBuiltin(string $code, bool $active, array $row): void
    {
        $data = ['code' => $code, 'is_active' => (int) $active] + $row;
        $this->connection()->insertOnDuplicate(
            $this->table(self::TABLE_BUILTIN),
            $data,
            array_keys(array_diff_key($data, ['code' => true]))
        );
    }

    /**
     * @param int $attributeId
     * @return void
     */
    public function deleteAttribute(int $attributeId): void
    {
        $this->connection()->delete($this->table(self::TABLE_ATTRIBUTE), ['attribute_id = ?' => $attributeId]);
    }

    /**
     * @param int $attributeId
     * @param int $storeId
     * @param string|null $label
     * @return void
     */
    public function saveAttributeLabel(int $attributeId, int $storeId, ?string $label): void
    {
        $table = $this->table(self::TABLE_ATTRIBUTE_STORE);
        if ($label === null || $label === '') {
            $this->connection()->update(
                $table,
                ['label_override' => null],
                ['attribute_id = ?' => $attributeId, 'store_id = ?' => $storeId]
            );

            return;
        }
        $this->connection()->insertOnDuplicate(
            $table,
            ['attribute_id' => $attributeId, 'store_id' => $storeId, 'label_override' => $label],
            ['label_override']
        );
    }

    /**
     * @param string $code
     * @param int $storeId
     * @param string|null $label
     * @return void
     */
    public function saveBuiltinLabel(string $code, int $storeId, ?string $label): void
    {
        $table = $this->table(self::TABLE_BUILTIN_STORE);
        if ($label === null || $label === '') {
            $this->connection()->delete($table, ['code = ?' => $code, 'store_id = ?' => $storeId]);

            return;
        }
        $this->connection()->insertOnDuplicate(
            $table,
            ['code' => $code, 'store_id' => $storeId, 'label' => $label],
            ['label']
        );
    }

    /**
     * @param string $frontendInput
     * @param string $backendType
     * @return string select, multiselect, boolean, decimal (or the raw frontend input)
     */
    private function inputType(string $frontendInput, string $backendType): string
    {
        if ($frontendInput === 'boolean') {
            return 'boolean';
        }
        if ($frontendInput === 'multiselect') {
            return 'multiselect';
        }
        if ($frontendInput === 'select') {
            return 'select';
        }
        if ($frontendInput === 'price' || $backendType === 'decimal') {
            return 'decimal';
        }

        return $frontendInput;
    }

    /**
     * @param mixed $additionalData serialized catalog_eav_attribute.additional_data
     * @return string|null visual, text or null
     */
    private function swatchType(mixed $additionalData): ?string
    {
        if (!is_string($additionalData) || $additionalData === '') {
            return null;
        }
        $data = json_decode($additionalData, true);
        $type = is_array($data) ? ($data['swatch_input_type'] ?? null) : null;

        return in_array($type, ['visual', 'text'], true) ? $type : null;
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
