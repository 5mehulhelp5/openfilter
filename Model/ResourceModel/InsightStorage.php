<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression;

/**
 * Table access for openfilter_insight_daily (1.2): daily counts per store view, category, kind, filter and value.
 */
class InsightStorage
{
    public const TABLE = 'openfilter_insight_daily';

    /**
     * @param ResourceConnection $resource
     */
    public function __construct(
        private readonly ResourceConnection $resource
    ) {
    }

    /**
     * Adds the rows' hits to the counts of their day (one statement).
     *
     * @param array<int, array{day: string, store_id: int, category_id: int, kind: string, filter_key: string,
     *     value: string, hits: int}> $rows
     * @return void
     */
    public function add(array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $this->resource->getConnection()->insertOnDuplicate(
            $this->resource->getTableName(self::TABLE),
            $rows,
            ['hits' => new Expression('hits + VALUES(hits)')]
        );
    }

    /**
     * Counts of one kind since a day, summed over the days and categories.
     *
     * @param string $kind
     * @param string $from first day, Y-m-d
     * @param int $storeId
     * @param int $limit 0 = all
     * @return array<int, array{filter_key: string, value: string, hits: int}> most hits first
     */
    public function sums(string $kind, string $from, int $storeId, int $limit = 0): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->resource->getTableName(self::TABLE), ['filter_key', 'value', 'hits' => new Expression('SUM(hits)')])
            ->where('kind = ?', $kind)
            ->where('day >= ?', $from)
            ->where('store_id = ?', $storeId)
            ->group(['filter_key', 'value'])
            ->order(['hits DESC', 'filter_key ASC', 'value ASC']);
        if ($limit > 0) {
            $select->limit($limit);
        }
        $sums = [];
        foreach ($connection->fetchAll($select) as $row) {
            $sums[] = ['filter_key' => (string) $row['filter_key'], 'value' => (string) $row['value'], 'hits' => (int) $row['hits']];
        }

        return $sums;
    }

    /**
     * Deletes the counts of the days before $before.
     *
     * @param string $before Y-m-d
     * @return int rows deleted
     */
    public function purge(string $before): int
    {
        return (int) $this->resource->getConnection()->delete(
            $this->resource->getTableName(self::TABLE),
            ['day < ?' => $before]
        );
    }
}
