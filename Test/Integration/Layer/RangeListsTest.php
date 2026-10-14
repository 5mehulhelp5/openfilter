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
use Iranimij\OpenFilter\Model\Search\StrategyResolver;
use Iranimij\OpenFilter\Model\Url\FilterStateProvider;
use Iranimij\OpenFilter\Model\Url\UrlCodec;
use Iranimij\OpenFilter\Test\Integration\Catalog\M1Catalog;
use Iranimij\OpenFilter\Test\Integration\Search\EngineRequests;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * M5 S3 price ranges as a list: picked ranges are [from, to) and combine with "any of", the ranges are counted in the
 * same search request without their own filter (disjunctive, like every facet), automatic ranges have round bounds
 * and add up to the result. Counts are checked against the catalog's PHP model, for both strategies.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 * @magentoCache openfilter enabled
 */
class RangeListsTest extends TestCase
{
    private const LIST = '10-30,30-50,50-';

    /** @var array{category: int, store: int, options: array<string, array<string, int>>}|null */
    private static ?array $catalog = null;

    public static function setUpBeforeClass(): void
    {
        self::$catalog = M1Catalog::create();
    }

    protected function setUp(): void
    {
        $this->price(Filter::DISPLAY_RANGES, self::LIST, true);
    }

    protected function tearDown(): void
    {
        $this->price(Filter::DISPLAY_SLIDER, null, false);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function strategies(): array
    {
        return ['single query' => [StrategyResolver::SINGLE_QUERY], 'per attribute' => [StrategyResolver::PER_ATTRIBUTE]];
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function testTheMerchantsRangesAreCountedInTheSameRequest(string $strategy): void
    {
        $this->strategy($strategy);
        $filters = [];
        $requests = EngineRequests::during(function () use (&$filters): void {
            [, $filters] = $this->layer([]);
            $filters['price']->getItems();
        });

        self::assertSame(1, $requests, 'no filter active: one request whatever the strategy');
        self::assertSame($this->expected(['10-30', '30-50', '50-'], []), $this->counts($filters['price']));
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function testPickedRangesCombineWithAnyOfAndCountWithoutTheirOwnFilter(string $strategy): void
    {
        $this->strategy($strategy);
        $layer = null;
        $filters = [];
        $requests = EngineRequests::during(function () use (&$layer, &$filters): void {
            [$layer, $filters] = $this->layer(['price' => '50-,10-30', M1Catalog::COLOR => 'red']);
            $filters['price']->getItems();
            $filters[M1Catalog::COLOR]->getItems();
        });

        self::assertSame($strategy === StrategyResolver::SINGLE_QUERY ? 1 : 3, $requests, 'one, or one per active filter');
        self::assertSame(count($this->matching(['10-30', '50-'], ['Red'])), $layer->getProductCollection()->getSize());
        self::assertSame(
            $this->expected(['10-30', '30-50', '50-'], ['Red']),
            $this->counts($filters['price']),
            'every range counted under the colour, without the price pick'
        );
        $colours = [];
        foreach ($this->matching(['10-30', '50-'], []) as $product) {
            foreach ($product['colors'] as $colour) {
                $colours[$colour] = ($colours[$colour] ?? 0) + 1;
            }
        }
        $actual = $this->counts($filters[M1Catalog::COLOR]);
        ksort($colours);
        ksort($actual);
        self::assertSame($colours, $actual, 'colours counted under the picked ranges');
        self::assertCount(3, $layer->getState()->getFilters(), 'two ranges and a colour, each removable');
        $picked = array_values(array_filter($filters['price']->getItems(), static fn ($item): bool => $item->isSelected()));
        self::assertSame(['10-30', '50-'], array_map(static fn ($item): string => (string) $item->getData('value'), $picked));
        self::assertStringContainsString('price=50-&', str_replace('%2C', ',', $picked[0]->getRemoveUrl()) . '&');
    }

    public function testRangesAreFromInclusiveToExclusive(): void
    {
        [$layer] = $this->layer(['price' => '20-30']);

        $prices = array_map(static fn (array $p): float => $p['price'], $this->matching(['20-30'], []));
        self::assertSame([20.0, 22.0, 24.0, 26.0, 28.0], array_values($prices), '30 belongs to the next range');
        self::assertSame(5, $layer->getProductCollection()->getSize());
    }

    public function testWholesalePricesDecideTheRange(): void
    {
        Bootstrap::getObjectManager()->get(CustomerSession::class)->setCustomerGroupId(M1Catalog::WHOLESALE);
        [, $filters] = $this->layer([]);

        self::assertSame($this->expected(['10-30', '30-50', '50-'], [], true), $this->counts($filters['price']));
    }

    public function testAutomaticRangesHaveRoundBoundsAndAddUpToTheResult(): void
    {
        $this->price(Filter::DISPLAY_RANGES, null, true);
        [$layer, $filters] = $this->layer([]);

        $counts = $this->counts($filters['price']);
        $ranges = ['10-20', '20-30', '30-40', '40-50', '50-'];
        self::assertSame($ranges, array_keys($counts), 'prices 10–56: about five ranges in steps of 10');
        self::assertSame($this->expected($ranges, []), $counts);
        self::assertSame($layer->getProductCollection()->getSize(), array_sum($counts));
    }

    public function testAutomaticRangesInTheGermanStoreViewAreInEuros(): void
    {
        $om = Bootstrap::getObjectManager();
        $om->get(\Magento\Directory\Model\ResourceModel\Currency::class)->saveRates(['USD' => ['EUR' => '0.9']]);
        $config = $om->get(MutableScopeConfigInterface::class);
        $config->setValue('currency/options/allow', 'USD,EUR', 'store', M1Catalog::STORE);
        $config->setValue('currency/options/default', 'EUR', 'store', M1Catalog::STORE);
        $om->get(StoreManagerInterface::class)->setCurrentStore(M1Catalog::STORE);
        $this->price(Filter::DISPLAY_RANGES, null, true);

        [$layer, $filters] = $this->layer([]);

        $counts = $this->counts($filters['price']);
        self::assertSame($layer->getProductCollection()->getSize(), array_sum($counts), 'every product in one range');
        foreach (array_keys($counts) as $range) {
            [$from] = explode('-', (string) $range);
            self::assertSame(0.0, fmod((float) $from, 10.0), "round bound $range");
        }
    }

    /**
     * Products of the category matching the picked ranges (any of) and colours (any of), by SKU.
     *
     * @param string[] $ranges
     * @param string[] $colours
     * @return array<string, array<string, mixed>>
     */
    private function matching(array $ranges, array $colours, bool $wholesale = false): array
    {
        return array_filter(M1Catalog::products(), function (array $product) use ($ranges, $colours, $wholesale): bool {
            if (!$product['category']) {
                return false;
            }
            $price = $wholesale ? $product['wholesale'] : $product['price'];
            $inRange = $ranges === [] || array_filter($ranges, fn (string $range): bool => $this->within($price, $range)) !== [];

            return $inRange && ($colours === [] || array_intersect($product['colors'], $colours) !== []);
        });
    }

    /**
     * @param string[] $ranges
     * @param string[] $colours
     * @return array<string, int> range => products, ranges without products left out
     */
    private function expected(array $ranges, array $colours, bool $wholesale = false): array
    {
        $counts = [];
        foreach ($ranges as $range) {
            $count = count($this->matching([$range], $colours, $wholesale));
            if ($count > 0) {
                $counts[$range] = $count;
            }
        }

        return $counts;
    }

    private function within(float $price, string $range): bool
    {
        [$from, $to] = array_map(static fn (string $bound): ?float => $bound === '' ? null : (float) $bound, explode('-', $range));

        return ($from === null || $price >= $from) && ($to === null || $price < $to);
    }

    /**
     * @return array<string, int> option value (price) or label (colour) => count
     */
    private function counts(AbstractFilter $filter): array
    {
        $counts = [];
        foreach ($filter->getItems() as $item) {
            $key = $filter->getRequestVar() === 'price' ? (string) $item->getData('value') : (string) $item->getData('label');
            $counts[$key] = (int) $item->getData('count');
        }

        return $counts;
    }

    private function price(string $mode, ?string $ranges, bool $multiselect): void
    {
        $om = Bootstrap::getObjectManager();
        $repository = $om->get(FilterConfigRepositoryInterface::class);
        $repository->save($repository->getByCode(Filter::BUILTIN_PRICE)->setDisplayMode($mode)->setRanges($ranges)->setMultiselect($multiselect));
        $om->get(CacheType::class)->clean();
        $om->get(FilterConfigProvider::class)->reset();
        $om->get(UrlCodec::class)->reset();
    }

    private function strategy(string $strategy): void
    {
        Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class)->setValue(StrategyResolver::XML_PATH_STRATEGY, $strategy);
    }

    /**
     * @param array<string, string> $query
     * @return array{Layer, array<string, AbstractFilter>}
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
        foreach ($om->create('categoryFilterList')->getFilters($layer) as $filter) {
            $filter->apply($request);
            if ($filter->hasData('attribute_model')) {
                $filters[$filter->getAttributeModel()->getAttributeCode()] = $filter;
            }
        }
        $layer->apply();

        return [$layer, $filters];
    }
}
