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
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
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

    /**
     * @return array<string, array{string, string, string, int|float|bool}>
     */
    public static function storefrontSettings(): array
    {
        return [
            'ajax' => ['isAjaxEnabled', 'getBool', 'openfilter/general/ajax', true],
            'expanded groups' => ['getExpandedGroups', 'getInt', 'openfilter/display/expanded_groups', 3],
            'price step' => ['getPriceStep', 'getFloat', 'openfilter/display/price_step', 0.5],
            'nofollow threshold' => ['getNofollowThreshold', 'getInt', 'openfilter/seo/nofollow_threshold', 2],
            'max active values' => ['getMaxActiveValues', 'getInt', 'openfilter/seo/max_active_values', 5],
        ];
    }

    /**
     * @dataProvider storefrontSettings
     */
    #[DataProvider('storefrontSettings')]
    public function testStorefrontSettingsAreReadPerStore(
        string $getter,
        string $readerMethod,
        string $path,
        int|float|bool $value
    ): void {
        $reader = $this->createMock(TypedReader::class);
        $reader->expects(self::once())->method($readerMethod)
            ->with($path, ScopeInterface::SCOPE_STORE, 2)
            ->willReturn($value);

        self::assertSame($value, (new Config($reader))->{$getter}(2));
    }

    public function testNegativeNumbersAreReadAsZero(): void
    {
        $reader = $this->createMock(TypedReader::class);
        $reader->method('getInt')->willReturn(-4);
        $reader->method('getFloat')->willReturn(-1.0);
        $config = new Config($reader);

        self::assertSame(0, $config->getMaxActiveValues());
        self::assertSame(0, $config->getExpandedGroups());
        self::assertSame(0.0, $config->getPriceStep());
    }
}
