<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Sitemap;

use Iranimij\OpenFilter\Model\Sitemap\CategoryCollectionFactory;
use Iranimij\OpenFilter\Model\Sitemap\OptionCounts;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Layer\Category as CategoryLayer;
use Magento\Catalog\Model\Layer\CategoryFactory as CategoryLayerFactory;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\Api\Search\SearchCriteriaBuilderFactory;
use Magento\Framework\ObjectManagerInterface;
use PHPUnit\Framework\TestCase;

class OptionCountsTest extends TestCase
{
    public function testCountsPerOptionFromTheCategoryPagesCollectionWithoutEmptyOptions(): void
    {
        $category = $this->createStub(Category::class);
        $collection = $this->createMock(Collection::class);
        $collection->expects(self::once())->method('addCategoryFilter')->with($category);
        $collection->method('getFacetedData')->willReturnMap([
            ['color', [5 => ['value' => 5, 'count' => 3], 6 => ['value' => 6, 'count' => 0]]],
            ['size', [9 => ['value' => 9, 'count' => '2']]],
        ]);
        $layer = $this->createMock(CategoryLayer::class);
        $layer->expects(self::once())->method('setCurrentCategory')->with(21);
        $layer->method('getCurrentCategory')->willReturn($category);
        $layer->expects(self::once())->method('prepareProductCollection')->with($collection);
        $layers = $this->createStub(CategoryLayerFactory::class);
        $layers->method('create')->willReturn($layer);
        $collections = $this->createStub(CategoryCollectionFactory::class);
        $collections->method('create')->willReturn($collection);

        self::assertSame(
            ['color' => [5 => 3], 'size' => [9 => 2]],
            (new OptionCounts($layers, $collections))->forCategory(21, ['color', 'size'])
        );
    }

    public function testEachCollectionGetsItsOwnCriteriaBuilder(): void
    {
        $builder = $this->createStub(SearchCriteriaBuilder::class);
        $builders = $this->createMock(SearchCriteriaBuilderFactory::class);
        $builders->expects(self::once())->method('create')->willReturn($builder);
        $collection = $this->createStub(Collection::class);
        $objectManager = $this->createMock(ObjectManagerInterface::class);
        $objectManager->expects(self::once())->method('create')
            ->with('elasticsearchCategoryCollection', ['searchCriteriaBuilder' => $builder])
            ->willReturn($collection);

        self::assertSame($collection, (new CategoryCollectionFactory($objectManager, $builders))->create());
    }
}
