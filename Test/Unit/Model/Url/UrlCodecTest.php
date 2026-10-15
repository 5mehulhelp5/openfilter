<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Url;

use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Layer\CategoryTree;
use Iranimij\OpenFilter\Model\Url\FilterState;
use Iranimij\OpenFilter\Model\Url\Range;
use Iranimij\OpenFilter\Model\Url\SlugProvider;
use Iranimij\OpenFilter\Model\Url\UrlCodec;
use PHPUnit\Framework\TestCase;

class UrlCodecTest extends TestCase
{
    private UrlCodec $codec;

    protected function setUp(): void
    {
        $defaults = new Defaults();
        $configs = [
            'category' => $defaults->forBuiltin('category'),
            'color' => $defaults->forAttribute('color', 93, 'select', 'visual', 10),
            'size' => $defaults->forAttribute('size', 144, 'select', 'text', 20)->setMultiselect(false),
            'price' => $defaults->forBuiltin('price')->with(['attribute_id' => 77]),
            'weight' => $defaults->forAttribute('weight', 82, 'decimal', null, 40),
            'attribute:new' => $defaults->forAttribute('new', 150, 'boolean', null, 50),
            'rating' => $defaults->forBuiltin('rating')->setActive(true),
            'stock' => $defaults->forBuiltin('stock')->setActive(true),
        ];
        $configProvider = $this->createStub(FilterConfigProvider::class);
        $configProvider->method('getActive')->willReturnCallback(
            static fn (int $storeId): array => $storeId === 2
                ? array_diff_key($configs, ['stock' => true])
                : $configs
        );
        $slugProvider = $this->createStub(SlugProvider::class);
        $slugProvider->method('forStore')->willReturnCallback(static fn (int $storeId): array => [
            'attributes' => [93 => $storeId === 2 ? 'farbe' : 'color', 144 => 'size', 82 => 'weight', 150 => 'new-2'],
            'options' => [
                93 => $storeId === 2 ? [50 => 'rot', 49 => 'gruen', 51 => 'weiss'] : [50 => 'red', 49 => 'green', 51 => 'white'],
                144 => [167 => 's', 168 => 'm', 169 => 'l'],
            ],
        ]);
        $this->codec = new UrlCodec($configProvider, $slugProvider, new Range(), $this->createStub(CategoryTree::class));
    }

    public function testDecodesSlugsIdsRangesAndToggles(): void
    {
        $state = $this->codec->decode([
            'color' => 'white,RED,50,unknown,999',
            'size' => 'm,l',
            'price' => '80-20',
            'weight' => '-2.50',
            'new-2' => '1',
            'rating' => '9',
            'stock' => '1',
            'product_list_order' => 'price',
            'cat' => '12',
        ], 1);

        self::assertSame([
            'color' => ['51', '50'],
            'size' => ['168'],
            'price' => ['20-80'],
            'weight' => ['-2.5'],
            'attribute:new' => ['1'],
            'rating' => ['5'],
            'stock' => ['1'],
        ], $state->getValues());
        self::assertSame(8, $state->count(), 'color has two values');
    }

    public function testRejectsJunk(): void
    {
        $state = $this->codec->decode([
            'color' => ',,',
            'size' => ['bad' => ['nested']],
            'price' => 'abc',
            'new-2' => 'yes',
            'rating' => 'x',
            'stock' => '0',
            'weight' => '-',
        ], 1);

        self::assertSame([], $state->getValues());
    }

    public function testEncodeIsCanonicalAndRoundTrips(): void
    {
        $state = new FilterState([
            'stock' => ['1'],
            'color' => ['51', '49', '50', '777'],
            'price' => ['20-80'],
        ]);

        $params = $this->codec->encode($state, 1);

        self::assertSame(['color' => 'red,green,white,777', 'price' => '20-80', 'stock' => '1'], $params);
        self::assertSame(
            ['color' => 'red,green,white', 'price' => '20-80', 'stock' => '1'],
            $this->codec->encode($this->codec->decode($params, 1), 1),
            'encode(decode(x)) is canonical; unknown ids are dropped on decode'
        );
    }

