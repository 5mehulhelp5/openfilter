<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Layer;

use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Layer\Filter\Decimal;
use Iranimij\OpenFilter\Model\Layer\Filter\Price;
use Iranimij\OpenFilter\Model\Url\FilterStateProvider;
use Iranimij\OpenFilter\Test\Integration\Catalog\M1Catalog;
use Iranimij\OpenFilter\Test\Integration\Search\EngineRequests;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Directory\Model\ResourceModel\Currency as CurrencyResource;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * Price and decimal sliders: min and max from the same search request, per customer group, without the slider's own
 * range, converted to the display currency; core's interval requests are gone.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class SlidersTest extends TestCase
{
    /** @var array{category: int, store: int, options: array<string, array<string, int>>} */
    private static array $catalog;

    public static function setUpBeforeClass(): void
    {
        self::$catalog = M1Catalog::create();
        Bootstrap::getObjectManager()->get(CacheType::class)->clean();
    }

    public function testGuestRangesComeFromOneSearchRequestWhereCoreNeedsMore(): void
    {
        $filters = [];
        $openFilter = EngineRequests::during(function () use (&$filters): void {
            [, $filters] = $this->layer([]);
            $filters['price']->getStats();
            $filters[M1Catalog::WIDTH]->getStats();
        });
        $this->enabled(false);
        $core = EngineRequests::during(function (): void {
            [, $coreFilters] = $this->layer([]);
            $coreFilters['price']->getItems();
        });
        $this->enabled(true);

        self::assertInstanceOf(Price::class, $filters['price']);
        self::assertInstanceOf(Decimal::class, $filters[M1Catalog::WIDTH]);
        self::assertSame(['min' => 10.0, 'max' => 56.0, 'count' => 28], $filters['price']->getStats());
        self::assertSame(['min' => 1.0, 'max' => 23.0, 'count' => 28], $filters[M1Catalog::WIDTH]->getStats());
        self::assertSame([], $filters['price']->getItems(), 'no active range: nothing to list until the slider template');
        self::assertSame(1, $openFilter, 'one search request for products, counts and both ranges');
        self::assertGreaterThan(1, $core, 'core re-queries for price intervals');
    }

    public function testWholesalePricesSetTheLowerBound(): void
    {
        Bootstrap::getObjectManager()->get(CustomerSession::class)->setCustomerGroupId(M1Catalog::WHOLESALE);
        [, $filters] = $this->layer([]);

        self::assertSame(8.0, $filters['price']->getStats()['min']);
        self::assertSame(56.0, $filters['price']->getStats()['max']);
    }

    public function testActiveRangeIsExcludedFromItsOwnBoundsOnly(): void
    {
        [$layer, $filters] = $this->layer(['price' => '20-30', M1Catalog::COLOR => 'red']);

        self::assertSame(1, $layer->getProductCollection()->getSize(), 'only red product 8 costs 26');
        self::assertSame(['min' => 10.0, 'max' => 52.0, 'count' => 8], $filters['price']->getStats(), 'red, any price');
        self::assertSame(['min' => 5.0, 'max' => 5.0, 'count' => 1], $filters[M1Catalog::WIDTH]->getStats());
        $items = $filters['price']->getItems();
        self::assertCount(1, $items);
        self::assertStringContainsString('20', (string) $items[0]->getData('label'));
        $remove = $items[0]->getRemoveUrl();
        self::assertStringContainsString(M1Catalog::COLOR . '=red', $remove);
        self::assertStringNotContainsString('price=', $remove);
        self::assertSame($remove, $items[0]->getUrl(), 'the selected range links to its removal');
    }

    public function testDecimalSliderFiltersAndKeepsItsFullRange(): void
    {
        [$layer, $filters] = $this->layer([M1Catalog::WIDTH => '5-10']);

        self::assertSame(11, $layer->getProductCollection()->getSize(), 'products 8 to 18 have widths 5 to 10');
        self::assertSame(['min' => 1.0, 'max' => 23.0, 'count' => 28], $filters[M1Catalog::WIDTH]->getStats());
        self::assertSame(['min' => 26.0, 'max' => 46.0, 'count' => 11], $filters['price']->getStats());
    }

    public function testGermanStoreViewShowsAndFiltersInEuros(): void
    {
        $om = Bootstrap::getObjectManager();
        $om->get(CurrencyResource::class)->saveRates(['USD' => ['EUR' => '0.9']]);
        $config = $om->get(MutableScopeConfigInterface::class);
        $config->setValue('currency/options/allow', 'USD,EUR', 'store', M1Catalog::STORE);
        $config->setValue('currency/options/default', 'EUR', 'store', M1Catalog::STORE);
        $om->get(StoreManagerInterface::class)->setCurrentStore(M1Catalog::STORE);

        [$layer, $filters] = $this->layer(['price' => '9-18']);

        self::assertSame(['min' => 9.0, 'max' => 51.0], array_slice($filters['price']->getStats(), 0, 2));
        self::assertSame(5, $layer->getProductCollection()->getSize(), '€9–18 = $10–20: products 0–5 without 3 (hidden)');
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

    private function enabled(bool $enabled): void
    {
        Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class)
            ->setValue(Config::XML_PATH_ENABLED, $enabled ? '1' : '0', 'store', 'default');
    }
}
