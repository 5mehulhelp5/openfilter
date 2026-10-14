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
            'and logic (1.1)' => [['logic' => 'and'], 0, false],
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
        (new Validator())->validate(new FilterConfig(array_replace(['code' => 'attr'], $data)), $optionCount);
        self::assertTrue($valid);
    }

    public function testAllProblemsAreReportedTogether(): void
    {
        try {
            (new Validator())->validate(new FilterConfig([
                'code' => 'attr', 'display_mode' => Filter::DISPLAY_SWATCH, 'logic' => 'and', 'search_threshold' => -2,
            ]));
            self::fail('expected an exception');
        } catch (InputException $e) {
            self::assertStringContainsString('does not fit', $e->getMessage());
            self::assertStringContainsString('"or"', $e->getMessage());
            self::assertStringContainsString('negative', $e->getMessage());
        }
    }
}
