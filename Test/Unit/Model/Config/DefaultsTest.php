<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Config;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Model\Config\Defaults;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DefaultsTest extends TestCase
{
    /**
     * @return array<string, array{string, ?string, string, bool}>
     */
    public static function attributes(): array
    {
        return [
            'colour (visual swatch)' => ['select', 'visual', Filter::DISPLAY_SWATCH, true],
            'size (text swatch)' => ['select', 'text', Filter::DISPLAY_SWATCH, true],
            'decimal' => ['decimal', null, Filter::DISPLAY_SLIDER, false],
            'boolean' => ['boolean', null, Filter::DISPLAY_TOGGLE, false],
            'select' => ['select', null, Filter::DISPLAY_LIST, true],
            'multiselect' => ['multiselect', null, Filter::DISPLAY_LIST, true],
        ];
    }

    /**
     * @dataProvider attributes
     */
    #[DataProvider('attributes')]
    public function testAttributeDefaults(string $input, ?string $swatch, string $mode, bool $multiselect): void
    {
        $config = (new Defaults())->forAttribute('code', 12, $input, $swatch, 40);

        self::assertSame($mode, $config->getDisplayMode());
        self::assertSame($multiselect, $config->isMultiselect());
        self::assertSame(12, $config->getAttributeId());
        self::assertSame(40, $config->getPosition());
        self::assertTrue($config->isActive());
        self::assertFalse($config->isBuiltin());
    }

    public function testBuiltinsPriceAndCategoryOnTogglesOff(): void
    {
        $defaults = new Defaults();
        $active = [];
        foreach (Filter::BUILTIN_CODES as $code) {
            $config = $defaults->forBuiltin($code);
            self::assertTrue($config->isBuiltin());
            self::assertSame($code, $config->getCode());
            $active[$code] = $config->isActive();
        }

        self::assertSame(['rating' => false, 'stock' => false, 'sale' => false, 'new' => false, 'price' => true,
            'category' => true], $active);
        self::assertSame(Filter::DISPLAY_SLIDER, $defaults->forBuiltin('price')->getDisplayMode());
        self::assertSame(Filter::DISPLAY_RATING, $defaults->forBuiltin('rating')->getDisplayMode());
        self::assertLessThan(0, $defaults->forBuiltin('category')->getPosition());
        self::assertSame('In stock', $defaults->builtinLabel('stock'));
        self::assertSame('Category', $defaults->builtinLabel('category'));
        self::assertSame('Rating', $defaults->builtinLabel('rating'));
        self::assertSame('On sale', $defaults->builtinLabel('sale'));
        self::assertSame('New', $defaults->builtinLabel('new'));
        self::assertSame('Price', $defaults->builtinLabel('price'));
        self::assertSame('Other', $defaults->builtinLabel('other'));
    }

    public function testUnknownBuiltinIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Defaults())->forBuiltin('brand');
    }
}
