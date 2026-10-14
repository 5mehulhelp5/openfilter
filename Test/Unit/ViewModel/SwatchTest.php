<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\ViewModel;

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

        $swatches = (new Swatch($data, $media))->forGroup($this->group(['49', '50', '51', '52', '53']));

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

        self::assertSame([], (new Swatch($data, $this->createStub(Media::class)))->forGroup($this->group([], null)));
    }

    /**
     * @param string[] $values
     */
    private function group(array $values, ?string $key = 'color'): FilterGroup
    {
        $options = array_map(
            static fn (string $value): FilterOption => new FilterOption("Option $value", $value, "/c?$value", 1, false),
            $values
        );

        return new FilterGroup('color', $key, 'Colour', 'swatch', true, true, 6, false, $options);
    }
}
