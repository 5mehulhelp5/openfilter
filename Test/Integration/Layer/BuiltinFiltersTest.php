<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Layer;

use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Layer\Filter\Builtin;
use Iranimij\OpenFilter\Model\Url\FilterStateProvider;
use Iranimij\OpenFilter\Test\Integration\Catalog\M1Builtins;
use Iranimij\OpenFilter\Test\Integration\Search\EngineRequests;
use Magento\Catalog\Model\Indexer\Product\Price as PriceIndexer;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\UrlInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Rating, in stock, on sale and new: applied and counted in the same search request, each count ignoring its own
 * filter, checked against the catalog model; stock only with "display out of stock products" on.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class BuiltinFiltersTest extends TestCase
{
    private const SHOW_OUT_OF_STOCK = 'cataloginventory/options/show_out_of_stock';

    /** @var array{category: int, ids: array<int, int>} */
    private static array $catalog;

    private string $showOutOfStock = '1';

    public static function setUpBeforeClass(): void
    {
        $om = Bootstrap::getObjectManager();
        self::showOutOfStock('1');
        $repository = $om->get(FilterConfigRepositoryInterface::class);
        foreach (['rating', 'stock', 'sale', 'new'] as $code) {
            $repository->save($repository->getByCode($code)->setActive(true));
        }
        self::$catalog = M1Builtins::create();
    }

    public static function tearDownAfterClass(): void
    {
        $om = Bootstrap::getObjectManager();
        self::showOutOfStock('0');
        $repository = $om->get(FilterConfigRepositoryInterface::class);
        foreach (['rating', 'stock', 'sale', 'new'] as $code) {
            $repository->resetToDefault($code);
        }
    }

    public function testCountsWithoutFiltersInOneRequest(): void
    {
        $filters = [];
        $requests = EngineRequests::during(function () use (&$filters): void {
            [, $filters] = $this->layer([]);
            foreach ($filters as $filter) {
                $filter->getItems();
            }
        });

        self::assertSame(['rating', 'stock', 'sale', 'new'], array_keys($filters));
        self::assertSame($this->ratingCounts([]), $this->counts($filters['rating']));
        self::assertSame(['1' => $this->expected([], 'inStock')], $this->counts($filters['stock']));
        self::assertSame(['1' => $this->expected([], 'saleAll')], $this->counts($filters['sale']));
        self::assertSame(['1' => $this->expected([], 'new')], $this->counts($filters['new']));
        self::assertSame('In stock', (string) $filters['stock']->getName());
        self::assertSame(1, $requests, 'products, built-in counts and price bounds in one request');
    }

    public function testTogglesCombineAndEachCountIgnoresItsOwnFilter(): void
    {
        [$layer, $filters] = $this->layer(['stock' => '1', 'sale' => '1']);

        self::assertSame($this->expected(['inStock', 'saleAll']), $layer->getProductCollection()->getSize());
        self::assertSame(['1' => $this->expected(['saleAll'], 'inStock')], $this->counts($filters['stock']));
        self::assertSame(['1' => $this->expected(['inStock'], 'saleAll')], $this->counts($filters['sale']));
        self::assertCount(2, $layer->getState()->getFilters());
        self::assertStringContainsString('sale=1', $filters['stock']->getItems()[0]->getRemoveUrl());
    }

    public function testRatingAndNew(): void
    {
        [$layer, $filters] = $this->layer(['rating' => '4', 'new' => '1']);

        $expected = count(array_filter(M1Builtins::model(), static fn (array $p): bool => $p['rating'] >= 80 && $p['new']));
        self::assertSame($expected, $layer->getProductCollection()->getSize());
        self::assertSame($this->ratingCounts(['new']), $this->counts($filters['rating']));
        $newUnderRating = count(array_filter(M1Builtins::model(), static fn (array $p): bool => $p['rating'] >= 80 && $p['new']));
        self::assertSame(['1' => $newUnderRating], $this->counts($filters['new']));
        self::assertSame('4 stars & up', (string) $layer->getState()->getFilters()[0]->getData('label'));
    }

    public function testSaleFollowsTheCustomerGroup(): void
    {
        Bootstrap::getObjectManager()->get(CustomerSession::class)->setCustomerGroupId(M1Builtins::WHOLESALE);
        [, $filters] = $this->layer([]);

        self::assertSame(['1' => $this->expected([], 'saleWholesale')], $this->counts($filters['sale']));
    }

    public function testPartialPriceReindexUpdatesTheSaleFlag(): void
    {
        $om = Bootstrap::getObjectManager();
        $id = self::$catalog['ids'][2];
        $connection = $om->get(ResourceConnection::class)->getConnection();
        $attributeId = (int) $connection->fetchOne($connection->select()
            ->from($connection->getTableName('eav_attribute'), ['attribute_id'])
            ->where('attribute_code = ?', 'special_price')->where('entity_type_id = ?', 4));
        $connection->insertOnDuplicate($connection->getTableName('catalog_product_entity_decimal'), [
            'attribute_id' => $attributeId, 'store_id' => 0, 'entity_id' => $id, 'value' => 5.0,
        ], ['value']);
        $before = $this->counts($this->layer([])[1]['sale'])['1'];

        $om->get(PriceIndexer::class)->executeList([$id]);

        self::assertSame($before + 1, $this->counts($this->layer([])[1]['sale'])['1']);
        $connection->delete($connection->getTableName('catalog_product_entity_decimal'), [
            'attribute_id = ?' => $attributeId, 'entity_id = ?' => $id,
        ]);
        $om->get(PriceIndexer::class)->executeList([$id]);
    }

    public function testStockFilterDisappearsWhenOutOfStockProductsAreHidden(): void
    {
        $this->showOutOfStock = '0';
        self::showOutOfStock('0');
        M1Builtins::reindex();
        [$layer, $filters] = $this->layer(['stock' => '1']);

        self::assertArrayNotHasKey('stock', $filters);
        self::assertSame(M1Builtins::COUNT - count(M1Builtins::OUT_OF_STOCK), $layer->getProductCollection()->getSize());
    }

    /**
     * "Display out of stock products" for the default and the store scope (the app is reset between tests).
     *
     * @param string $value
     */
    private static function showOutOfStock(string $value): void
    {
        $config = Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class);
        $config->setValue(self::SHOW_OUT_OF_STOCK, $value);
        $config->setValue(self::SHOW_OUT_OF_STOCK, $value, 'store', 'default');
    }

    /**
     * @param string[] $flags model flags that must hold
     * @param string|null $counted flag counted on top
     * @return int
     */
    private function expected(array $flags, ?string $counted = null): int
    {
        return count(array_filter(M1Builtins::model(), static function (array $p) use ($flags, $counted): bool {
            foreach (array_merge($flags, $counted === null ? [] : [$counted]) as $flag) {
                if (!$p[$flag]) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @param string[] $flags
     * @return array<string, int> stars => products with at least that rating (non-zero only)
     */
    private function ratingCounts(array $flags): array
    {
        $counts = [];
        foreach ([4, 3, 2, 1] as $stars) {
            $count = count(array_filter(M1Builtins::model(), static function (array $p) use ($flags, $stars): bool {
                foreach ($flags as $flag) {
                    if (!$p[$flag]) {
                        return false;
                    }
                }

                return $p['rating'] >= $stars * 20;
            }));
            if ($count > 0) {
                $counts[(string) $stars] = $count;
            }
        }

        return $counts;
    }

    /**
     * @param AbstractFilter $filter
     * @return array<string, int>
     */
    private function counts(AbstractFilter $filter): array
    {
        $counts = [];
        foreach ($filter->getItems() as $item) {
            $counts[(string) $item->getData('value')] = (int) $item->getData('count');
        }

        return $counts;
    }

    /**
     * @param array<string, string> $query
     * @return array{Layer, array<string, Builtin>}
     */
    private function layer(array $query): array
    {
        $om = Bootstrap::getObjectManager();
        self::showOutOfStock($this->showOutOfStock);
        $om->removeSharedInstance(SearchCriteriaBuilder::class);
        $om->removeSharedInstance(Resolver::class);
        $om->get(FilterConfigProvider::class)->reset();
        /** @var HttpRequest $request */
        $request = $om->get(RequestInterface::class);
        $request->setParams($query);
        $request->setQuery(new \Laminas\Stdlib\Parameters($query));
        $request->setRouteName('catalog')->setControllerName('category')->setActionName('view');
        $request->setAlias(UrlInterface::REWRITE_REQUEST_PATH_ALIAS, 'of-m1-builtins.html');
        $om->get(FilterStateProvider::class)->set(null);

        $resolver = $om->get(Resolver::class);
        $resolver->create(Resolver::CATALOG_LAYER_CATEGORY);
        $layer = $resolver->get();
        $layer->setCurrentCategory(self::$catalog['category']);
        $filters = [];
        foreach ($om->create('categoryFilterList')->getFilters($layer) as $filter) {
            $filter->apply($request);
            if ($filter instanceof Builtin) {
                $filters[(string) $filter->getOpenFilterKey()] = $filter;
            }
        }
        $layer->apply();

        return [$layer, $filters];
    }
}
