<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\ViewModel;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Search\StrategyResolver;
use Iranimij\OpenFilter\Model\Url\FilterState;
use Iranimij\OpenFilter\ViewModel\Data\FilterGroup;
use Iranimij\OpenFilter\ViewModel\Data\FilterOption;
use Iranimij\OpenFilter\ViewModel\Debug;
use Magento\Framework\Escaper;
use PHPUnit\Framework\TestCase;

class DebugTest extends TestCase
{
    public function testAttributesOnlyInDebugMode(): void
    {
        $group = new FilterGroup('color', null, 'Color', 'swatch', true, true, 6, false, [], null);
        $option = new FilterOption('Red "dark"', '50', '/c?color=red', 7, false);

        self::assertSame('', $this->debug(false)->region());
        self::assertSame('', $this->debug(false)->group($group));
        self::assertSame('', $this->debug(false)->option($option));

        $debug = $this->debug(true);
        self::assertTrue($debug->isEnabled());
        self::assertSame(
            ' data-of-debug-strategy="single_query" data-of-debug-engine="opensearch" data-of-debug-values="2"',
            $debug->region()
        );
        self::assertSame(' data-of-debug-filter="color (core)" data-of-debug-display="swatch"', $debug->group($group));
        self::assertSame(' data-of-debug-option="50" data-of-debug-count="7"', $debug->option($option));
    }

    private function debug(bool $on): Debug
    {
        $config = $this->createStub(Config::class);
        $config->method('isDebug')->willReturn($on);
        $strategies = $this->createStub(StrategyResolver::class);
        $strategies->method('code')->willReturn('single_query');
        $strategies->method('engine')->willReturn('opensearch');
        $context = $this->createStub(LayerContext::class);
        $context->method('state')->willReturn(new FilterState(['color' => ['50', '49']]));
        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeHtmlAttr')->willReturnCallback(static fn ($v): string => htmlspecialchars((string) $v));

        return new Debug($config, $strategies, $context, $escaper);
    }
}
