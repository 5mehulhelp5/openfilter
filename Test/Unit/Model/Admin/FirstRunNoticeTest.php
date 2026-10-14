<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Admin;

use Iranimij\OpenFilter\Model\Admin\FirstRunNotice;
use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Magento\Framework\FlagManager;
use Magento\Framework\Notification\MessageInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;

class FirstRunNoticeTest extends TestCase
{
    public function testShownUntilTheDefaultsAreReviewedAndCountsTheAttributeFilters(): void
    {
        $notice = $this->notice(null);

        self::assertTrue($notice->isDisplayed());
        self::assertSame('openfilter_first_run', $notice->getIdentity());
        self::assertSame(MessageInterface::SEVERITY_NOTICE, $notice->getSeverity());
        self::assertStringContainsString('OpenFilter found 2 filterable attributes', $notice->getText());
        self::assertStringContainsString('href="openfilter/filter/index/reviewed/1"', $notice->getText());
        self::assertStringContainsString('Rating, In stock, On sale and New are off', $notice->getText());
    }

    public function testHiddenOnceReviewed(): void
    {
        self::assertFalse($this->notice('1')->isDisplayed());
    }

    private function notice(?string $flag): FirstRunNotice
    {
        $defaults = new Defaults();
        $provider = $this->createStub(FilterConfigProvider::class);
        $provider->method('getForStore')->willReturn([
            'category' => $defaults->forBuiltin('category'),
            'color' => $defaults->forAttribute('color', 93, 'select', 'visual', 10),
            'size' => $defaults->forAttribute('size', 144, 'select', 'text', 20),
            'price' => $defaults->forBuiltin('price'),
        ]);
        $flags = $this->createStub(FlagManager::class);
        $flags->method('getFlagData')->willReturn($flag);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params = []): string => $route . '/reviewed/' . ($params['reviewed'] ?? '')
        );

        return new FirstRunNotice($provider, $flags, $url);
    }
}
