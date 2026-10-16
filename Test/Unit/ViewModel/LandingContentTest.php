<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\ViewModel;

use Iranimij\OpenFilter\Model\Landing\CurrentPage;
use Iranimij\OpenFilter\Model\Landing\LandingPage;
use Iranimij\OpenFilter\ViewModel\LandingContent;
use Magento\Cms\Model\Template\Filter;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class LandingContentTest extends TestCase
{
    public function testTheTextIsFilteredLikeACmsBlockInTheStoreView(): void
    {
        $current = new CurrentPage();
        self::assertSame('', $this->content($current)->html(), 'not on a landing page');
        $current->set(new LandingPage(['description' => ' ']));
        self::assertSame('', $this->content($current)->html(), 'no text');
        $current->set(new LandingPage(['description' => '<p>{{store url=""}}</p>']));
        self::assertSame('<p>https://shop/</p> (store 2)', $this->content($current)->html());
    }

    private function content(CurrentPage $current): LandingContent
    {
        $storeId = 0;
        $filter = $this->createStub(Filter::class);
        $filter->method('setStoreId')->willReturnCallback(function (int $id) use (&$storeId, &$filter) {
            $storeId = $id;

            return $filter;
        });
        $filter->method('filter')->willReturnCallback(
            static function (string $html) use (&$storeId): string {
                return str_replace('{{store url=""}}', 'https://shop/', $html) . ' (store ' . $storeId . ')';
            }
        );
        $provider = $this->createStub(FilterProvider::class);
        $provider->method('getBlockFilter')->willReturn($filter);
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(2);
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);

        return new LandingContent($current, $provider, $stores);
    }
}
