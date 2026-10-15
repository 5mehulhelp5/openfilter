<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Api;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface;
use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Api\FilterConfigManagement;
use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class FilterConfigManagementTest extends TestCase
{
    public function testOnlyTheSentSettingsChangeOnACopy(): void
    {
        $current = (new Defaults())->forAttribute('size', 144, 'select', 'text', 20)->setVisibleOptions(8)->setSeoIndexable(true);
        $saved = null;
        $repository = $this->createMock(FilterConfigRepositoryInterface::class);
        $repository->method('getByCode')->with('size')->willReturn($current);
        $repository->expects(self::once())->method('save')->willReturnCallback(static function (FilterConfigInterface $config) use (&$saved) {
            return $saved = $config;
        });
        $sent = (new FilterConfig())->setSortBy('name')->setCategoryIds([5, 3, 5]);

        (new FilterConfigManagement($repository))->update('size', $sent);

        self::assertNotSame($current, $saved, 'the cached object is left alone');
        self::assertSame('position', $current->getSortBy());
        self::assertSame(['size', 'name', [3, 5], 8, true, 'swatch'], [
            $saved->getCode(), $saved->getSortBy(), $saved->getCategoryIds(), $saved->getVisibleOptions(),
            $saved->isSeoIndexable(), $saved->getDisplayMode(),
        ]);
    }

    public function testAnotherImplementationReplacesTheBehaviour(): void
    {
        $current = (new Defaults())->forAttribute('size', 144, 'select', 'text', 20)->setVisibleOptions(8);
        $repository = $this->createStub(FilterConfigRepositoryInterface::class);
        $repository->method('getByCode')->willReturn($current);
        $repository->method('save')->willReturnArgument(0);
        $other = $this->createStub(FilterConfigInterface::class);
        $other->method('getDisplayMode')->willReturn('list');
        $other->method('getSortBy')->willReturn('count');
        $other->method('getLogic')->willReturn('or');
        $other->method('getCategoryMode')->willReturn('all');
        $other->method('getCategoryIds')->willReturn([]);

        $saved = (new FilterConfigManagement($repository))->update('size', $other);

        self::assertSame(['list', 'count', 0], [$saved->getDisplayMode(), $saved->getSortBy(), $saved->getVisibleOptions()]);
    }

    public function testFiltersThatCannotBeEditedAreRefused(): void
    {
        $repository = $this->createStub(FilterConfigRepositoryInterface::class);
        $repository->method('getByCode')->willReturn($this->createStub(FilterConfigInterface::class));

        $this->expectException(LocalizedException::class);
        (new FilterConfigManagement($repository))->update('size', new FilterConfig());
    }
}
