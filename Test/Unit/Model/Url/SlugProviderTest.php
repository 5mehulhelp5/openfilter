<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Url;

use Iranimij\Base\Serializer\SafeJson;
use Iranimij\OpenFilter\Model\ResourceModel\FilterConfigStorage;
use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Iranimij\OpenFilter\Model\Url\SlugGenerator;
use Iranimij\OpenFilter\Model\Url\SlugProvider;
use Iranimij\OpenFilter\Model\Url\Transliterator;
use Magento\Framework\Cache\FrontendInterface;
use PHPUnit\Framework\TestCase;

class SlugProviderTest extends TestCase
{
    public function testBuildsWithCodeFallbacksThenServesFromCache(): void
    {
        $storage = $this->createMock(SlugStorage::class);
        $storage->expects(self::once())->method('attributeSlugsForStore')->with(2)->willReturn([93 => 'farbe']);
        $storage->expects(self::once())->method('optionSlugsForStore')->with(2)->willReturn([93 => [50 => 'rot']]);
        $configStorage = $this->createMock(FilterConfigStorage::class);
        $configStorage->expects(self::once())->method('filterableAttributes')->with(2)->willReturn([
            93 => ['attribute_id' => 93, 'code' => 'color', 'input_type' => 'select', 'swatch_type' => 'visual', 'position' => 1, 'label' => null],
            77 => ['attribute_id' => 77, 'code' => 'price', 'input_type' => 'decimal', 'swatch_type' => null, 'position' => 2, 'label' => null],
            150 => ['attribute_id' => 150, 'code' => 'new', 'input_type' => 'boolean', 'swatch_type' => null, 'position' => 3, 'label' => null],
            151 => ['attribute_id' => 151, 'code' => 'farbe', 'input_type' => 'select', 'swatch_type' => null, 'position' => 4, 'label' => null],
        ]);
        /** @var array<string, string> $data */
        $data = [];
        $cache = $this->createStub(FrontendInterface::class);
        $cache->method('load')->willReturnCallback(static function (string $key) use (&$data): string|false {
            return $data[$key] ?? false;
        });
        $cache->method('save')->willReturnCallback(static function (string $value, string $key) use (&$data): bool {
            $data[$key] = $value;

            return true;
        });
        $generator = new SlugGenerator(new Transliterator());

        $provider = new SlugProvider($storage, $configStorage, $generator, $cache, new SafeJson());
        $expected = ['attributes' => [93 => 'farbe', 150 => 'new-2', 151 => 'farbe-2'], 'options' => [93 => [50 => 'rot']]];
        self::assertSame($expected, $provider->forStore(2));
        self::assertSame($expected, $provider->forStore(2), 'process copy');

        $cached = $this->createStub(FrontendInterface::class);
        $cached->method('load')->willReturn($data['openfilter_slugs_2']);
        $fresh = new SlugProvider($storage, $configStorage, $generator, $cached, new SafeJson());
        self::assertSame($expected, $fresh->forStore(2), 'from the cache entry, tables untouched');
        $fresh->reset();
    }
}
