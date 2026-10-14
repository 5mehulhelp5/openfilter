<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Layer;

use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Active categories of a store view as a tree (1.1 category filter), from OpenFilter's cache: built once from the
 * category collection, then read without SQL on every page (02 · Architecture §10). The entry carries the general
 * category tag (new, deleted, moved categories) and each category's own tag (a renamed one), so it is rebuilt then.
 */
class CategoryTree
{
    private const CACHE_ID = 'openfilter_category_tree_';

    /** @var array<int, array<int, array{name: string, parent: int, position: int, children: int[]}>> by store */
    private array $loaded = [];

    /**
     * @param CollectionFactory $collectionFactory
     * @param StoreManagerInterface $storeManager
     * @param CacheType $cache
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly CacheType $cache
    ) {
    }

    /**
     * Categories below $rootId down to $depth levels, depth first in their position order.
     *
     * @param int $rootId
     * @param int $depth
     * @param int $storeId
     * @return array<int, array{id: int, name: string, depth: int}> depth 1 = a child of the root
     */
    public function below(int $rootId, int $depth, int $storeId): array
    {
        $tree = $this->forStore($storeId);
        $nodes = [];
        $walk = function (int $parent, int $level) use (&$walk, &$nodes, $tree, $depth): void {
            foreach ($tree[$parent]['children'] ?? [] as $child) {
                $nodes[] = ['id' => $child, 'name' => $tree[$child]['name'], 'depth' => $level];
                if ($level < $depth) {
                    $walk($child, $level + 1);
                }
            }
        };
        $walk($rootId, 1);

        return $nodes;
    }

    /**
     * Every active category of the store view by its place in the tree, depth first (canonical URL order).
     *
     * @param int $storeId
     * @return array<int, int> category id => position
     */
    public function order(int $storeId): array
    {
        $tree = $this->forStore($storeId);
        $root = $this->rootId($storeId);
        $order = [];
        $walk = function (int $parent) use (&$walk, &$order, $tree): void {
            foreach ($tree[$parent]['children'] ?? [] as $child) {
                $order[$child] = count($order);
                $walk($child);
            }
        };
        $walk($root);

        return $order;
    }

    /**
     * Ids of a category and the categories above it, up to the store's root (for "Where it shows").
     *
     * @param int $id
     * @param int $storeId
     * @return int[]
     */
    public function pathIds(int $id, int $storeId): array
    {
        $tree = $this->forStore($storeId);
        $path = [];
        for ($node = $id; isset($tree[$node]); $node = $tree[$node]['parent']) {
            $path[] = $node;
        }

        return array_reverse($path);
    }

    /**
     * Whether $id is an active category below $rootId.
     *
     * @param int $id
     * @param int $rootId
     * @param int $storeId
     * @return bool
     */
    public function isBelow(int $id, int $rootId, int $storeId): bool
    {
        $tree = $this->forStore($storeId);
        for ($node = $tree[$id]['parent'] ?? null; $node !== null; $node = $tree[$node]['parent'] ?? null) {
            if ($node === $rootId) {
                return isset($tree[$id]);
            }
        }

        return false;
    }

    /**
     * @param int $storeId
     * @return array<int, array{name: string, parent: int, position: int, children: int[]}> by category id
     */
    public function forStore(int $storeId): array
    {
        if (isset($this->loaded[$storeId])) {
            return $this->loaded[$storeId];
        }
        $cached = $this->cache->load(self::CACHE_ID . $storeId);
        if (is_string($cached) && $cached !== '') {
            $tree = json_decode($cached, true);
            if (is_array($tree)) {
                return $this->loaded[$storeId] = $this->intKeys($tree);
            }
        }
        $tree = $this->build($storeId);
        // A plain save (a new name) cleans only the category's own tag; new, deleted and moved categories clean the
        // general one.
        $tags = [Category::CACHE_TAG];
        foreach (array_keys($tree) as $id) {
            $tags[] = Category::CACHE_TAG . '_' . $id;
        }
        $this->cache->save((string) json_encode($tree), self::CACHE_ID . $storeId, $tags);

        return $this->loaded[$storeId] = $tree;
    }

    /**
     * Forgets the trees read in this process.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->loaded = [];
    }

    /**
     * @param int $storeId
     * @return array<int, array{name: string, parent: int, position: int, children: int[]}>
     */
    private function build(int $storeId): array
    {
        $rootId = $this->rootId($storeId);
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($storeId)
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('is_active', '1')
            ->addFieldToFilter('path', ['like' => '1/' . $rootId . '/%']);
        $tree = [$rootId => ['name' => '', 'parent' => 1, 'position' => 0, 'children' => []]];
        foreach ($collection as $category) {
            $tree[(int) $category->getId()] = [
                'name' => (string) $category->getName(),
                'parent' => (int) $category->getParentId(),
                'position' => (int) $category->getPosition(),
                'children' => [],
            ];
        }
        $ids = array_keys($tree);
        usort($ids, static fn (int $a, int $b): int => [$tree[$a]['position'], $a] <=> [$tree[$b]['position'], $b]);
        foreach ($ids as $id) {
            $parent = $tree[$id]['parent'];
            if ($id !== $rootId && isset($tree[$parent])) {
                $tree[$parent]['children'][] = $id;
            }
        }

        return $tree;
    }

    /**
     * @param int $storeId
     * @return int the store view's root category
     */
    private function rootId(int $storeId): int
    {
        $store = $this->storeManager->getStore($storeId);

        return (int) $this->storeManager->getGroup((string) $store->getStoreGroupId())->getRootCategoryId();
    }

    /**
     * JSON turns integer keys into strings; the tree uses category ids.
     *
     * @param array<int|string, mixed> $tree
     * @return array<int, array{name: string, parent: int, position: int, children: int[]}>
     */
    private function intKeys(array $tree): array
    {
        $result = [];
        foreach ($tree as $id => $node) {
            $result[(int) $id] = [
                'name' => (string) $node['name'],
                'parent' => (int) $node['parent'],
                'position' => (int) $node['position'],
                'children' => array_map('intval', (array) $node['children']),
            ];
        }

        return $result;
    }
}
