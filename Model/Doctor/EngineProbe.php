<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Doctor;

use Magento\Elasticsearch\SearchAdapter\ConnectionManager;
use Magento\Elasticsearch\SearchAdapter\SearchIndexNameResolver;

/**
 * Asks the search engine (OpenSearch, Elasticsearch 8) about a store view's product index: which fields it maps,
 * and how many visible products of a category match a clause. Returns null when the engine cannot be asked.
 */
class EngineProbe
{
    private const INDEXER = 'catalogsearch_fulltext';

    /** Visibility "catalog" (2) and "catalog, search" (4): products listed on category pages. */
    private const CATALOG_VISIBILITY = [2, 4];

    /** @var array<int, string[]|null> mapped fields per store */
    private array $fields = [];

    /**
     * @param ConnectionManager $connectionManager
     * @param SearchIndexNameResolver $indexNames
     */
    public function __construct(
        private readonly ConnectionManager $connectionManager,
        private readonly SearchIndexNameResolver $indexNames
    ) {
    }

    /**
     * @param int $storeId
     * @return string[]|null field names in the store view's product index
     */
    public function fields(int $storeId): ?array
    {
        if (array_key_exists($storeId, $this->fields)) {
            return $this->fields[$storeId];
        }
        try {
            $connection = $this->connectionManager->getConnection();
            if (!method_exists($connection, 'getMapping')) {
                return $this->fields[$storeId] = null;
            }
            $mapping = (array) $connection->getMapping(['index' => $this->index($storeId)]);
            $index = reset($mapping);
            $properties = is_array($index) ? (array) ($index['mappings']['properties'] ?? []) : [];

            return $this->fields[$storeId] = array_map('strval', array_keys($properties));
        } catch (\Throwable) {
            return $this->fields[$storeId] = null;
        }
    }

    /**
     * Visible products of a category matching a clause.
     *
     * @param int $storeId
     * @param int $categoryId
     * @param array<string, mixed> $clause an engine query clause, e.g. ['exists' => ['field' => 'color']]
     * @return int|null
     */
    public function count(int $storeId, int $categoryId, array $clause): ?int
    {
        $response = $this->query($storeId, $categoryId, $clause, []);
        if ($response === null) {
            return null;
        }
        $total = $response['hits']['total'] ?? 0;

        return (int) (is_array($total) ? ($total['value'] ?? 0) : $total);
    }

    /**
     * Number of different values of a field among the visible products of a category (at most $limit).
     *
     * @param int $storeId
     * @param int $categoryId
     * @param string $field
     * @param int $limit
     * @return int|null
     */
    public function distinct(int $storeId, int $categoryId, string $field, int $limit = 2): ?int
    {
        $response = $this->query(
            $storeId,
            $categoryId,
            ['exists' => ['field' => $field]],
            ['values' => ['terms' => ['field' => $field, 'size' => $limit]]]
        );

        return $response === null ? null : count((array) ($response['aggregations']['values']['buckets'] ?? []));
    }

    /**
     * @param int $storeId
     * @param int $categoryId
     * @param array<string, mixed> $clause
     * @param array<string, mixed> $aggregations
     * @return array<string, mixed>|null
     */
    private function query(int $storeId, int $categoryId, array $clause, array $aggregations): ?array
    {
        try {
            $connection = $this->connectionManager->getConnection();
            if (!method_exists($connection, 'query')) {
                return null;
            }
            $body = [
                'size' => 0,
                'track_total_hits' => true,
                'query' => ['bool' => ['filter' => [
                    ['term' => ['category_ids' => $categoryId]],
                    ['terms' => ['visibility' => self::CATALOG_VISIBILITY]],
                    $clause,
                ]]],
            ];
            if ($aggregations !== []) {
                $body['aggregations'] = $aggregations;
            }

            return (array) $connection->query(['index' => $this->index($storeId), 'body' => $body]);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param int $storeId
     * @return string
     */
    private function index(int $storeId): string
    {
        return $this->indexNames->getIndexName($storeId, self::INDEXER);
    }
}