    public function testStoreViewNamesAndInactiveFilters(): void
    {
        $state = $this->codec->decode(['farbe' => 'gruen%2Crot', 'color' => 'red', 'stock' => '1'], 2);
        self::assertSame([], $state->getValues(), '%2C is decoded by the request, not by the codec');

        $state = $this->codec->decode(['farbe' => 'gruen,rot', 'stock' => '1'], 2);
        self::assertSame(['color' => ['49', '50']], $state->getValues());
        self::assertSame(['farbe' => 'rot,gruen'], $this->codec->encode($state, 2));
        self::assertSame(
            ['farbe' => 'color', 'size' => 'size', 'price' => 'price', 'weight' => 'weight', 'new-2' => 'attribute:new',
                'rating' => 'rating'],
            $this->codec->getParameterNames(2)
        );
    }

    public function testUrlForKeepsOtherParametersAndCommas(): void
    {
        $state = new FilterState(['color' => ['50', '49'], 'size' => ['167']]);

        self::assertSame(
            'https://shop.test/women.html?color=red,green&size=s&product_list_order=price',
            $this->codec->urlFor($state, 'https://shop.test/women.html', ['product_list_order' => 'price', 'color' => 'x'], 1)
        );
        self::assertSame('https://shop.test/women.html', $this->codec->urlFor(new FilterState(), 'https://shop.test/women.html', [], 1));
        self::assertSame('https://shop.test/s?q=a&rating=4', $this->codec->urlFor(new FilterState(['rating' => ['4']]), 'https://shop.test/s?q=a', [], 1));
        $this->codec->reset();
        self::assertSame(['color' => 'red'], $this->codec->encode(new FilterState(['color' => ['50']]), 1));
    }

    public function testARangeListTakesSeveralRangesInCanonicalOrder(): void
    {
        $defaults = new Defaults();
        $configs = [
            'price' => $defaults->forBuiltin('price')->with(['attribute_id' => 77])
                ->setDisplayMode('ranges')->setMultiselect(true),
            'weight' => $defaults->forAttribute('weight', 82, 'decimal', null, 40)->setDisplayMode('ranges'),
        ];
        $configProvider = $this->createStub(FilterConfigProvider::class);
        $configProvider->method('getActive')->willReturn($configs);
        $slugProvider = $this->createStub(SlugProvider::class);
        $slugProvider->method('forStore')->willReturn(['attributes' => [82 => 'weight'], 'options' => []]);
        $codec = new UrlCodec($configProvider, $slugProvider, new Range(), $this->createStub(CategoryTree::class));

        $state = $codec->decode(['price' => '100-,50-100,0-50,50-100,cheap,-20', 'weight' => '2-3,1-2'], 1);

        self::assertSame(['-20', '0-50', '50-100', '100-'], $state->get('price'));
        self::assertSame(['1-2'], $state->get('weight'), 'one range when the filter is single-select');
        self::assertSame(['price' => '-20,0-50,50-100,100-', 'weight' => '1-2'], $codec->encode($state, 1));
        self::assertSame(
            ['price' => '0-50,100-', 'weight' => '1-2'],
            $codec->encode($state->with('price', ['100-', '0-50']), 1),
            'a range added at the end is put in order'
        );
    }

    public function testTheCategoryTreeUsesCatWithIdsInTreeOrder(): void
    {
        $defaults = new Defaults();
        $configProvider = $this->createStub(FilterConfigProvider::class);
        $configProvider->method('getActive')->willReturn([
            'category' => $defaults->forBuiltin('category')->setDisplayMode('tree')->setMultiselect(true),
        ]);
        $slugProvider = $this->createStub(SlugProvider::class);
        $slugProvider->method('forStore')->willReturn(['attributes' => [], 'options' => []]);
        $tree = $this->createStub(CategoryTree::class);
        $tree->method('order')->willReturn([20 => 0, 23 => 1, 21 => 2]);
        $codec = new UrlCodec($configProvider, $slugProvider, new Range(), $tree);

        $state = $codec->decode(['cat' => '21,99,abc,20,21'], 1);

        self::assertSame(['20', '21'], $state->get('category'), 'unknown ids dropped, tree order');
        self::assertSame(['cat' => '20,23,21'], $codec->encode($state->with('category', ['21', '23', '20']), 1));
        self::assertSame(['cat' => 'category'], $codec->getParameterNames(1));
    }
}
