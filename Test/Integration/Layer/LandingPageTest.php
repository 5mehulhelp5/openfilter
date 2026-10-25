<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Layer;

use Iranimij\OpenFilter\Model\Landing\LandingPage;
use Iranimij\OpenFilter\Model\Landing\LandingPages;
use Iranimij\OpenFilter\Model\Sitemap\LandingPagesSitemap;
use Iranimij\OpenFilter\Test\Integration\Catalog\M1Catalog;
use Magento\Framework\Exception\InputException;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * M6 S7 landing pages through the front controller: the URL key serves the category with the page's filters, heading,
 * meta tags, robots, text and canonical URL; links and canonical URLs of the same state point to it; other store
 * views, disabled pages and taken URL keys do not; the XML sitemap lists the indexable ones. One dispatch per test.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class LandingPageTest extends AbstractController
{
    private const KEY = 'of-m1-red-things';

    /** @var array{category: int, store: int, options: array<string, array<string, int>>}|null */
    private static ?array $fixture = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$fixture = M1Catalog::create();
        self::removePages();
        $pages = Bootstrap::getObjectManager()->get(LandingPages::class);
        $page = [
            'category_id' => self::$fixture['category'],
            'filters' => [M1Catalog::COLOR => [(string) self::$fixture['options'][M1Catalog::COLOR]['Red']]],
        ];
        $pages->save(new LandingPage($page + [
            'url_key' => self::KEY, 'title' => 'Red things', 'h1' => 'All our red things', 'meta_title' => 'Red things | Shop',
            'meta_description' => 'Everything red.', 'description' => '<p class="of-landing-text">Red, {{store url=""}}</p>',
            'robots' => 'INDEX,FOLLOW', 'store_ids' => [0],
        ]));
        $pages->save(new LandingPage($page + [
            'url_key' => self::KEY . '-de', 'title' => 'Rote Sachen', 'robots' => 'NOINDEX,FOLLOW',
            'store_ids' => [self::$fixture['store']],
        ]));
        $pages->save(new LandingPage($page + ['url_key' => self::KEY . '-off', 'title' => 'Off', 'is_active' => false]));
    }

    public static function tearDownAfterClass(): void
    {
        self::removePages();
        parent::tearDownAfterClass();
    }

    public function testTheUrlKeyServesTheFilteredCategoryWithItsOwnHead(): void
    {
        $this->dispatch('/' . self::KEY);
        $html = (string) $this->getResponse()->getBody();

        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
        self::assertMatchesRegularExpression('#<title>\s*Red things \| Shop#', $html);
        self::assertMatchesRegularExpression('#<meta\s+name="description"\s+content="Everything red\."#', $html);
        self::assertMatchesRegularExpression('#<meta\s+name="robots"\s+content="INDEX,FOLLOW"#', $html);
        self::assertMatchesRegularExpression('#<link\s+rel="canonical"\s+href="[^"]*/' . self::KEY . '"#', $html);
        self::assertSame(1, preg_match_all('#rel="canonical"#', $html), 'one canonical');
        self::assertMatchesRegularExpression('#<h1[^>]*>\s*(<span[^>]*>)?\s*All our red things#', $html);
        self::assertMatchesRegularExpression('#class="of-landing-text">Red, http[^<]+/</p>#', $html, 'variables rendered');
        self::assertMatchesRegularExpression(
            '#href="[^"]*/of-m1\.html\?' . M1Catalog::COLOR . '=red(,|%2C)blue"#',
            $html,
            'other links start from the category'
        );
    }

    public function testLinksOfTheSameStatePointToTheLandingPage(): void
    {
        $this->dispatch('/of-m1.html');

        self::assertMatchesRegularExpression('#href="[^"]*/' . self::KEY . '"#', (string) $this->getResponse()->getBody());
    }

    public function testTheFilteredCategoryPageNamesTheLandingPageCanonical(): void
    {
        $this->getRequest()->setMethod('GET')->setQueryValue([M1Catalog::COLOR => 'red']);
        $this->dispatch('/of-m1.html');

        self::assertMatchesRegularExpression(
            '#<link\s+rel="canonical"\s+href="[^"]*/' . self::KEY . '"#',
            (string) $this->getResponse()->getBody()
        );
    }

    public function testAPageOfAnotherStoreViewIsNotFound(): void
    {
        $this->dispatch('/' . self::KEY . '-de');

        self::assertSame(404, $this->getResponse()->getHttpResponseCode());
    }

    public function testADisabledPageIsNotFound(): void
    {
        $this->dispatch('/' . self::KEY . '-off');

        self::assertSame(404, $this->getResponse()->getHttpResponseCode());
    }

    public function testTheSitemapListsIndexablePagesOfTheStoreView(): void
    {
        $sitemap = Bootstrap::getObjectManager()->get(LandingPagesSitemap::class);
        $urls = array_map(static fn ($item): string => (string) $item->getUrl(), $sitemap->getItems(1));

        self::assertContains(self::KEY, $urls);
        self::assertNotContains(self::KEY . '-off', $urls);
        self::assertSame([], array_values(array_filter(
            array_map(static fn ($item): string => (string) $item->getUrl(), $sitemap->getItems(self::$fixture['store'])),
            static fn (string $url): bool => str_starts_with($url, self::KEY . '-de')
        )), 'noindex pages are left out');
    }

    public function testAUrlKeyOfACategoryIsRefused(): void
    {
        $this->expectException(InputException::class);
        $this->expectExceptionMessageMatches('#already has the address "of-m1\.html"#');

        Bootstrap::getObjectManager()->get(LandingPages::class)->save(new LandingPage([
            'url_key' => 'of-m1.html', 'title' => 'Taken', 'category_id' => self::$fixture['category'],
            'filters' => [M1Catalog::COLOR => ['1']],
        ]));
    }

    private static function removePages(): void
    {
        $pages = Bootstrap::getObjectManager()->get(LandingPages::class);
        $pages->delete(array_map(
            static fn (LandingPage $page): int => (int) $page->getId(),
            array_filter($pages->getAll(), static fn (LandingPage $page): bool => str_starts_with($page->getUrlKey(), self::KEY))
        ));
    }
}
