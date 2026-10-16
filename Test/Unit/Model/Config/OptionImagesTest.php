<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Config;

use Iranimij\Base\Serializer\SafeJson;
use Iranimij\OpenFilter\Model\Config\OptionImages;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class OptionImagesTest extends TestCase
{
    public function testAWarmCacheNeedsNoQueryAndUrlsUseTheMediaUrl(): void
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects(self::never())->method('getConnection');
        $cache = $this->createStub(FrontendInterface::class);
        $cache->method('load')->willReturn('{"60":"c/o/cotton.png"}');

        $images = new OptionImages($resource, $cache, new SafeJson(), $this->stores());

        self::assertSame([60 => 'c/o/cotton.png'], $images->paths());
        self::assertSame([60 => 'https://shop/media/openfilter/options/c/o/cotton.png'], $images->urls(['60', 61]));
        self::assertSame([], $images->urls([61]));
    }

    public function testAColdCacheReadsTheTableOnceAndStoresTheMap(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($this->createStub(Select::class));
        $connection->expects(self::once())->method('fetchPairs')->willReturn(['61' => 'w/o/wool.png']);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $cache = $this->createMock(FrontendInterface::class);
        $cache->method('load')->willReturn(false);
        $cache->expects(self::once())->method('save')->with('{"61":"w/o/wool.png"}', 'openfilter_option_images', ['OPENFILTER']);

        $images = new OptionImages($resource, $cache, new SafeJson(), $this->stores());

        self::assertSame([61 => 'w/o/wool.png'], $images->paths());
        self::assertSame([61 => 'w/o/wool.png'], $images->paths(), 'kept for the request');
    }

    public function testSaveWritesAndRemovesRowsAndForgetsTheMap(): void
    {
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects(self::once())->method('delete')->with('openfilter_option_image', ['option_id IN (?)' => [62]]);
        $connection->expects(self::once())->method('insertOnDuplicate')
            ->with('openfilter_option_image', [['option_id' => 61, 'image' => 'w/o/wool.png']], ['image']);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $cache = $this->createMock(FrontendInterface::class);
        $cache->expects(self::once())->method('remove')->with('openfilter_option_images');

        (new OptionImages($resource, $cache, new SafeJson(), $this->stores()))->save([61 => 'w/o/wool.png', 62 => null]);
    }

    private function stores(): StoreManagerInterface
    {
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop/media/');
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);

        return $stores;
    }
}
