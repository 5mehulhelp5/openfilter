<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Url;

use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Url\SlugProvider;
use Iranimij\OpenFilter\Test\Integration\Catalog\M1Catalog;
use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Model\ResourceModel\Eav\AttributeFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\TestFramework\Helper\Bootstrap;
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

    /** A second filter with a Red option, made for one test and removed after it. */
    private const SHADE = 'of_m8_shade';

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
     * A second filter has a Red option too: without the attribute, "red" is not guessed.
     *
     * @magentoConfigFixture current_store openfilter/migration/redirects 1
     */
    public function testANameTwoFiltersShareStaysNotFound(): void
    {
        $this->shade();
        try {
            $this->dispatch('/of-m1/red.html');
        } finally {
            $this->removeShade();
        }

        self::assertSame(404, $this->getResponse()->getHttpResponseCode());
    }

    /**
     * A filterable attribute with a Red option, so two of the store's filters share that name.
     *
     * @return void
     */
    private function shade(): void
    {
        $om = Bootstrap::getObjectManager();
        /** @var Attribute $attribute */
        $attribute = $om->get(AttributeFactory::class)->create();
        $attribute->setData([
            'attribute_code' => self::SHADE,
            'entity_type_id' => (int) $om->get(EavConfig::class)->getEntityType('catalog_product')->getId(),
            'frontend_input' => 'select',
            'backend_type' => 'int',
            'frontend_label' => ['Shade'],
            'is_user_defined' => 1,
            'is_global' => 1,
            'is_filterable' => 1,
            'is_filterable_in_search' => 1,
            'option' => ['value' => ['option_0' => ['Red']]],
        ]);
        $attribute->save();
        $this->forget();
    }

    /**
     * @return void
     */
    private function removeShade(): void
    {
        $om = Bootstrap::getObjectManager();
        try {
            $om->get(ProductAttributeRepositoryInterface::class)->deleteById(self::SHADE);
        } catch (NoSuchEntityException) {
            // already gone
        }
        $this->forget();
    }

    /**
     * Drops the cached filters and URL names, so the next request reads the attributes again.
     *
     * @return void
     */
    private function forget(): void
    {
        $om = Bootstrap::getObjectManager();
        $om->get(EavConfig::class)->clear();
        $om->get(CacheType::class)->clean();
        $om->get(FilterConfigProvider::class)->reset();
        $om->get(SlugProvider::class)->reset();
    }

    private function assertRedirectTo(string $end): void
    {
        self::assertSame(301, $this->getResponse()->getHttpResponseCode());
        $location = (string) $this->getResponse()->getHeader('Location')->getFieldValue();
        self::assertStringEndsWith(str_replace(',', '%2C', $end), str_replace(',', '%2C', $location), $location);
    }
}
