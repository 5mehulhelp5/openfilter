<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Layer;

use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\Layer\CategoryTree;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Api\Data\GroupInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class CategoryTreeTest extends TestCase
{
    private const TREE = [
        2 => ['name' => '', 'parent' => 1, 'position' => 0, 'children' => [20, 11]],
        20 => ['name' => 'Women', 'parent' => 2, 'position' => 1, 'children' => [21, 22]],
        21 => ['name' => 'Tops', 'parent' => 20, 'position' => 1, 'children' => [23]],
        23 => ['name' => 'Jackets', 'parent' => 21, 'position' => 1, 'children' => []],
        22 => ['name' => 'Bottoms', 'parent' => 20, 'position' => 2, 'children' => []],
        11 => ['name' => 'Men', 'parent' => 2, 'position' => 2, 'children' => []],
    ];

    public function testBelowWalksDepthFirstWithDepths(): void
    {
        self::assertSame([
            ['id' => 21, 'name' => 'Tops', 'depth' => 1],
            ['id' => 23, 'name' => 'Jackets', 'depth' => 2],
            ['id' => 22, 'name' => 'Bottoms', 'depth' => 1],
        ], $this->tree()->below(20, 3, 1));
        self::assertSame([21, 22], array_column($this->tree()->below(20, 1, 1), 'id'), 'one level');
    }

    public function testIsBelowFollowsParents(): void
    {
        $tree = $this->tree();

        self::assertTrue($tree->isBelow(23, 20, 1));
        self::assertTrue($tree->isBelow(23, 2, 1));
        self::assertFalse($tree->isBelow(11, 20, 1));
        self::assertFalse($tree->isBelow(20, 20, 1));
        self::assertFalse($tree->isBelow(99, 20, 1));
    }

    public function testOrderIsTheTreesDepthFirstOrder(): void
    {
        self::assertSame([20 => 0, 21 => 1, 23 => 2, 22 => 3, 11 => 4], $this->tree()->order(1));
    }

    private function tree(): CategoryTree
    {
        $cache = $this->createStub(CacheType::class);
        $cache->method('load')->willReturn((string) json_encode(self::TREE));
        $store = $this->createStub(StoreInterface::class);
        $store->method('getStoreGroupId')->willReturn(1);
        $group = $this->createStub(GroupInterface::class);
        $group->method('getRootCategoryId')->willReturn(2);
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);
        $stores->method('getGroup')->willReturn($group);
        $collections = $this->createMock(CollectionFactory::class);
        $collections->expects(self::never())->method('create');

        return new CategoryTree($collections, $stores, $cache);
    }
}
