<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Config;

use Iranimij\Base\Serializer\SafeJson;
use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Config\FilterConfigStorage;
use Magento\Framework\Cache\FrontendInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FilterConfigProviderTest extends TestCase
{
    /** @var array<string, string> */
    private array $cacheData = [];

    private FilterConfigStorage&MockObject $storage;

    private FilterConfigProvider $provider;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(FilterConfigStorage::class);
        $cache = $this->createMock(FrontendInterface::class);
        $cache->method('load')->willReturnCallback(fn (string $key) => $this->cacheData[$key] ?? false);
        $cache->method('save')->willReturnCallback(function (string $data, string $key, array $tags): bool {
            self::assertSame([CacheType::CACHE_TAG], $tags);
            $this->cacheData[$key] = $data;

            return true;
        });
        $this->provider = new FilterConfigProvider($this->storage, new Defaults(), $cache, new SafeJson());
    }

    public function testBuildsOnMissThenServesFromCacheWithoutStorage(): void
    {
        $this->storage->expects(self::once())->method('filterableAttributes')->with(1)->willReturn([
            93 => ['attribute_id' => 93, 'code' => 'color', 'input_type' => 'select', 'swatch_type' => 'visual',
                'position' => 20, 'label' => 'Color'],
            77 => ['attribute_id' => 77, 'code' => 'price', 'input_type' => 'decimal', 'swatch_type' => null,
                'position' => 10, 'label' => 'Preis'],
            140 => ['attribute_id' => 140, 'code' => 'material', 'input_type' => 'multiselect', 'swatch_type' => null,
                'position' => 30, 'label' => 'Material'],
        ]);
        $this->storage->expects(self::once())->method('attributeRows')->willReturn([
            140 => ['attribute_id' => '140', 'display_mode' => 'list', 'multiselect' => '0', 'logic' => 'or',
                'visible_options' => '3', 'search_threshold' => '12', 'expanded' => null, 'sort_by' => 'name',
                'hide_single_option' => '1', 'seo_indexable' => '1', 'position_override' => '5'],
        ]);
        $this->storage->expects(self::once())->method('builtinRows')->willReturn([
            'stock' => ['code' => 'stock', 'is_active' => '1', 'display_mode' => 'toggle', 'multiselect' => '0',
                'logic' => 'or', 'visible_options' => '6', 'search_threshold' => '12', 'expanded' => null,
                'sort_by' => 'position', 'hide_single_option' => '1', 'seo_indexable' => '0',
                'position_override' => null],
        ]);
        $this->storage->expects(self::once())->method('labels')->with(1)
            ->willReturn(['attributes' => [93 => 'Farbe'], 'builtins' => ['stock' => 'Auf Lager']]);

        $configs = $this->provider->getForStore(1);

        self::assertSame(['category', 'material', 'price', 'color', 'rating', 'stock', 'sale', 'new'], array_keys($configs));
        self::assertSame('Farbe', $configs['color']->getLabel());
        self::assertSame('swatch', $configs['color']->getDisplayMode());
        self::assertSame(5, $configs['material']->getPosition());
        self::assertFalse($configs['material']->isMultiselect());
        self::assertTrue($configs['material']->isSeoIndexable());
        self::assertSame(77, $configs['price']->getAttributeId());
        self::assertSame('Preis', $configs['price']->getLabel());
        self::assertTrue($configs['price']->isBuiltin());
        self::assertTrue($configs['stock']->isActive());
        self::assertSame('Auf Lager', $configs['stock']->getLabel());
        self::assertNull($configs['rating']->getLabel());
        self::assertSame(['category', 'material', 'price', 'color', 'stock'],
            array_keys($this->provider->getActive(1)));

        $fresh = new FilterConfigProvider($this->storage, new Defaults(), $this->cachedFrontend(), new SafeJson());
        self::assertEquals($configs, $fresh->getForStore(1), 'served from the cache entry, storage not touched');
    }

    public function testResetForgetsTheProcessCopy(): void
    {
        $this->storage->expects(self::exactly(2))->method('filterableAttributes')->willReturn([]);
        $this->storage->method('attributeRows')->willReturn([]);
        $this->storage->method('builtinRows')->willReturn([]);
        $this->storage->method('labels')->willReturn(['attributes' => [], 'builtins' => []]);

        $this->provider->getForStore(0);
        $this->provider->getForStore(0);
        $this->cacheData = [];
        $this->provider->reset();
        $configs = $this->provider->getForStore(0);

        self::assertCount(6, $configs, 'built-ins only');
        self::assertNull($configs['price']->getAttributeId());
    }

    private function cachedFrontend(): FrontendInterface
    {
        $cache = $this->createStub(FrontendInterface::class);
        $cache->method('load')->willReturnCallback(fn (string $key) => $this->cacheData[$key] ?? false);

        return $cache;
    }
}
