<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Layer;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Layer\CategoryTree as Tree;
use Iranimij\OpenFilter\Model\Layer\Filter\CategoryTree;
use Iranimij\OpenFilter\Model\Search\StrategyResolver;
use Iranimij\OpenFilter\Model\Url\FilterStateProvider;
use Iranimij\OpenFilter\Model\Url\UrlCodec;
use Iranimij\OpenFilter\Test\Integration\Catalog\M1Catalog;
use Iranimij\OpenFilter\Test\Integration\Catalog\M5Categories;
use Iranimij\OpenFilter\Test\Integration\Search\EngineRequests;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * M5 S4 category tree: the categories below the page's category with counts (anchors include their children),
 * picks narrow with "any of" and are counted without themselves, the page's own category stays, one search request
 * (SingleQuery). Checked against the catalog model, for both strategies.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 * @magentoCache openfilter enabled
 */
class CategoryTreeTest extends TestCase
{
    /** @var array{category: int, store: int, options: array<string, array<string, int>>}|null */
    private static ?array $catalog = null;

    /** @var array<string, int>|null category name => id */
    private static ?array $tree = null;

    public static function setUpBeforeClass(): void
    {
        self::$catalog = M1Catalog::create();
        self::$tree = M5Categories::create(self::$catalog['category']);
    }

    protected function setUp(): void
    {
        $this->category(Filter::DISPLAY_TREE, true, 3);
    }

