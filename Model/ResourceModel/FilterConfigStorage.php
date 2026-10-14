<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\ResourceModel;

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
    public const TABLE_FILTER_CATEGORY = 'openfilter_filter_category';

    /** Table values of openfilter_filter_category.mode by FilterConfigInterface category mode. */
    private const CATEGORY_MODES = ['only' => 'show', 'except' => 'hide'];

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
        return $this->storeTexts($storeId, 'label_override', 'label');
    }

    /**
     * Tooltips for one store view, falling back to the admin (store 0) row (1.1).
     *
     * @param int $storeId
     * @return array{attributes: array<int, string>, builtins: array<string, string>}
     */
    public function tooltips(int $storeId): array
    {
        return $this->storeTexts($storeId, 'tooltip', 'tooltip');
    }

    /**
     * @param int $storeId
     * @param string $attributeColumn column in openfilter_attribute_store
     * @param string $builtinColumn column in openfilter_builtin_store
     * @return array{attributes: array<int, string>, builtins: array<string, string>}
     */
    private function storeTexts(int $storeId, string $attributeColumn, string $builtinColumn): array
    {
        $connection = $this->connection();
        $stores = array_unique([0, $storeId]);
        $attributes = [];
        $select = $connection->select()
            ->from($this->table(self::TABLE_ATTRIBUTE_STORE), ['attribute_id', 'text' => $attributeColumn])
            ->where('store_id IN (?)', $stores)
            ->where("$attributeColumn IS NOT NULL AND $attributeColumn <> ''")
            ->order('store_id ASC');
        foreach ($connection->fetchAll($select) as $row) {
            $attributes[(int) $row['attribute_id']] = (string) $row['text'];
        }
        $builtins = [];
        $select = $connection->select()
            ->from($this->table(self::TABLE_BUILTIN_STORE), ['code', 'text' => $builtinColumn])
            ->where('store_id IN (?)', $stores)
            ->where("$builtinColumn IS NOT NULL AND $builtinColumn <> ''")
            ->order('store_id ASC');
        foreach ($connection->fetchAll($select) as $row) {
            $builtins[(string) $row['code']] = (string) $row['text'];
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
     * @param string|null $label null or '' removes it
     * @return void
     */
    public function saveAttributeLabel(int $attributeId, int $storeId, ?string $label): void
    {
        $this->saveAttributeText($attributeId, $storeId, 'label_override', $label);
    }

    /**
     * @param int $attributeId
     * @param int $storeId
     * @param string|null $tooltip null or '' removes it
     * @return void
     */
    public function saveAttributeTooltip(int $attributeId, int $storeId, ?string $tooltip): void
    {
        $this->saveAttributeText($attributeId, $storeId, 'tooltip', $tooltip);
    }

    /**
     * @param string $code
     * @param int $storeId
     * @param string|null $label null or '' removes it
     * @return void
     */
    public function saveBuiltinLabel(string $code, int $storeId, ?string $label): void
    {
        $this->saveBuiltinText($code, $storeId, 'label', $label);
    }

    /**
     * @param string $code
     * @param int $storeId
     * @param string|null $tooltip null or '' removes it
     * @return void
     */
    public function saveBuiltinTooltip(string $code, int $storeId, ?string $tooltip): void
    {
        $this->saveBuiltinText($code, $storeId, 'tooltip', $tooltip);
    }

    /**
     * @param int $attributeId
     * @param int $storeId
     * @param string $column label_override or tooltip
     * @param string|null $value
     * @return void
     */
    private function saveAttributeText(int $attributeId, int $storeId, string $column, ?string $value): void
    {
        $table = $this->table(self::TABLE_ATTRIBUTE_STORE);
        if ($value === null || $value === '') {
            $this->connection()->update(
                $table,
                [$column => null],
                ['attribute_id = ?' => $attributeId, 'store_id = ?' => $storeId]
            );

            return;
        }
        $this->connection()->insertOnDuplicate(
            $table,
            ['attribute_id' => $attributeId, 'store_id' => $storeId, $column => $value],
            [$column]
        );
    }

    /**
     * A built-in's store row goes once it holds neither label nor tooltip.
     *
     * @param string $code
     * @param int $storeId
     * @param string $column label or tooltip
     * @param string|null $value
     * @return void
     */
    private function saveBuiltinText(string $code, int $storeId, string $column, ?string $value): void
    {
        $table = $this->table(self::TABLE_BUILTIN_STORE);
        $where = ['code = ?' => $code, 'store_id = ?' => $storeId];
        if ($value === null || $value === '') {
            $this->connection()->update($table, [$column => null], $where);
            $this->connection()->delete($table, array_merge($where, ['label IS NULL', 'tooltip IS NULL']));

            return;
        }
        $this->connection()->insertOnDuplicate(
            $table,
            ['code' => $code, 'store_id' => $storeId, $column => $value],
            [$column]
        );
    }

    /**
     * Where filters show on category pages (1.1), by filter key; filters without rows show everywhere.
     *
     * @return array<string, array{mode: string, ids: int[]}>
     */
    public function categoryRules(): array
    {
        $connection = $this->connection();
        $modes = array_flip(self::CATEGORY_MODES);
        $rules = [];
        foreach ($connection->fetchAll($connection->select()->from($this->table(self::TABLE_FILTER_CATEGORY))) as $row) {
            $key = (string) $row['filter_key'];
            $rules[$key]['mode'] = $modes[(string) $row['mode']] ?? 'all';
            $rules[$key]['ids'][] = (int) $row['category_id'];
        }

        return $rules;
    }

    /**
     * @param string $key filter key
     * @param string $mode all, only or except
     * @param int[] $categoryIds
     * @return void
     */
    public function saveCategoryRule(string $key, string $mode, array $categoryIds): void
    {
        $table = $this->table(self::TABLE_FILTER_CATEGORY);
        $this->connection()->delete($table, ['filter_key = ?' => $key]);
        if (!isset(self::CATEGORY_MODES[$mode]) || $categoryIds === []) {
            return;
        }
        $rows = array_map(
            static fn (int $id): array => ['filter_key' => $key, 'category_id' => $id, 'mode' => self::CATEGORY_MODES[$mode]],
            array_values(array_unique($categoryIds))
        );
        $this->connection()->insertMultiple($table, $rows);
    }

    /**
     * Layered navigation flags of a product attribute (null when there is no such attribute).
     *
     * @param int $attributeId
     * @return array{code: string, input: string, is_filterable: int, is_filterable_in_search: int}|null
     */
    public function attributeFlags(int $attributeId): ?array
    {
        $connection = $this->connection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from(['e' => $this->table('eav_attribute')], ['attribute_code', 'frontend_input'])
                ->join(
                    ['c' => $this->table('catalog_eav_attribute')],
                    'c.attribute_id = e.attribute_id',
                    ['is_filterable', 'is_filterable_in_search']
                )
                ->where('e.attribute_id = ?', $attributeId)
        );
        if (!is_array($row)) {
            return null;
        }

        return [
            'code' => (string) $row['attribute_code'],
            'input' => (string) $row['frontend_input'],
            'is_filterable' => (int) $row['is_filterable'],
            'is_filterable_in_search' => (int) $row['is_filterable_in_search'],
        ];
    }

    /**
     * Label overrides of one filter by store id (0 = all store views), as stored.
     *
     * @param int|null $attributeId the attribute, or null for a built-in
     * @param string $code built-in code (used when $attributeId is null)
     * @return array<int, string>
     */
    public function labelOverrides(?int $attributeId, string $code): array
    {
        return $this->overrides($attributeId, $code, 'label_override', 'label');
    }

    /**
     * Tooltips of one filter by store id (0 = all store views), as stored (1.1).
     *
     * @param int|null $attributeId the attribute, or null for a built-in
     * @param string $code built-in code (used when $attributeId is null)
     * @return array<int, string>
     */
    public function tooltipOverrides(?int $attributeId, string $code): array
    {
        return $this->overrides($attributeId, $code, 'tooltip', 'tooltip');
    }

    /**
     * @param int|null $attributeId
     * @param string $code
     * @param string $attributeColumn
     * @param string $builtinColumn
     * @return array<int, string>
     */
    private function overrides(?int $attributeId, string $code, string $attributeColumn, string $builtinColumn): array
    {
        $connection = $this->connection();
        $select = $attributeId !== null
            ? $connection->select()->from($this->table(self::TABLE_ATTRIBUTE_STORE), ['store_id', $attributeColumn])
                ->where('attribute_id = ?', $attributeId)
                ->where("$attributeColumn IS NOT NULL AND $attributeColumn <> ''")
            : $connection->select()->from($this->table(self::TABLE_BUILTIN_STORE), ['store_id', $builtinColumn])
                ->where('code = ?', $code)
                ->where("$builtinColumn IS NOT NULL AND $builtinColumn <> ''");
        $texts = [];
        foreach ($connection->fetchPairs($select) as $storeId => $text) {
            $texts[(int) $storeId] = (string) $text;
        }

        return $texts;
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
