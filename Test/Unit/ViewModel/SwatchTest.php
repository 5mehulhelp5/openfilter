<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\ViewModel;

use Iranimij\OpenFilter\Model\Config\OptionImages;
use Iranimij\OpenFilter\ViewModel\Data\FilterGroup;
use Iranimij\OpenFilter\ViewModel\Data\FilterOption;
use Iranimij\OpenFilter\ViewModel\Swatch;
use Magento\Swatches\Helper\Data as SwatchData;
use Magento\Swatches\Helper\Media;
use PHPUnit\Framework\TestCase;

class SwatchTest extends TestCase
{
    public function testColourImageAndTextSwatchesByOptionInOneLookup(): void
    {
        $data = $this->createMock(SwatchData::class);
        $data->expects(self::once())->method('getSwatchesByOptionsId')->with(['49', '50', '51', '52', '53'])->willReturn([
            49 => ['type' => '1', 'value' => '#000000'],
            50 => ['type' => '2', 'value' => '/b/l/blue.png'],
            51 => ['type' => '0', 'value' => 'XS'],
            52 => ['type' => '1', 'value' => 'red; background:url(x)'],
        ]);
        $media = $this->createStub(Media::class);
        $media->method('getSwatchAttributeImage')
            ->willReturnCallback(static fn (string $type, string $file): string => "https://m/$type$file");

        $swatches = (new Swatch($data, $media, $this->createStub(OptionImages::class)))->forGroup($this->group(['49', '50', '51', '52', '53']));

        self::assertSame(['type' => Swatch::COLOR, 'value' => '#000000'], $swatches['49']);
        self::assertSame(['type' => Swatch::IMAGE, 'value' => 'https://m/swatch_thumb/b/l/blue.png'], $swatches['50']);
        self::assertSame(['type' => Swatch::TEXT, 'value' => 'XS'], $swatches['51']);
        self::assertSame(['type' => Swatch::TEXT, 'value' => 'Option 52'], $swatches['52'], 'no CSS from data');
        self::assertSame(['type' => Swatch::TEXT, 'value' => 'Option 53'], $swatches['53'], 'no swatch: the label');
    }

    public function testGroupsWithoutAttributeOptionsNeedNoLookup(): void
    {
        $data = $this->createMock(SwatchData::class);
        $data->expects(self::never())->method('getSwatchesByOptionsId');

        self::assertSame([], (new Swatch($data, $this->createStub(Media::class), $this->createStub(OptionImages::class)))->forGroup($this->group([], null)));
    }

    public function testTheImagesDisplayUsesTheMerchantsImagesAndTextOtherwise(): void
    {
        $data = $this->createMock(SwatchData::class);
        $data->expects(self::never())->method('getSwatchesByOptionsId');
        $images = $this->createMock(OptionImages::class);
        $images->expects(self::once())->method('urls')->with(['60', '61'])->willReturn([60 => 'https://m/openfilter/options/c/o/cotton.png']);

        $swatches = (new Swatch($data, $this->createStub(Media::class), $images))->forGroup($this->group(['60', '61'], 'material', 'images'));

        self::assertSame(['type' => Swatch::IMAGE, 'value' => 'https://m/openfilter/options/c/o/cotton.png'], $swatches['60']);
        self::assertSame(['type' => Swatch::TEXT, 'value' => 'Option 61'], $swatches['61']);
    }

    /**
     * @param string[] $values
     */
    private function group(array $values, ?string $key = 'color', string $display = 'swatch'): FilterGroup
    {
        $options = array_map(
            static fn (string $value): FilterOption => new FilterOption("Option $value", $value, "/c?$value", 1, false),
            $values
        );

        return new FilterGroup('color', $key, 'Colour', $display, true, true, 6, false, $options);
    }
}
