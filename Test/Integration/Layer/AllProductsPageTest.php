<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Layer;

use Iranimij\OpenFilter\Test\Integration\Catalog\M1Catalog;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * M6 S5 All Products page: off by default (404), on at its URL key with its title, canonical URL and the filters;
 * filter URLs stay on the URL key, non-canonical ones redirect there.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class AllProductsPageTest extends AbstractController
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        M1Catalog::create();
    }

    public function testOffByDefault(): void
    {
        $this->dispatch('/everything');

        self::assertSame(404, $this->getResponse()->getHttpResponseCode());
    }

    public function testTheUrlKeyShowsEveryProductWithTheFilters(): void
    {
        $this->switchOn();
        $this->dispatch('/everything');
        $html = (string) $this->getResponse()->getBody();

        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
        self::assertMatchesRegularExpression('#<title>\s*Shop everything#', $html);
        self::assertMatchesRegularExpression('#<link\s+rel="canonical"\s+href="[^"]*/everything"#', $html);
        self::assertSame(1, preg_match_all('#rel="canonical"#', $html), 'one canonical');
        self::assertMatchesRegularExpression('#href="[^"]*/everything\?' . M1Catalog::COLOR . '=red"#', $html, 'filter links keep the URL key');
    }

    public function testNonCanonicalFilterUrlsRedirectToTheUrlKey(): void
    {
        $this->switchOn();
        $this->getRequest()->setMethod('GET')->setQueryValue([M1Catalog::COLOR => 'RED']);
        $this->dispatch('/everything');

        self::assertSame(301, $this->getResponse()->getHttpResponseCode());
        self::assertStringEndsWith(
            '/everything?' . M1Catalog::COLOR . '=red',
            (string) $this->getResponse()->getHeader('Location')->getFieldValue()
        );
    }

    private function switchOn(): void
    {
        $config = Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class);
        $config->setValue('openfilter/all_products/enabled', '1', 'store', 'default');
        $config->setValue('openfilter/all_products/url_key', 'everything', 'store', 'default');
        $config->setValue('openfilter/all_products/title', 'Shop everything', 'store', 'default');
    }
}
