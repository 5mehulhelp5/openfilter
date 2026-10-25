<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Sitemap;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Landing\LandingPage;
use Iranimij\OpenFilter\Model\Landing\LandingPages;
use Iranimij\OpenFilter\Model\Sitemap\LandingPagesSitemap;
use Magento\Sitemap\Model\ItemProvider\ConfigReaderInterface;
use Magento\Sitemap\Model\SitemapItem;
use Magento\Sitemap\Model\SitemapItemInterfaceFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class LandingPagesSitemapTest extends TestCase
{
    public function testIndexablePagesAreListedWithTheCategorySettings(): void
    {
        $items = $this->sitemap(true)->getItems(1);

        self::assertCount(1, $items);
        self::assertSame('black-tops', $items[0]->getUrl());
        self::assertSame('0.5', $items[0]->getPriority());
        self::assertSame('daily', $items[0]->getChangeFrequency());
        self::assertSame('2026-10-16 10:00:00', $items[0]->getUpdatedAt());
        self::assertSame([], $this->sitemap(false)->getItems(1), 'OpenFilter is off');
    }

    private function sitemap(bool $on): LandingPagesSitemap
    {
        $pages = $this->createStub(LandingPages::class);
        $pages->method('forStore')->willReturn([
            new LandingPage(['url_key' => 'black-tops', 'updated_at' => '2026-10-16 10:00:00']),
            new LandingPage(['url_key' => 'hidden', 'robots' => 'NOINDEX,FOLLOW']),
        ]);
        $config = $this->createStub(Config::class);
        $config->method('isEnabled')->willReturn($on);
        $factory = $this->createStub(SitemapItemInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(static fn (array $data) => new SitemapItem(
            $data['url'],
            $data['priority'],
            $data['changeFrequency'],
            $data['updatedAt']
        ));
        $categoryConfig = $this->createStub(ConfigReaderInterface::class);
        $categoryConfig->method('getPriority')->willReturn('0.5');
        $categoryConfig->method('getChangeFrequency')->willReturn('daily');

        return new LandingPagesSitemap($pages, $config, $factory, $categoryConfig);
    }
}
