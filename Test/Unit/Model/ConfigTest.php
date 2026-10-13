<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model;

use Iranimij\Base\Model\Config\TypedReader;
use Iranimij\OpenFilter\Model\Config;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testEnabledIsReadPerStoreThroughTheBaseReader(): void
    {
        $reader = $this->createMock(TypedReader::class);
        $reader->expects(self::once())->method('getBool')
            ->with('openfilter/general/enabled', ScopeInterface::SCOPE_STORE, 3)
            ->willReturn(true);

        self::assertTrue((new Config($reader))->isEnabled(3));
    }

    public function testDebugIsAGlobalSetting(): void
    {
        $reader = $this->createMock(TypedReader::class);
        $reader->expects(self::once())->method('getBool')
            ->with('openfilter/general/debug', 'default', null)
            ->willReturn(false);

        self::assertFalse((new Config($reader))->isDebug());
    }
}
