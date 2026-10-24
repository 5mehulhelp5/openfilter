<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Migration;

use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Migration\Spellings;
use Iranimij\OpenFilter\Model\Migration\VendorModules;
use Iranimij\OpenFilter\Model\Migration\Vocabulary;
use Iranimij\OpenFilter\Model\Url\SlugProvider;
use Magento\Framework\Module\ModuleListInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class VocabularyTest extends TestCase
{
    public function testOptionFiltersWithEverySpellingOfTheirNames(): void
    {
        $defaults = new Defaults();
        $configs = $this->createStub(FilterConfigProvider::class);
        $configs->method('getActive')->willReturn([
            'price' => $defaults->forBuiltin('price'),
            'style_general' => $defaults->forAttribute('style_general', 93, 'select', null, 10),
            'width' => $defaults->forAttribute('width', 94, 'decimal', null, 20),
        ]);
        $slugs = $this->createMock(SlugProvider::class);
        $slugs->expects(self::once())->method('forStore')->willReturn([
            'attributes' => [93 => 'style'],
            'options' => [93 => [1 => 'heavy-duty', 2 => 'light']],
        ]);
        $vocabulary = new Vocabulary($configs, $slugs, new Spellings());

        $words = $vocabulary->forStore(1);

        self::assertSame(['style_general' => 'style'], $words['params'], 'built-ins and number filters are left out');
        self::assertSame(
            ['style_general', 'style-general', 'stylegeneral', 'style'],
            array_keys($words['attributes'])
        );
        self::assertSame(['heavy-duty', 'heavy_duty', 'heavyduty', 'light'], array_keys($words['options']));
        self::assertSame(['style_general' => 'heavy-duty'], $words['options']['heavyduty']);
        self::assertSame($words, $vocabulary->forStore(1), 'built once per store view');
    }

    public function testOtherFilterModulesByName(): void
    {
        $modules = $this->createStub(ModuleListInterface::class);
        $modules->method('getNames')->willReturn(['Mirasvit_SeoFilter', 'Magento_Catalog', 'Amasty_ShopbyBase', 'Amasty_Base']);

        self::assertSame(
            ['Amasty_ShopbyBase' => 'Amasty Improved Layered Navigation', 'Mirasvit_SeoFilter' => 'Mirasvit SEO Filters'],
            (new VendorModules($modules))->enabled()
        );
    }
}
