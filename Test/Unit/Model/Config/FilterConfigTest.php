<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Config;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use PHPUnit\Framework\TestCase;

class FilterConfigTest extends TestCase
{
    public function testDatabaseStringsBecomeTypedValuesAndSurviveTheCacheRoundTrip(): void
    {
        $config = new FilterConfig([
            'code' => 'color', 'attribute_id' => '93', 'display_mode' => 'swatch', 'multiselect' => '1',
            'visible_options' => '4', 'expanded' => '0', 'position_override' => '', 'seo_indexable' => '1',
            'position' => '7', 'label' => 'Farbe', 'unknown' => 'ignored',
        ]);

        self::assertSame(93, $config->getAttributeId());
        self::assertTrue($config->isMultiselect());
        self::assertSame(4, $config->getVisibleOptions());
        self::assertFalse($config->getExpanded());
        self::assertNull($config->getPositionOverride());
        self::assertSame(7, $config->getPosition());
        self::assertSame('Farbe', $config->getLabel());
        self::assertArrayNotHasKey('unknown', $config->toArray());
        self::assertEquals($config, new FilterConfig(json_decode(json_encode($config->toArray()), true)));
    }

    public function testOverrideWinsOverTheAttributePositionAndEmptyLabelMeansDefault(): void
    {
        $config = (new FilterConfig(['position' => 30, 'label' => '']))->setPositionOverride(5);

        self::assertSame(5, $config->getPosition());
        self::assertNull($config->getLabel());
    }

    public function testSettersAndRowOf(): void
    {
        $config = (new FilterConfig(['code' => 'size']))
            ->setDisplayMode(Filter::DISPLAY_DROPDOWN)->setMultiselect(false)->setLogic('or')
            ->setVisibleOptions(0)->setSearchThreshold(20)->setExpanded(true)->setSortBy(Filter::SORT_COUNT)
            ->setHideSingleOption(false)->setSeoIndexable(true)->setPositionOverride(null)->setActive(false);

        self::assertSame([
            'display_mode' => 'dropdown', 'multiselect' => 0, 'logic' => 'or', 'visible_options' => 0,
            'search_threshold' => 20, 'expanded' => 1, 'sort_by' => 'count', 'hide_single_option' => 0,
            'seo_indexable' => 1, 'position_override' => null, 'ranges' => null, 'tree_depth' => 3,
        ], $config->toRow());
        self::assertFalse($config->isActive());
        self::assertSame(FilterConfig::BEHAVIOUR_COLUMNS, array_keys($config->toRow()));
        $builtin = (new FilterConfig(['code' => 'stock', 'builtin' => true, 'active' => false]))
            ->withBehaviourOf($config->setActive(true));
        self::assertTrue($builtin->isActive());
        self::assertSame('dropdown', $builtin->getDisplayMode());
        self::assertSame('stock', $builtin->getCode());
        $attribute = (new FilterConfig(['code' => 'color']))->withBehaviourOf($config->setActive(false));
        self::assertFalse($attribute->isActive(), 'attributes can be hidden too (M2)');
        self::assertNotSame($config, $config->with(['label' => 'x']));
        self::assertSame('x', $config->with(['label' => 'x'])->getLabel());
        self::assertFalse($config->isBuiltin());
        self::assertNull($config->getSwatchType());
        self::assertSame('select', $config->getInputType());
    }

    public function testRangesTreeDepthAndTooltipOf11(): void
    {
        $config = (new FilterConfig(['code' => 'price', 'builtin' => true, 'ranges' => ' ', 'tooltip' => '']))
            ->setRanges(' 0-50,50- ')->setTreeDepth(2);

        self::assertSame('0-50,50-', $config->getRanges());
        self::assertSame(2, $config->getTreeDepth());
        self::assertNull($config->getTooltip());
        self::assertNull($config->setRanges('')->getRanges());
        self::assertSame(3, (new FilterConfig())->getTreeDepth());
        self::assertSame('Hint', (new FilterConfig(['tooltip' => 'Hint']))->getTooltip());
        self::assertSame(['ranges' => null, 'tree_depth' => 2], array_intersect_key(
            (new FilterConfig(['code' => 'x']))->withBehaviourOf($config)->toRow(),
            ['ranges' => 1, 'tree_depth' => 1]
        ));
        self::assertSame('7', (string) (new FilterConfig(['tree_depth' => '7']))->getTreeDepth());
    }

    public function testWhereItShowsKeepsSortedUniqueIdsAndTravelsWithTheBehaviour(): void
    {
        $config = (new FilterConfig(['code' => 'color', 'category_ids' => ['21', 20, 21]]))->setCategoryMode('only');

        self::assertSame([20, 21], $config->getCategoryIds());
        self::assertSame([3, 21], $config->setCategoryIds([21, '3'])->getCategoryIds());
        $copy = (new FilterConfig(['code' => 'x']))->withBehaviourOf($config);
        self::assertSame(['only', [3, 21]], [$copy->getCategoryMode(), $copy->getCategoryIds()]);
        self::assertArrayNotHasKey('category_mode', $config->toRow(), 'stored in its own table');
        self::assertSame('all', (new FilterConfig())->getCategoryMode());
    }
}
