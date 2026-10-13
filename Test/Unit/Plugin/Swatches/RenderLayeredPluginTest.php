<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Plugin\Swatches;

use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Plugin\Swatches\RenderLayeredPlugin;
use Magento\Swatches\Block\LayeredNavigation\RenderLayered;
use PHPUnit\Framework\TestCase;

class RenderLayeredPluginTest extends TestCase
{
    public function testSwatchLinksBecomeToggleLinksForOpenFilterAttributes(): void
    {
        $context = $this->createStub(LayerContext::class);
        $context->method('isActive')->willReturn(true);
        $context->method('keyForAttributeCode')->willReturnMap([['color', 'color'], ['other', null]]);
        $context->method('toggleUrl')->willReturnCallback(static fn (string $k, string $v): string => "$k=$v");
        $plugin = new RenderLayeredPlugin($context);
        $subject = $this->createStub(RenderLayered::class);
        $proceed = static fn ($code, $id): string => "core:$code=$id";

        self::assertSame('color=50', $plugin->aroundBuildUrl($subject, $proceed, 'color', 50));
        self::assertSame('core:other=7', $plugin->aroundBuildUrl($subject, $proceed, 'other', 7));
    }
}
