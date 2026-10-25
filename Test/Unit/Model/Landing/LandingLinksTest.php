<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Landing;

use Iranimij\OpenFilter\Model\Landing\CurrentPage;
use Iranimij\OpenFilter\Model\Landing\LandingLinks;
use Iranimij\OpenFilter\Model\Landing\LandingPage;
use Iranimij\OpenFilter\Model\Landing\LandingPages;
use Iranimij\OpenFilter\Model\Landing\LandingUrl;
use Iranimij\OpenFilter\Model\Seo\CategoryUrl;
use Iranimij\OpenFilter\Model\Url\FilterState;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Resolver;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class LandingLinksTest extends TestCase
{
    public function testALinkToALandingPagesStateGoesToTheLandingPageWithTheOtherParameters(): void
    {
        $links = $this->links();

        self::assertSame('https://shop/black-tops', $links->urlFor(new FilterState(['color' => ['49']]), [], 1));
        self::assertSame(
            'https://shop/black-tops?product_list_order=price&p=2',
            $links->urlFor(new FilterState(['color' => ['49']]), ['product_list_order' => 'price', 'p' => '2'], 1)
        );
        self::assertNull($links->urlFor(new FilterState(['color' => ['50']]), [], 1));
    }

    public function testOnALandingPageOtherLinksStartFromTheCategory(): void
    {
        self::assertNull($this->links()->base(1), 'not on a landing page');
        $current = new CurrentPage();
        $current->set(new LandingPage(['page_id' => 3]));
        self::assertSame('https://shop/women/tops-women.html', $this->links($current)->base(1));
    }

    private function links(?CurrentPage $current = null): LandingLinks
    {
        $page = new LandingPage(['page_id' => 3, 'url_key' => 'black-tops']);
        $pages = $this->createStub(LandingPages::class);
        $pages->method('forState')->willReturnCallback(
            static fn (int $category, array $filters): ?LandingPage => $category === 21 && $filters === ['color' => ['49']] ? $page : null
        );
        $url = $this->createStub(LandingUrl::class);
        $url->method('url')->willReturnCallback(static fn (LandingPage $page): string => 'https://shop/' . $page->getUrlKey());
        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(21);
        $layer = $this->createStub(Layer::class);
        $layer->method('getCurrentCategory')->willReturn($category);
        $resolver = $this->createStub(Resolver::class);
        $resolver->method('get')->willReturn($layer);
        $categoryUrl = $this->createStub(CategoryUrl::class);
        $categoryUrl->method('get')->willReturn('https://shop/women/tops-women.html');

        return new LandingLinks($current ?? new CurrentPage(), $pages, $url, $resolver, $categoryUrl);
    }
}
