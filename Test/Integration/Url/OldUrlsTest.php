<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Url;

use Iranimij\OpenFilter\Test\Integration\Catalog\M1Catalog;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * M6 S8 old filter URLs of other modules: off by default (404); on, option names after a category path redirect (301)
 * to OpenFilter's URL of the same filters, in the store's URL format; an address with an unknown word, or with an
 * option name two filters share and no attribute before it, stays a 404.
 * One dispatch per test.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class OldUrlsTest extends AbstractController
{
    private const COLOR = M1Catalog::COLOR;
    private const SIZE = M1Catalog::SIZE;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        M1Catalog::create();
    }

    public function testOffByDefault(): void
    {
        $this->dispatch('/of-m1/red-blue.html');

        self::assertSame(404, $this->getResponse()->getHttpResponseCode());
    }

    /**
     * @magentoConfigFixture current_store openfilter/migration/redirects 1
     */
    public function testOptionNamesAfterTheCategoryRedirect(): void
    {
        $this->dispatch('/of-m1/shopby/' . self::COLOR . '_blue-red/' . self::SIZE . '-m.html');

        $this->assertRedirectTo('/of-m1.html?' . self::COLOR . '=red,blue&' . self::SIZE . '=m');
    }

    /**
     * @magentoConfigFixture current_store openfilter/migration/redirects 1
     */
    public function testAttributeCodesAndLongFormats(): void
    {
        $this->getRequest()->setMethod('GET')->setQueryValue(['product_list_order' => 'price']);
        $this->dispatch('/of-m1/' . self::COLOR . ':red,blue/' . self::SIZE . '_m.html');

        $this->assertRedirectTo('/of-m1.html?' . self::COLOR . '=red,blue&' . self::SIZE . '=m&product_list_order=price');
    }

    /**
     * @magentoConfigFixture current_store openfilter/migration/redirects 1
     * @magentoConfigFixture current_store openfilter/seo/url_format path
     */
    public function testPathFormatStores(): void
    {
        $this->dispatch('/of-m1/' . self::COLOR . '_red.html');

        $this->assertRedirectTo('/of-m1/' . self::COLOR . '-red.html');
    }

    /**
     * @magentoConfigFixture current_store openfilter/migration/redirects 1
     */
    public function testUnknownWordsStayNotFound(): void
    {
        $this->dispatch('/of-m1/' . self::COLOR . '_red-nonsense.html');

        self::assertSame(404, $this->getResponse()->getHttpResponseCode());
    }

    /**
     * Magento's own color attribute has a Red option too: without the attribute, "red" is not guessed.
     *
     * @magentoConfigFixture current_store openfilter/migration/redirects 1
     */
    public function testANameTwoFiltersShareStaysNotFound(): void
    {
        $this->dispatch('/of-m1/red.html');

        self::assertSame(404, $this->getResponse()->getHttpResponseCode());
    }

    private function assertRedirectTo(string $end): void
    {
        self::assertSame(301, $this->getResponse()->getHttpResponseCode());
        $location = (string) $this->getResponse()->getHeader('Location')->getFieldValue();
        self::assertStringEndsWith(str_replace(',', '%2C', $end), str_replace(',', '%2C', $location), $location);
    }
}
