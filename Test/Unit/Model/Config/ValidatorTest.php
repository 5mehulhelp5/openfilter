<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Config;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Config\Validator;
use Iranimij\OpenFilter\Model\Url\Range;
use Iranimij\OpenFilter\Model\Url\RangeList;
use Magento\Framework\Exception\InputException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ValidatorTest extends TestCase
{
    /**
     * @return array<string, array{array<string, mixed>, int, bool}>
     */
    public static function cases(): array
    {
        return [
            'swatch on a swatch attribute' => [['swatch_type' => 'visual', 'display_mode' => 'swatch'], 0, true],
            'swatch on a plain select' => [['display_mode' => 'swatch'], 0, false],
            'slider on a decimal' => [['input_type' => 'decimal', 'display_mode' => 'slider'], 0, true],
            'slider on a select' => [['display_mode' => 'slider'], 0, false],
            'toggle on a boolean' => [['input_type' => 'boolean', 'display_mode' => 'toggle'], 0, true],
            'toggle on a two-option select' => [['display_mode' => 'toggle'], 2, true],
            'toggle on a five-option select' => [['display_mode' => 'toggle'], 5, false],
            'rating on an attribute' => [['display_mode' => 'rating'], 0, false],
            'list on a multiselect' => [['input_type' => 'multiselect', 'display_mode' => 'list'], 0, true],
            'dropdown on a select' => [['display_mode' => 'dropdown'], 0, true],
            'list on a decimal' => [['input_type' => 'decimal', 'display_mode' => 'list'], 0, false],
            'unknown mode' => [['display_mode' => 'carousel'], 0, false],
            'and on a multi-select option filter' => [['logic' => 'and'], 0, true],
            'and on a single-select filter' => [['logic' => 'and', 'multiselect' => false], 0, false],
            'and on a decimal' => [['logic' => 'and', 'input_type' => 'decimal', 'display_mode' => 'slider'], 0, false],
            'and on a built-in' => [['builtin' => true, 'code' => 'stock', 'display_mode' => 'toggle', 'logic' => 'and'], 0, false],
            'unknown logic' => [['logic' => 'xor'], 0, false],
            'ranges on price' => [['builtin' => true, 'code' => 'price', 'display_mode' => 'ranges'], 0, true],
            'ranges on a decimal' => [['input_type' => 'decimal', 'display_mode' => 'ranges'], 0, true],
            'ranges on a select' => [['display_mode' => 'ranges'], 0, false],
            'merchant ranges' => [['builtin' => true, 'code' => 'price', 'display_mode' => 'ranges', 'ranges' => '-50, 50-100,100-'], 0, true],
            'overlapping ranges' => [['builtin' => true, 'code' => 'price', 'display_mode' => 'ranges', 'ranges' => '0-60,50-100'], 0, false],
            'open range in the middle' => [['builtin' => true, 'code' => 'price', 'display_mode' => 'ranges', 'ranges' => '0-50,50-,100-200'], 0, false],
            'not ranges' => [['builtin' => true, 'code' => 'price', 'display_mode' => 'ranges', 'ranges' => 'cheap'], 0, false],
            'category tree' => [['builtin' => true, 'code' => 'category', 'display_mode' => 'tree'], 0, true],
            'tree on an attribute' => [['display_mode' => 'tree'], 0, false],
            'tree depth 5' => [['builtin' => true, 'code' => 'category', 'display_mode' => 'tree', 'tree_depth' => 5], 0, true],
            'tree depth 6' => [['builtin' => true, 'code' => 'category', 'display_mode' => 'tree', 'tree_depth' => 6], 0, false],
            'only in categories' => [['category_mode' => 'only', 'category_ids' => [20]], 0, true],
            'except categories' => [['category_mode' => 'except', 'category_ids' => [20, 21]], 0, true],
            'only without categories' => [['category_mode' => 'only'], 0, false],
            'unknown category mode' => [['category_mode' => 'sometimes', 'category_ids' => [20]], 0, false],
            'all with leftover ids' => [['category_mode' => 'all', 'category_ids' => [20]], 0, true],
            'tree depth 0' => [['builtin' => true, 'code' => 'category', 'display_mode' => 'tree', 'tree_depth' => 0], 0, false],
            'unknown order' => [['sort_by' => 'random'], 0, false],
            'negative visible options' => [['visible_options' => -1], 0, false],
            'price slider' => [['builtin' => true, 'code' => 'price', 'display_mode' => 'slider'], 0, true],
            'price from-to' => [['builtin' => true, 'code' => 'price', 'display_mode' => 'from_to'], 0, true],
            'price list' => [['builtin' => true, 'code' => 'price', 'display_mode' => 'list'], 0, false],
            'category list' => [['builtin' => true, 'code' => 'category', 'display_mode' => 'list'], 0, true],
            'category swatch' => [['builtin' => true, 'code' => 'category', 'display_mode' => 'swatch'], 0, false],
            'rating stars' => [['builtin' => true, 'code' => 'rating', 'display_mode' => 'rating'], 0, true],
            'stock toggle' => [['builtin' => true, 'code' => 'stock', 'display_mode' => 'toggle'], 0, true],
            'sale list' => [['builtin' => true, 'code' => 'sale', 'display_mode' => 'list'], 0, false],
            'waits for another filter' => [['depends_on' => 'color', 'depends_values' => ['49']], 0, true],
            'waits for itself' => [['depends_on' => 'attr'], 0, false],
            'waits for itself by key' => [['code' => 'new', 'depends_on' => 'attribute:new'], 0, false],
            'values without a filter to wait for' => [['depends_values' => ['49']], 0, false],
            'quick bar' => [['quick_bar' => true], 0, true],
            'images on a plain select' => [['display_mode' => 'images'], 0, true],
            'images on a swatch attribute' => [['display_mode' => 'images', 'swatch_type' => 'visual'], 0, false],
            'images on a decimal' => [['display_mode' => 'images', 'input_type' => 'decimal'], 0, false],
        ];
    }

    /**
     * @dataProvider cases
     * @param array<string, mixed> $data
     */
    #[DataProvider('cases')]
    public function testValidate(array $data, int $optionCount, bool $valid): void
    {
        if (!$valid) {
            $this->expectException(InputException::class);
        }
        (new Validator(new RangeList(new Range())))->validate(new FilterConfig(array_replace(['code' => 'attr'], $data)), $optionCount);
        self::assertTrue($valid);
    }

    public function testAllProblemsAreReportedTogether(): void
    {
        try {
            (new Validator(new RangeList(new Range())))->validate(new FilterConfig([
                'code' => 'attr', 'display_mode' => Filter::DISPLAY_SWATCH, 'logic' => 'xor', 'search_threshold' => -2,
            ]));
            self::fail('expected an exception');
        } catch (InputException $e) {
            self::assertStringContainsString('does not fit', $e->getMessage());
            self::assertStringContainsString('"xor" is not a filter logic', $e->getMessage());
            self::assertStringContainsString('negative', $e->getMessage());
        }
    }

    public function testAllowedModesListOnlyWhatFits(): void
    {
        $defaults = new \Iranimij\OpenFilter\Model\Config\Defaults();
        $validator = new Validator(new RangeList(new Range()));

        self::assertSame(
            ['list', 'swatch', 'dropdown'],
            $validator->allowedModes($defaults->forAttribute('color', 93, 'select', 'visual', 10), 12)
        );
        self::assertSame(
            ['list', 'toggle', 'dropdown', 'images'],
            $validator->allowedModes($defaults->forAttribute('gift', 95, 'select', null, 10), 2)
        );
        self::assertSame(['slider', 'from_to', 'ranges'], $validator->allowedModes($defaults->forBuiltin('price')));
        self::assertSame(['list', 'tree'], $validator->allowedModes($defaults->forBuiltin('category')));
        self::assertSame(['list', 'rating'], $validator->allowedModes($defaults->forBuiltin('rating')));
        self::assertSame(['toggle'], $validator->allowedModes($defaults->forBuiltin('sale')));
        self::assertSame(['slider', 'from_to', 'ranges'], $validator->allowedModes($defaults->forAttribute('weight', 80, 'decimal', null, 10)));
    }
}
