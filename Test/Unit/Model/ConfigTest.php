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
     * @return array<string, array{string, string, string, int|float|bool|string}>
     */
    public static function storefrontSettings(): array
    {
        return [
            'ajax' => ['isAjaxEnabled', 'getBool', 'openfilter/general/ajax', true],
            'expanded groups' => ['getExpandedGroups', 'getInt', 'openfilter/display/expanded_groups', 3],
            'price step' => ['getPriceStep', 'getFloat', 'openfilter/display/price_step', 0.5],
            'nofollow threshold' => ['getNofollowThreshold', 'getInt', 'openfilter/seo/nofollow_threshold', 2],
            'max active values' => ['getMaxActiveValues', 'getInt', 'openfilter/seo/max_active_values', 5],
            'sticky' => ['isSticky', 'getBool', 'openfilter/display/sticky', true],
            'title template' => ['getMetaTitleTemplate', 'getString', 'openfilter/seo/meta_title', '{filters} {category}'],
            'description template' => ['getMetaDescriptionTemplate', 'getString', 'openfilter/seo/meta_description', 'Shop {filters}'],
            'h1 template' => ['getH1Template', 'getString', 'openfilter/seo/h1', '{filters} {category}'],
            'sitemap' => ['isSitemapEnabled', 'getBool', 'openfilter/sitemap/enabled', true],
            'sitemap minimum' => ['getSitemapMinProducts', 'getInt', 'openfilter/sitemap/min_products', 3],
            'sitemap maximum' => ['getSitemapMaxUrls', 'getInt', 'openfilter/sitemap/max_urls', 5000],
            'horizontal layout' => ['getLayout', 'getString', 'openfilter/display/layout', 'horizontal'],
            'apply button' => ['getApplyMode', 'getString', 'openfilter/display/apply_mode', 'button'],
            'quick bar' => ['getQuickBarSize', 'getInt', 'openfilter/display/quick_bar_size', 8],
            'load more' => ['getPagination', 'getString', 'openfilter/display/pagination', 'load_more'],
            'infinite' => ['getPagination', 'getString', 'openfilter/display/pagination', 'infinite'],
            'insights' => ['isInsightsEnabled', 'getBool', 'openfilter/insights/enabled', true],
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
        int|float|bool|string $value
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
        self::assertSame(1, $config->getSitemapMinProducts(), 'at least one product');
        self::assertSame(0, $config->getSitemapMaxUrls());
        self::assertSame(0, $config->getQuickBarSize(), 'no bar');
    }

    public function testUnknownLayoutAndApplyModeFallBackTo10Behaviour(): void
    {
        $reader = $this->createMock(TypedReader::class);
        $reader->method('getString')->willReturn('carousel');
        $config = new Config($reader);

        self::assertSame(Config::LAYOUT_SIDEBAR, $config->getLayout());
        self::assertSame(Config::APPLY_INSTANT, $config->getApplyMode());
        self::assertSame(Config::PAGINATION_PAGES, $config->getPagination());
    }

    public function testTemplatesAreTrimmed(): void
    {
        $reader = $this->createMock(TypedReader::class);
        $reader->method('getString')->willReturn("  {category}\n");

        self::assertSame('{category}', (new Config($reader))->getH1Template());
    }

    public function testInsightRetentionIsGlobalAndAtLeastOneDay(): void
    {
        $reader = $this->createMock(TypedReader::class);
        $reader->expects(self::exactly(2))->method('getInt')
            ->with('openfilter/insights/retention_days', 'default', null)
            ->willReturnOnConsecutiveCalls(90, 0);
        $config = new Config($reader);

        self::assertSame(90, $config->getInsightsRetentionDays());
        self::assertSame(1, $config->getInsightsRetentionDays());
    }
}
