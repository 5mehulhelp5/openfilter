<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\ViewModel;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Url\FilterState;
use Iranimij\OpenFilter\ViewModel\Insights;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Catalog\Model\Layer\Search as SearchLayer;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * M6 S9 storefront: insights only where OpenFilter and the setting are on; the page carries its store view,
 * category (0 on search pages) and filter state, nothing about the shopper.
 */
#[AllowMockObjectsWithoutExpectations]
class InsightsTest extends TestCase
{
    public function testOnlyWhereOpenFilterAndTheSettingAreOn(): void
    {
        self::assertTrue($this->viewModel(true, true)->isEnabled());
        self::assertFalse($this->viewModel(true, false)->isEnabled());
        self::assertFalse($this->viewModel(false, true)->isEnabled());
    }

    public function testTheEndpointIsTheStoreViewsBeaconAddress(): void
    {
        self::assertSame('https://shop.test/openfilter/insights/collect/', $this->viewModel(true, true)->endpoint());
    }

    public function testThePayloadNamesTheCategoryAndTheFilters(): void
    {
        self::assertSame(
            '{"s":"default","c":20,"f":{"color":["12"]}}',
            $this->viewModel(true, true)->payload()
        );
        self::assertSame('{"s":"default","c":0,"f":{}}', $this->viewModel(true, true, search: true, state: [])->payload());
    }

    /**
     * @param bool $openFilter
     * @param bool $insights
     * @param bool $search
     * @param array<string, string[]> $state
     * @return Insights
     */
    private function viewModel(bool $openFilter, bool $insights, bool $search = false, array $state = ['color' => ['12']]): Insights
    {
        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->with(1)->willReturn($openFilter);
        $config->method('isInsightsEnabled')->with(1)->willReturn($insights);
        $context = $this->createMock(LayerContext::class);
        $context->method('storeId')->willReturn(1);
        $context->method('state')->willReturn(new FilterState($state));
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn('20');
        $layer = $this->createMock($search ? SearchLayer::class : Layer::class);
        $layer->method('getCurrentCategory')->willReturn($category);
        $resolver = $this->createMock(Resolver::class);
        $resolver->method('get')->willReturn($layer);
        $store = $this->createMock(StoreInterface::class);
        $store->method('getCode')->willReturn('default');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $url = $this->createMock(UrlInterface::class);
        $url->method('getUrl')->with('openfilter/insights/collect', ['_nosid' => true])
            ->willReturn('https://shop.test/openfilter/insights/collect/');

        return new Insights($config, $context, $resolver, $storeManager, $url);
    }
}
