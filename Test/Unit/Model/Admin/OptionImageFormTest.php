<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Admin;

use Iranimij\OpenFilter\Model\Admin\OptionImageForm;
use Iranimij\OpenFilter\Model\Config\OptionImages;
use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Iranimij\OpenFilter\Model\Admin\OptionImageUploader;
use PHPUnit\Framework\TestCase;

class OptionImageFormTest extends TestCase
{
    public function testRowsListEveryOptionWithItsImageInTheUploadersFormat(): void
    {
        $images = $this->createStub(OptionImages::class);
        $images->method('paths')->willReturn([60 => 'c/o/cotton.png']);
        $images->method('urls')->willReturn([60 => 'https://m/openfilter/options/c/o/cotton.png']);

        $rows = (new OptionImageForm($images, $this->labels(), $this->createStub(OptionImageUploader::class)))->rows(140);

        self::assertSame([
            ['option_id' => 60, 'option_label' => 'Cotton', 'image' => [[
                'name' => 'cotton.png', 'file' => 'c/o/cotton.png', 'url' => 'https://m/openfilter/options/c/o/cotton.png', 'type' => 'image',
            ]]],
            ['option_id' => 61, 'option_label' => 'Wool', 'image' => []],
            ['option_id' => 62, 'option_label' => 'Silk', 'image' => []],
        ], $rows);
    }

    public function testNewUploadsMoveIntoPlaceRemovedImagesGoAndOthersStay(): void
    {
        $images = $this->createMock(OptionImages::class);
        $images->method('paths')->willReturn([60 => 'c/o/cotton.png', 62 => 's/i/silk.png']);
        $images->expects(self::once())->method('save')->with([61 => 'w/o/wool_1.png', 62 => null]);
        $uploader = $this->createMock(OptionImageUploader::class);
        $uploader->expects(self::once())->method('moveFileFromTmp')->with('wool.png')->willReturn('/w/o/wool_1.png');

        (new OptionImageForm($images, $this->labels(), $uploader))->save(140, [
            ['option_id' => '60', 'image' => [['name' => 'cotton.png', 'file' => 'c/o/cotton.png']]],
            ['option_id' => '61', 'image' => [['name' => 'wool.png', 'tmp_name' => '/tmp/php1']]],
            ['option_id' => '62'],
            ['option_id' => '99', 'image' => [['name' => 'x.png', 'tmp_name' => '/tmp/php2']]],
            'junk',
        ]);
    }

    private function labels(): SlugStorage
    {
        $slugs = $this->createStub(SlugStorage::class);
        $slugs->method('optionLabels')->willReturn(['admin' => [60 => 'Cotton', 61 => 'Wool', 62 => 'Silk'], 'stores' => []]);

        return $slugs;
    }
}
