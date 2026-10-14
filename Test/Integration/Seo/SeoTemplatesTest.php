<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Seo;

use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Url\UrlCodec;
use Iranimij\OpenFilter\Test\Integration\Catalog\M1Catalog;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * M5 S7 title, meta description and H1 templates of filtered category pages, through the front controller.
 * Pages without filters keep Magento's. One dispatch per test (core keeps layout state per process).
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class SeoTemplatesTest extends AbstractController
{
    private const CATEGORY = 'OpenFilter M1';

    /** @var array{category: int, store: int, options: array<string, array<string, int>>}|null */
    private static ?array $catalog = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$catalog = M1Catalog::create();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $om = Bootstrap::getObjectManager();
        $om->removeSharedInstance(FilterConfigProvider::class);
        $om->get(UrlCodec::class)->reset();
    }

    /**
     * @magentoConfigFixture current_store openfilter/seo/meta_title {filters} {category} | {store}
     * @magentoConfigFixture current_store openfilter/seo/meta_description Shop {filters} {category} at {store}.
     * @magentoConfigFixture current_store openfilter/seo/h1 {filters} {category}
     */
    public function testFilteredPagesUseTheTemplates(): void
    {
        $this->open([M1Catalog::COLOR => 'red,blue']);

        self::assertSame('Red, Blue ' . self::CATEGORY . ' | Default Store View', $this->title());
        self::assertSame('Shop Red, Blue ' . self::CATEGORY . ' at Default Store View.', $this->description());
        self::assertSame('Red, Blue ' . self::CATEGORY, $this->heading());
    }

    /**
     * @magentoConfigFixture current_store openfilter/seo/meta_title {filters} {category} | {store}
     * @magentoConfigFixture current_store openfilter/seo/h1 {filters} {category}
     */
    public function testPagesWithoutFiltersKeepMagentosTitleAndHeading(): void
    {
        $this->open([]);

        self::assertSame(self::CATEGORY, $this->title());
        self::assertSame(self::CATEGORY, $this->heading());
    }

    /**
     * @magentoConfigFixture current_store openfilter/seo/meta_title {filters} {category}
     */
    public function testATitleTemplateLeavesTheHeadingAlone(): void
    {
        $this->open([M1Catalog::SIZE => 'm']);

        self::assertSame('M ' . self::CATEGORY, $this->title());
        self::assertSame(self::CATEGORY, $this->heading());
    }

    public function testNoTemplatesKeepMagentosTags(): void
    {
        $this->open([M1Catalog::COLOR => 'red']);

        self::assertSame(self::CATEGORY, $this->title());
        self::assertSame(self::CATEGORY, $this->heading());
    }

    /**
     * @param array<string, string> $query
     */
    private function open(array $query): void
    {
        $this->getRequest()->setMethod('GET')->setQueryValue($query);
        $this->dispatch('catalog/category/view/id/' . self::$catalog['category']);
        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
    }

    private function title(): string
    {
        return $this->match('#<title>([^<]*)</title>#');
    }

    private function description(): string
    {
        return $this->match('#<meta name="description" content="([^"]*)"#');
    }

    private function heading(): string
    {
        return trim($this->match('#data-ui-id="page-title-wrapper"[^>]*>([^<]*)<#'));
    }

    private function match(string $pattern): string
    {
        preg_match($pattern, (string) $this->getResponse()->getBody(), $match);

        return html_entity_decode($match[1] ?? '', ENT_QUOTES);
    }
}
