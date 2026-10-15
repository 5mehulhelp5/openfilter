<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Transfer;

use Iranimij\OpenFilter\Model\Transfer\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SchemaTest extends TestCase
{
    private const FILTERS = [
        'color' => ['builtin' => false, 'options' => ['Red', 'Blue', 'Wool #1', 'Wool #2']],
        'rating' => ['builtin' => true, 'options' => []],
    ];

    private const STORES = ['admin', 'default', 'de'];

    public function testAValidFileHasNoErrors(): void
    {
        $file = ['schema' => 1, 'filters' => [
            'color' => [
                'settings' => ['display_mode' => 'swatch', 'multiselect' => true, 'visible_options' => 6,
                    'expanded' => null, 'position_override' => -2, 'active' => false],
                'labels' => ['de' => 'Farbe'],
                'url_name' => ['admin' => 'color', 'de' => 'farbe_x'],
                'options' => ['Red' => ['admin' => 'red', 'de' => 'rot'], 'Wool #2' => ['admin' => 'wool-2']],
            ],
            'rating' => ['settings' => ['seo_indexable' => true]],
        ]];

        self::assertSame([], (new Schema())->validate($file, self::FILTERS, self::STORES));
        self::assertSame([], (new Schema())->validate(['schema' => 1, 'filters' => []], self::FILTERS, self::STORES));
    }

    public function testVersionTwoAddsLogicRangesTreeDepthAndTooltipsAndOneStillReads(): void
    {
        $file = ['schema' => 2, 'filters' => [
            'color' => [
                'settings' => ['logic' => 'and', 'ranges' => null, 'tree_depth' => 3],
                'tooltips' => ['admin' => 'Pick all colours a product must have', 'de' => 'Alle gewählten Farben'],
            ],
            'rating' => ['settings' => ['ranges' => '0-50,50-']],
        ]];

        self::assertSame([], (new Schema())->validate($file, self::FILTERS, self::STORES));
        self::assertSame(2, Schema::VERSION);
        self::assertSame([1, 2], Schema::READS);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidFiles(): array
    {
        $filter = static fn (array $color): array => ['schema' => 1, 'filters' => ['color' => $color]];

        return [
            'not an object' => ['nonsense', 'not a JSON object'],
            'other schema' => [['schema' => 3, 'filters' => []], 'Unsupported schema "3" (this version reads 1 and 2)'],
            'tree depth as text' => [$filter(['settings' => ['tree_depth' => '3']]), 'tree_depth: expected int'],
            'tooltip for an unknown store view' => [$filter(['tooltips' => ['fr' => 'x']]), 'no store view "fr"'],
            'tooltip not text' => [$filter(['tooltips' => ['de' => 5]]), 'invalid value 5'],
            'filters as a list' => [['schema' => 1, 'filters' => [['code' => 'color']]], 'must be an object keyed by filter'],
            'unknown filter' => [['schema' => 1, 'filters' => ['ghost' => []]], 'ghost: no such filter'],
            'unknown section' => [$filter(['extra' => 1]), 'unknown section "extra"'],
            'unknown setting' => [$filter(['settings' => ['colour' => 'x']]), 'unknown setting "colour"'],
            'wrong type' => [$filter(['settings' => ['visible_options' => '6']]), 'visible_options: expected int'],
            'null where not allowed' => [$filter(['settings' => ['multiselect' => null]]), 'multiselect: expected bool'],
            'unknown store view' => [$filter(['labels' => ['fr' => 'Couleur']]), 'no store view "fr"'],
            'option URL name with an underscore' => [$filter(['options' => ['Red' => ['admin' => 'r_ed']]]), 'invalid value "r_ed"'],
            'parameter name in capitals' => [$filter(['url_name' => ['admin' => 'Color']]), 'invalid value "Color"'],
            'unknown option' => [$filter(['options' => ['Green' => ['admin' => 'green']]]), 'no option "Green"'],
            'ambiguous option label' => [$filter(['options' => ['Wool' => ['admin' => 'wool']]]), 'no option "Wool"'],
            'options on a built-in' => [['schema' => 1, 'filters' => ['rating' => ['options' => ['4' => ['admin' => 'x']]]]], 'built-in filters have no option'],
        ];
    }

    /**
     * @dataProvider invalidFiles
     */
    #[DataProvider('invalidFiles')]
    public function testInvalidFilesAreRefusedWithAReason(mixed $file, string $reason): void
    {
        $errors = (new Schema())->validate($file, self::FILTERS, self::STORES);

        self::assertNotEmpty($errors);
        self::assertStringContainsString($reason, implode("\n", $errors));
    }

    public function testRepeatedLabelsGetNumberedKeys(): void
    {
        self::assertSame(
            [10 => 'Wool #1', 11 => 'Cotton', 12 => 'Wool #2', 13 => '0'],
            (new Schema())->optionKeys([10 => 'Wool', 11 => 'Cotton', 12 => 'Wool', 13 => '0'])
        );
    }

    public function testDiffListsChangedAndNewValuesOfTheFiltersInTheFile(): void
    {
        $old = ['filters' => [
            'color' => ['settings' => ['visible_options' => 6, 'active' => true], 'options' => ['Red' => ['admin' => 'red']]],
            'size' => ['settings' => ['visible_options' => 6]],
        ]];
        $new = ['filters' => [
            'color' => ['settings' => ['visible_options' => 9, 'active' => true], 'labels' => ['de' => 'Farbe'],
                'options' => ['Red' => ['admin' => 'rot']]],
        ]];

        self::assertSame([
            'color.settings.visible_options: 6 → 9',
            'color.labels.de: null → "Farbe"',
            'color.options.Red.admin: "red" → "rot"',
        ], (new Schema())->diff($old, $new));
    }
}
