<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Setup\Patch\Data;

use Iranimij\OpenFilter\Setup\Patch\Data\EnableCacheType;
use Magento\Framework\App\Cache\Manager as CacheManager;
use PHPUnit\Framework\TestCase;

class EnableCacheTypeTest extends TestCase
{
    public function testEnablesTheOpenFilterCacheTypeWhenDeclared(): void
    {
        $manager = $this->createMock(CacheManager::class);
        $manager->method('getAvailableTypes')->willReturn(['config', 'openfilter']);
        $manager->expects(self::once())->method('setEnabled')->with(['openfilter'], true);

        $patch = new EnableCacheType($manager);

        self::assertSame($patch, $patch->apply());
        self::assertSame([], EnableCacheType::getDependencies());
        self::assertSame([], $patch->getAliases());
    }

    public function testDoesNothingBeforeTheTypeIsDeclared(): void
    {
        $manager = $this->createMock(CacheManager::class);
        $manager->method('getAvailableTypes')->willReturn(['config']);
        $manager->expects(self::never())->method('setEnabled');

        (new EnableCacheType($manager))->apply();
    }
}
