<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Config;

use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Config\FilterConfigEditor;
use Magento\Framework\Exception\InputException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FilterConfigEditorTest extends TestCase
{
    public function testPostedValuesBecomeSettings(): void
    {
        $config = (new Defaults())->forAttribute('color', 93, 'select', 'visual', 10);

        $edited = (new FilterConfigEditor())->apply($config, [
            'display_mode' => 'list',
            'multiselect' => '0',
            'visible_options' => '4',
            'search_threshold' => ' 8 ',
            'expanded' => '',
            'sort_by' => 'count',
            'hide_single_option' => 'false',
            'seo_indexable' => 'true',
            'active' => '0',
            'unknown' => 'ignored',
        ]);

        self::assertSame('list', $edited->getDisplayMode());
        self::assertFalse($edited->isMultiselect());
        self::assertSame(4, $edited->getVisibleOptions());
        self::assertSame(8, $edited->getSearchThreshold());
        self::assertNull($edited->getExpanded());
        self::assertSame('count', $edited->getSortBy());
        self::assertFalse($edited->isHideSingleOption());
        self::assertTrue($edited->isSeoIndexable());
        self::assertFalse($edited->isActive());
        self::assertTrue($config->isActive(), 'the original is not changed');
    }

    public function testExpandedKnowsOpenClosedAndAutomatic(): void
    {
        $config = (new Defaults())->forAttribute('color', 93, 'select', 'visual', 10);
        $editor = new FilterConfigEditor();

        self::assertTrue($editor->apply($config, ['expanded' => '1'])->getExpanded());
        self::assertFalse($editor->apply($config, ['expanded' => '0'])->getExpanded());
        self::assertNull($editor->apply($config, ['expanded' => 'auto'])->getExpanded());
    }

    public function testPositionOnlyBecomesAnOverrideWhenItChanges(): void
    {
        $config = (new Defaults())->forAttribute('color', 93, 'select', 'visual', 10);
        $editor = new FilterConfigEditor();

        self::assertNull($editor->apply($config, ['position' => '10'])->getPositionOverride(), 'unchanged grid value');
        self::assertSame(3, $editor->apply($config, ['position' => '3'])->getPositionOverride());
        self::assertSame(-2, $editor->apply($config, ['position_override' => '-2'])->getPositionOverride());
        self::assertNull(
            $editor->apply($config->with(['position_override' => 5]), ['position_override' => ''])->getPositionOverride(),
            'the form clears an override with an empty field'
        );
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function badValues(): array
    {
        return [
            'negative' => [['visible_options' => '-1'], 'Options before "Show more"'],
            'text' => [['search_threshold' => 'many'], 'Search box from'],
            'decimal' => [['visible_options' => '2.5'], 'Options before "Show more"'],
            'position text' => [['position' => 'top'], 'Position'],
        ];
    }

    /**
     * @param array<string, string> $values
     * @dataProvider badValues
     */
    #[DataProvider('badValues')]
    public function testNumbersAreChecked(array $values, string $field): void
    {
        $config = (new Defaults())->forAttribute('color', 93, 'select', 'visual', 10);

        $this->expectException(InputException::class);
        $this->expectExceptionMessage($field);

        (new FilterConfigEditor())->apply($config, $values);
    }

    public function test11SettingsFromTheForm(): void
    {
        $editor = new FilterConfigEditor();
        $config = new FilterConfig(['code' => 'color', 'category_mode' => 'only', 'category_ids' => [20]]);

        $edited = $editor->apply($config, [
            'logic' => 'and', 'ranges' => ' 0-50, 50- ', 'tree_depth' => '2',
            'category_mode' => 'except', 'category_ids' => ['21', 'x', '25'],
        ]);
        self::assertSame(['and', '0-50,50-', 2, 'except', [21, 25]], [
            $edited->getLogic(), $edited->getRanges(), $edited->getTreeDepth(), $edited->getCategoryMode(), $edited->getCategoryIds(),
        ]);
        self::assertSame([], $editor->apply($config, ['category_mode' => 'only'])->getCategoryIds(), 'every chip removed');
        self::assertSame([20], $editor->apply($config, ['visible_options' => '3'])->getCategoryIds(), 'untouched');
        self::assertNull($editor->apply($config, ['ranges' => ''])->getRanges());
    }
}
