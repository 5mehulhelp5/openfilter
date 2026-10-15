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
use Iranimij\OpenFilter\Test\Integration\Catalog\M1Catalog;
use Iranimij\OpenFilter\Test\Integration\Catalog\M5Categories;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * M5 S5 "Where it shows": a filter only in some categories, or in every category except some; a category counts with
 * the categories below it; search pages always show the filter.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 * @magentoCache openfilter enabled
 */
class FilterVisibilityTest extends TestCase
{
    /** @var array{category: int, store: int, options: array<string, array<string, int>>}|null */
    private static ?array $catalog = null;

    /** @var array<string, int>|null */
    private static ?array $tree = null;

    public static function setUpBeforeClass(): void
    {
        self::$catalog = M1Catalog::create();
        self::$tree = M5Categories::create(self::$catalog['category']);
    }

    protected function tearDown(): void
    {
        $this->rule(Filter::CATEGORIES_ALL, []);
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function pages(): array
    {
        return [
            'only Tops: on Tops' => [Filter::CATEGORIES_ONLY, M5Categories::TOPS, true],
            'only Tops: on Tees, below Tops' => [Filter::CATEGORIES_ONLY, M5Categories::TEES, true],
            'only Tops: on Bottoms' => [Filter::CATEGORIES_ONLY, M5Categories::BOTTOMS, false],
            'only Tops: on the parent' => [Filter::CATEGORIES_ONLY, 'parent', false],
            'except Tops: on Tees' => [Filter::CATEGORIES_EXCEPT, M5Categories::TEES, false],
            'except Tops: on Bottoms' => [Filter::CATEGORIES_EXCEPT, M5Categories::BOTTOMS, true],
        ];
    }

    /**
     * @dataProvider pages
     */
    #[DataProvider('pages')]
    public function testTheColourShowsWhereTheMerchantWantsIt(string $mode, string $page, bool $shown): void
    {
        $this->rule($mode, [self::$tree[M5Categories::TOPS]]);
        $category = $page === 'parent' ? self::$catalog['category'] : self::$tree[$page];

        self::assertSame($shown, in_array(M1Catalog::COLOR, $this->filterCodes(Resolver::CATALOG_LAYER_CATEGORY, $category), true));
    }

    public function testSearchPagesAlwaysShowTheFilter(): void
    {
        $this->rule(Filter::CATEGORIES_ONLY, [self::$tree[M5Categories::TOPS]]);

        self::assertContains(M1Catalog::COLOR, $this->filterCodes(Resolver::CATALOG_LAYER_SEARCH, null));
    }

    /**
     * @param string $mode
     * @param int[] $ids
     */
    private function rule(string $mode, array $ids): void
    {
        $om = Bootstrap::getObjectManager();
        $repository = $om->get(FilterConfigRepositoryInterface::class);
        $repository->save($repository->getByCode(M1Catalog::COLOR)->setCategoryMode($mode)->setCategoryIds($ids));
        $om->get(CacheType::class)->clean();
        $om->get(FilterConfigProvider::class)->reset();
    }

    /**
     * @return string[] attribute codes of the filters on the page
     */
    private function filterCodes(string $type, ?int $category): array
    {
        $om = Bootstrap::getObjectManager();
        $om->removeSharedInstance(SearchCriteriaBuilder::class);
        $om->removeSharedInstance(Resolver::class);
        /** @var HttpRequest $request */
        $request = $om->get(RequestInterface::class);
        $request->setParams([]);
        $search = $type === Resolver::CATALOG_LAYER_SEARCH;
        $request->setRouteName($search ? 'catalogsearch' : 'catalog')
            ->setControllerName($search ? 'result' : 'category')
            ->setActionName($search ? 'index' : 'view');
        $resolver = $om->get(Resolver::class);
        $resolver->create($type);
        $layer = $resolver->get();
        if ($category !== null) {
            $layer->setCurrentCategory($category);
        }
        $list = $om->create($type === Resolver::CATALOG_LAYER_SEARCH ? 'searchFilterList' : 'categoryFilterList');
        $codes = [];
        foreach ($list->getFilters($layer) as $filter) {
            if ($filter->hasData('attribute_model')) {
                $codes[] = (string) $filter->getAttributeModel()->getAttributeCode();
            }
        }

        return $codes;
    }
}
