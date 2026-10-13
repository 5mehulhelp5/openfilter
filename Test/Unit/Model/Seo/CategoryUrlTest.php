<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Seo;

use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\Seo\CategoryUrl;
use Magento\Catalog\Model\Category;
use PHPUnit\Framework\TestCase;

class CategoryUrlTest extends TestCase
{
    public function testTheUrlRewriteIsReadOnceThenComesFromTheCacheTaggedWithTheCategory(): void
    {
        /** @var array<string, string> $store */
        $store = [];
        $cache = $this->createMock(CacheType::class);
        $cache->method('load')->willReturnCallback(static function (string $id) use (&$store) {
            return $store[$id] ?? false;
        });
        $cache->expects(self::once())->method('save')
            ->with('https://shop.test/women/tops.html', 'openfilter_category_url_1_12', ['cat_c_12'])
            ->willReturnCallback(static function (string $url, string $id) use (&$store): bool {
                $store[$id] = $url;

                return true;
            });
        $category = $this->createMock(Category::class);
        $category->method('getId')->willReturn(12);
        $category->expects(self::once())->method('getUrl')->willReturn('https://shop.test/women/tops.html?___store=x');

        $urls = new CategoryUrl($cache);

        self::assertSame('https://shop.test/women/tops.html', $urls->get($category, 1));
        self::assertSame('https://shop.test/women/tops.html', $urls->get($category, 1));
    }
}