    protected function tearDown(): void
    {
        $this->category(Filter::DISPLAY_LIST, false, 3);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function strategies(): array
    {
        return ['single query' => [StrategyResolver::SINGLE_QUERY], 'per attribute' => [StrategyResolver::PER_ATTRIBUTE]];
    }

    public function testTheTreeShowsTheCategoriesBelowThePageWithCounts(): void
    {
        $filter = null;
        $requests = EngineRequests::during(function () use (&$filter): void {
            [, , $filter] = $this->layer([]);
            $filter->getItems();
        });

        self::assertSame(1, $requests);
        self::assertInstanceOf(CategoryTree::class, $filter);
        self::assertSame([
            [M5Categories::TOPS, 1, $this->expected(M5Categories::TOPS, [])],
            [M5Categories::TEES, 2, $this->expected(M5Categories::TEES, [])],
            [M5Categories::BOTTOMS, 1, $this->expected(M5Categories::BOTTOMS, [])],
        ], $this->rows($filter));
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function testPicksNarrowWithAnyOfAndAreCountedWithoutThemselves(string $strategy): void
    {
        Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class)->setValue(StrategyResolver::XML_PATH_STRATEGY, $strategy);
        $picks = self::$tree[M5Categories::TEES] . ',' . self::$tree[M5Categories::BOTTOMS];
        $layer = null;
        $filter = null;
        $filters = [];
        $requests = EngineRequests::during(function () use ($picks, &$layer, &$filter, &$filters): void {
            [$layer, $filters, $filter] = $this->layer(['cat' => $picks, M1Catalog::COLOR => 'red']);
            $filter->getItems();
            $filters[M1Catalog::COLOR]->getItems();
        });

        self::assertSame($strategy === StrategyResolver::SINGLE_QUERY ? 1 : 3, $requests);
        $matching = array_filter(
            $this->products(['Red']),
            static fn (string $sku): bool => M5Categories::inTree($sku, M5Categories::TEES) || M5Categories::inTree($sku, M5Categories::BOTTOMS)
        );
        self::assertSame(count($matching), $layer->getProductCollection()->getSize());
        self::assertSame([
            [M5Categories::TOPS, 1, $this->expected(M5Categories::TOPS, ['Red'])],
            [M5Categories::TEES, 2, $this->expected(M5Categories::TEES, ['Red'])],
            [M5Categories::BOTTOMS, 1, $this->expected(M5Categories::BOTTOMS, ['Red'])],
        ], $this->rows($filter), 'the tree is counted under the colour, without the picks');
        $selected = array_values(array_filter($filter->getItems(), static fn ($item): bool => $item->isSelected()));
        self::assertSame([M5Categories::TEES, M5Categories::BOTTOMS], array_map(static fn ($i): string => (string) $i->getData('label'), $selected));
        self::assertStringContainsString('cat=' . self::$tree[M5Categories::BOTTOMS], $selected[0]->getRemoveUrl());
        self::assertCount(3, $layer->getState()->getFilters());

        $colours = [];
        foreach (M1Catalog::products() as $sku => $product) {
            if ($product['category'] && (M5Categories::inTree($sku, M5Categories::TEES) || M5Categories::inTree($sku, M5Categories::BOTTOMS))) {
                foreach ($product['colors'] as $colour) {
                    $colours[$colour] = ($colours[$colour] ?? 0) + 1;
                }
            }
        }
        $actual = [];
        foreach ($filters[M1Catalog::COLOR]->getItems() as $item) {
            $actual[(string) $item->getData('label')] = (int) $item->getData('count');
        }
        ksort($colours);
        ksort($actual);
        self::assertSame($colours, $actual, 'colours counted under the picks');
    }

    public function testACategoryOutsideThePageIsNoPick(): void
    {
        $outside = (int) Bootstrap::getObjectManager()->get(\Magento\Catalog\Model\ResourceModel\Category\CollectionFactory::class)
            ->create()->addAttributeToFilter('url_key', 'of-m1-outside')->getFirstItem()->getId();

        [$layer] = $this->layer(['cat' => (string) $outside]);

        self::assertSame(count($this->products([])), $layer->getProductCollection()->getSize());
        self::assertCount(0, $layer->getState()->getFilters());
    }

    public function testOneLevelHidesTheGrandchildren(): void
    {
        $this->category(Filter::DISPLAY_TREE, true, 1);

        [, , $filter] = $this->layer([]);

        self::assertSame([M5Categories::TOPS, M5Categories::BOTTOMS], array_column($this->rows($filter), 0));
    }

    public function testTheTreeIsCachedAndRebuiltWhenACategoryIsSaved(): void
    {
        $om = Bootstrap::getObjectManager();
        $tree = $om->get(Tree::class);
        $tree->reset();
        $tree->forStore(1);
        self::assertNotFalse($om->get(CacheType::class)->load('openfilter_category_tree_1'));

        $repository = $om->get(\Magento\Catalog\Api\CategoryRepositoryInterface::class);
        $bottoms = $repository->get(self::$tree[M5Categories::BOTTOMS], 0);
        $repository->save($bottoms->setName('Ofmfive Trousers'));
        try {
            self::assertFalse($om->get(CacheType::class)->load('openfilter_category_tree_1'), 'the category tag cleaned it');
            $tree->reset();
            self::assertSame('Ofmfive Trousers', $tree->forStore(1)[self::$tree[M5Categories::BOTTOMS]]['name']);
        } finally {
            $repository->save($repository->get(self::$tree[M5Categories::BOTTOMS], 0)->setName(M5Categories::BOTTOMS));
            $tree->reset();
        }
    }

    /**
     * Products of the page with one of the colours (all when none), by SKU.
     *
     * @param string[] $colours
     * @return string[]
     */
    private function products(array $colours): array
    {
        $skus = [];
        foreach (M1Catalog::products() as $sku => $product) {
            if ($product['category'] && ($colours === [] || array_intersect($product['colors'], $colours) !== [])) {
                $skus[] = $sku;
            }
        }

        return $skus;
    }

    /**
     * @param string $name
     * @param string[] $colours
     * @return int
     */
    private function expected(string $name, array $colours): int
    {
        return count(array_filter($this->products($colours), static fn (string $sku): bool => M5Categories::inTree($sku, $name)));
    }

    /**
     * @param AbstractFilter $filter
     * @return array<int, array{string, int, int}> label, depth, count
     */
    private function rows(AbstractFilter $filter): array
    {
        return array_map(static fn ($item): array => [
            (string) $item->getData('label'),
            (int) $item->getData('depth'),
            (int) $item->getData('count'),
        ], array_values($filter->getItems()));
    }

    private function category(string $mode, bool $multiselect, int $depth): void
    {
        $om = Bootstrap::getObjectManager();
        $repository = $om->get(FilterConfigRepositoryInterface::class);
        $repository->save($repository->getByCode(Filter::BUILTIN_CATEGORY)->setDisplayMode($mode)->setMultiselect($multiselect)->setTreeDepth($depth));
        $om->get(CacheType::class)->clean();
        $om->get(FilterConfigProvider::class)->reset();
        $om->get(UrlCodec::class)->reset();
        $om->get(Tree::class)->reset();
    }

    /**
     * @param array<string, string> $query
     * @return array{Layer, array<string, AbstractFilter>, AbstractFilter|null}
     */
    private function layer(array $query): array
    {
        $om = Bootstrap::getObjectManager();
        $om->removeSharedInstance(SearchCriteriaBuilder::class);
        $om->removeSharedInstance(Resolver::class);
        /** @var HttpRequest $request */
        $request = $om->get(RequestInterface::class);
        $request->setParams($query);
        $request->setQuery(new \Laminas\Stdlib\Parameters($query));
        $request->setRouteName('catalog')->setControllerName('category')->setActionName('view');
        $request->setAlias(UrlInterface::REWRITE_REQUEST_PATH_ALIAS, 'of-m1.html');
        $om->get(FilterStateProvider::class)->set(null);

        $resolver = $om->get(Resolver::class);
        $resolver->create(Resolver::CATALOG_LAYER_CATEGORY);
        $layer = $resolver->get();
        $layer->setCurrentCategory(self::$catalog['category']);
        $filters = [];
        $tree = null;
        foreach ($om->create('categoryFilterList')->getFilters($layer) as $filter) {
            $filter->apply($request);
            if ($filter instanceof CategoryTree) {
                $tree = $filter;
            } elseif ($filter->hasData('attribute_model')) {
                $filters[$filter->getAttributeModel()->getAttributeCode()] = $filter;
            }
        }
        $layer->apply();

        return [$layer, $filters, $tree];
    }
}
