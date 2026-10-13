<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Admin;

use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Api\SlugRepositoryInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Data\Form\FormKey;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The "OpenFilter" fieldset on core's product attribute page: shown for filters, saved with the attribute.
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation disabled
 * @magentoCache openfilter enabled
 */
class AttributeFieldsetTest extends AbstractBackendController
{
    private const CODE = 'of_m2_fieldset_attr';

    private ?int $attributeId = null;

    protected function setUp(): void
    {
        $this->resource = 'Magento_Catalog::attributes_attributes';
        $this->uri = 'backend/catalog/product_attribute/index';
        parent::setUp();
        $this->attributeId = AdminFixture::selectAttribute(self::CODE, ['One', 'Two', 'Three']);
    }

    protected function tearDown(): void
    {
        AdminFixture::deleteAttribute(self::CODE);
        AdminFixture::deleteAttribute(self::CODE . '_off');
        parent::tearDown();
    }

    public function testTheAttributePageShowsTheFieldsetWithTheCurrentSettings(): void
    {
        $this->dispatch('backend/catalog/product_attribute/edit/attribute_id/' . $this->attributeId);

        $body = $this->getResponse()->getBody();
        self::assertStringContainsString('openfilter_fieldset', $body);
        self::assertStringContainsString('name="openfilter[display_mode]"', $body);
        self::assertStringContainsString('openfilter/filter/edit/code/' . self::CODE, $body);
    }

    public function testNoFieldsetForAnAttributeThatIsNoFilter(): void
    {
        $id = AdminFixture::selectAttribute(self::CODE . '_off', ['A'], ['is_filterable' => 0, 'is_filterable_in_search' => 0]);

        $this->dispatch('backend/catalog/product_attribute/edit/attribute_id/' . $id);

        self::assertStringNotContainsString('openfilter_fieldset', $this->getResponse()->getBody());
    }

    public function testSavingTheAttributeSavesTheFieldset(): void
    {
        $this->save([
            'openfilter' => [
                'display_mode' => 'list', 'multiselect' => '0', 'visible_options' => '2', 'search_threshold' => '3',
                'url_name' => 'of-fieldset', 'seo_indexable' => '1',
            ],
        ]);

        $this->assertRedirect(self::stringContains('catalog/product_attribute'));
        AdminFixture::clean();
        $config = $this->_objectManager->get(FilterConfigRepositoryInterface::class)->getByAttributeId($this->attributeId);
        self::assertFalse($config->isMultiselect());
        self::assertSame(2, $config->getVisibleOptions());
        self::assertSame(3, $config->getSearchThreshold());
        self::assertTrue($config->isSeoIndexable());
        self::assertSame(
            'of-fieldset',
            $this->_objectManager->get(SlugRepositoryInterface::class)->getAttributeSlug($this->attributeId, 0)
        );
    }

    public function testWithoutTheFieldsetTheSettingsStay(): void
    {
        $repository = $this->_objectManager->get(FilterConfigRepositoryInterface::class);
        $repository->save($repository->getByAttributeId($this->attributeId)->setVisibleOptions(4));

        $this->save([]);

        AdminFixture::clean();
        self::assertSame(4, $repository->getByAttributeId($this->attributeId)->getVisibleOptions());
    }

    /**
     * @param array<string, mixed> $values
     * @return void
     */
    private function save(array $values): void
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST)->setPostValue($values + [
            'form_key' => $this->_objectManager->get(FormKey::class)->getFormKey(),
            'attribute_id' => $this->attributeId,
            'frontend_label' => ['Of m2 fieldset attr'],
            'is_filterable' => '1',
            'is_filterable_in_search' => '1',
            'is_searchable' => '0',
            'frontend_input' => 'select',
        ]);
        $this->dispatch('backend/catalog/product_attribute/save/attribute_id/' . $this->attributeId);
    }
}
