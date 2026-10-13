<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Layer;

use Iranimij\OpenFilter\Api\SlugRepositoryInterface;
use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\Layer\Filter\Attribute as OpenFilterAttribute;
use Iranimij\OpenFilter\Model\Layer\Filter\Item;
use Iranimij\OpenFilter\Model\Url\FilterStateProvider;
use Iranimij\OpenFilter\Test\Integration\Search\SpikeCatalog;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class MultiValueFiltersTest extends TestCase
{
    private ObjectManagerInterface $om;

    /** @var array{category: int, options: array<string, array<string, int>>} */
    private static array $catalog;

    public static function setUpBeforeClass(): void
    {
        self::$catalog = SpikeCatalog::create();
        $om = Bootstrap::getObjectManager();
        foreach (array_keys(SpikeCatalog::OPTIONS) as $code) {
            $attributeId = (int) $om->get(ProductAttributeRepositoryInterface::class)->get($code)->getAttributeId();
            $om->get(SlugRepositoryInterface::class)->regenerate($attributeId);
        }
        $om->get(CacheType::class)->clean();
    }

    protected function setUp(): void
    {
        $this->om = Bootstrap::getObjectManager();
    }

    public function testTwoValuesNarrowByOrAndTheActiveFilterKeepsEveryOption(): void
    {
        [$layer, $filters] = $this->layer([SpikeCatalog::COLOR => 'red,blue']);
        $colors = self::$catalog['options'][SpikeCatalog::COLOR];

        $expected = count(array_filter(range(0, 35), static fn (int $i): bool => in_array($i % 4, [0, 1], true)));
        self::assertSame($expected, $layer->getProductCollection()->getSize());

        $color = $filters[SpikeCatalog::COLOR];
        self::assertInstanceOf(OpenFilterAttribute::class, $color);
        $items = $this->items($color);
        self::assertSame(['Red' => 9, 'Blue' => 9, 'Green' => 9, 'Black' => 9], array_map(
            static fn (Item $item): int => (int) $item->getData('count'),
            $items
        ), 'options of the active attribute are counted without its own filter');
        self::assertTrue($items['Red']->isSelected());
        self::assertFalse($items['Green']->isSelected());
        self::assertStringContainsString(SpikeCatalog::COLOR . '=red,blue,green', $items['Green']->getUrl());
        self::assertStringContainsString(SpikeCatalog::COLOR . '=blue', $items['Red']->getRemoveUrl());
        self::assertStringNotContainsString('red', $items['Red']->getRemoveUrl());
        self::assertSame((string) $colors['Red'], (string) $items['Red']->getData('value'));

        $sizes = [];
        foreach (range(0, 35) as $i) {
            if (in_array($i % 4, [0, 1], true)) {
                $label = SpikeCatalog::OPTIONS[SpikeCatalog::SIZE][($i * 7 + intdiv($i, 5)) % 3];
                $sizes[$label] = ($sizes[$label] ?? 0) + 1;
            }
        }
        $sizeCounts = array_map(static fn (Item $item): int => (int) $item->getData('count'), $this->items($filters[SpikeCatalog::SIZE]));
        ksort($sizes);
        ksort($sizeCounts);
        self::assertSame($sizes, $sizeCounts, 'other attributes are counted under the colour filter');

        $state = $layer->getState()->getFilters();
        self::assertCount(2, $state, 'one active entry per value');
    }

    public function testUnknownAndNumericValuesAndLinksWithSortKept(): void
    {
        $colors = self::$catalog['options'][SpikeCatalog::COLOR];
        [$layer, $filters] = $this->layer(
            [SpikeCatalog::COLOR => 'nonsense,' . $colors['Green'], 'product_list_order' => 'name', 'p' => '2']
        );

        self::assertSame(9, $layer->getProductCollection()->getSize());
        $url = $this->items($filters[SpikeCatalog::COLOR])['Black']->getUrl();
        self::assertStringContainsString(SpikeCatalog::COLOR . '=green,black&product_list_order=name', $url);
        self::assertStringNotContainsString('p=2', $url);
    }

    /**
     * @param array<string, string> $query
     * @return array{Layer, array<string, AbstractFilter>}
     */
    private function layer(array $query): array
    {
        /** @var HttpRequest $request */
        $request = $this->om->get(RequestInterface::class);
        $request->setParams($query);
        foreach ($query as $name => $value) {
            $request->setQueryValue($name, $value);
        }
        $request->setRouteName('catalog')->setControllerName('category')->setActionName('view');
        $request->setAlias(UrlInterface::REWRITE_REQUEST_PATH_ALIAS, 'of-spike.html');
        $this->om->get(FilterStateProvider::class)->set(null);

        $resolver = $this->om->get(Resolver::class);
        $resolver->create(Resolver::CATALOG_LAYER_CATEGORY);
        $layer = $resolver->get();
        $layer->setCurrentCategory(self::$catalog['category']);
        $filters = [];
        foreach ($this->om->create('categoryFilterList')->getFilters($layer) as $filter) {
            $filter->apply($request);
            if ($filter->hasData('attribute_model')) {
                $filters[$filter->getAttributeModel()->getAttributeCode()] = $filter;
            }
        }
        $layer->apply();

        return [$layer, $filters];
    }

    /**
     * @param AbstractFilter $filter
     * @return array<string, Item>
     */
    private function items(AbstractFilter $filter): array
    {
        $items = [];
        foreach ($filter->getItems() as $item) {
            $items[(string) $item->getData('label')] = $item;
        }

        return $items;
    }
}
