<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Config;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Model\Config\CategoryRule;
use Iranimij\OpenFilter\Model\Config\Defaults;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CategoryRuleTest extends TestCase
{
    /**
     * @return array<string, array{string, int[], int[], bool}>
     */
    public static function rules(): array
    {
        return [
            'everywhere' => [Filter::CATEGORIES_ALL, [], [1, 2, 20, 21], true],
            'only: the category itself' => [Filter::CATEGORIES_ONLY, [21], [1, 2, 20, 21], true],
            'only: a category below it' => [Filter::CATEGORIES_ONLY, [20], [1, 2, 20, 21], true],
            'only: elsewhere' => [Filter::CATEGORIES_ONLY, [30], [1, 2, 20, 21], false],
            'except: below the excluded one' => [Filter::CATEGORIES_EXCEPT, [20], [1, 2, 20, 21], false],
            'except: elsewhere' => [Filter::CATEGORIES_EXCEPT, [30], [1, 2, 20, 21], true],
        ];
    }

    /**
     * @dataProvider rules
     * @param int[] $categories
     * @param int[] $path
     */
    #[DataProvider('rules')]
    public function testShowsIn(string $mode, array $categories, array $path, bool $expected): void
    {
        $filter = (new Defaults())->forAttribute('color', 93, 'select', 'visual', 10)->setCategoryMode($mode)->setCategoryIds($categories);

        self::assertSame($expected, (new CategoryRule())->showsIn($filter, $path));
    }
}
