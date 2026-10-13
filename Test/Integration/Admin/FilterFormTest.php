<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Admin;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Api\SlugRepositoryInterface;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Message\MessageInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * The filter form: page, save (settings, labels, URL names; all or nothing) and reset.
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation disabled
 * @magentoCache openfilter enabled
 */
class FilterFormTest extends AbstractBackendController
{
    private const CODE = 'of_m2_form_attr';

    private int $attributeId;

    /** @var array<string, int> option admin label => id */
    private array $options = [];

    protected function setUp(): void
    {
        $this->resource = 'Iranimij_OpenFilter::filters';
        $this->uri = 'backend/openfilter/filter/edit/code/' . self::CODE;
        parent::setUp();
        $this->attributeId = AdminFixture::selectAttribute(self::CODE, ['Rot', 'Blau', 'Grün']);
        $this->_objectManager->get(SlugRepositoryInterface::class)->regenerate($this->attributeId);
        AdminFixture::clean();
        foreach ($this->_objectManager->get(SlugStorage::class)->optionLabels($this->attributeId)['admin'] as $id => $label) {
            $this->options[$label] = $id;
        }
    }

    protected function tearDown(): void
    {
        AdminFixture::deleteAttribute(self::CODE);
        AdminFixture::resetBuiltins();
        parent::tearDown();
    }

    public function testTheFormShowsTheFilterWithItsOptions(): void
    {
        $this->dispatch($this->uri);

        $body = $this->getResponse()->getBody();
        self::assertStringContainsString('Filter: Of m2 form attr', $body);
        self::assertStringContainsString('openfilter_filter_form', $body);
        self::assertStringContainsString('"url_name_0":"gruen"', $body);
        self::assertStringContainsString('"option_label":"Blau"', $body);
    }

    public function testAnUnknownFilterGoesBackToTheGrid(): void
    {
        $this->dispatch('backend/openfilter/filter/edit/code/no_such_filter');

        $this->assertRedirect(self::stringContains('openfilter/filter/index'));
        $this->assertSessionMessages(self::containsEqual('No filter with the code &quot;no_such_filter&quot;.'), MessageInterface::TYPE_ERROR);
    }

    public function testSaveWritesSettingsLabelsAndUrlNamesAndTheStorefrontSeesThem(): void
    {
        $storeId = $this->storeId();
        $this->save([
            'display_mode' => 'list',
            'multiselect' => '0',
            'visible_options' => '3',
            'search_threshold' => '2',
            'expanded' => '1',
            'sort_by' => 'name',
            'hide_single_option' => '0',
            'seo_indexable' => '1',
            'active' => '1',
            'position_override' => '-3',
            'label_0' => 'Farbton',
            'label_' . $storeId => 'Colour',
            'url_name' => 'farbton',
            'option_urls' => [
                ['option_id' => $this->options['Rot'], 'url_name_0' => 'blau', 'url_name_' . $storeId => 'red'],
                ['option_id' => $this->options['Blau'], 'url_name_0' => 'rot', 'url_name_' . $storeId => ''],
                ['option_id' => $this->options['Grün'], 'url_name_0' => 'x', 'regenerate' => '1'],
            ],
        ]);

        $this->assertSessionMessages(self::containsEqual('You saved the filter.'), MessageInterface::TYPE_SUCCESS);
        AdminFixture::clean();
        $config = $this->_objectManager->get(FilterConfigProvider::class)->getForStore($storeId)[self::CODE];
        self::assertSame(Filter::DISPLAY_LIST, $config->getDisplayMode());
        self::assertFalse($config->isMultiselect());
        self::assertSame(3, $config->getVisibleOptions());
        self::assertTrue($config->getExpanded());
        self::assertSame('name', $config->getSortBy());
        self::assertSame(-3, $config->getPosition());
        self::assertSame('Colour', $config->getLabel());
        self::assertSame('Farbton', $this->_objectManager->get(FilterConfigProvider::class)->getForStore(0)[self::CODE]->getLabel());

        $slugs = $this->_objectManager->get(SlugRepositoryInterface::class);
        self::assertSame('farbton', $slugs->getAttributeSlug($this->attributeId, $storeId));
        self::assertSame(
            [$this->options['Rot'] => 'red', $this->options['Blau'] => 'rot', $this->options['Grün'] => 'gruen'],
            $slugs->getOptionSlugs($this->attributeId, $storeId)
        );
        self::assertSame('blau', $slugs->getOptionSlugs($this->attributeId, 0)[$this->options['Rot']]);
    }

    public function testACollisionRollsTheWholeSaveBack(): void
    {
        $this->save([
            'display_mode' => 'list',
            'visible_options' => '1',
            'search_threshold' => '12',
            'label_0' => 'Should not stay',
            'option_urls' => [
                ['option_id' => $this->options['Rot'], 'url_name_0' => 'blau'],
            ],
        ]);

        $this->assertSessionMessages(
            self::containsEqual('Another option of this filter already uses the URL name &quot;blau&quot;.'),
            MessageInterface::TYPE_ERROR
        );
        $this->assertRedirect(self::stringContains('openfilter/filter/edit/code/' . self::CODE));
        $config = $this->freshRepository()->getByCode(self::CODE);
        self::assertSame(6, $config->getVisibleOptions(), 'settings rolled back');
        self::assertSame('Of m2 form attr', $config->getLabel(), 'label rolled back');
        self::assertSame('rot', $this->_objectManager->get(SlugRepositoryInterface::class)->getOptionSlugs($this->attributeId, 0)[$this->options['Rot']]);
    }

    public function testADisplayTypeThatDoesNotFitIsRefused(): void
    {
        $this->save(['display_mode' => 'slider']);

        $this->assertSessionMessages(self::containsEqual('The display type &quot;slider&quot; does not fit the filter &quot;' . self::CODE . '&quot;.'), MessageInterface::TYPE_ERROR);
    }

    public function testResetPutsTheFilterBack(): void
    {
        $repository = $this->freshRepository();
        $repository->save($repository->getByCode(self::CODE)->setVisibleOptions(1)->setActive(false));

        $this->getRequest()->setMethod(HttpRequest::METHOD_POST)->setPostValue(['form_key' => $this->formKey()]);
        $this->dispatch('backend/openfilter/filter/reset/code/' . self::CODE);

        $this->assertSessionMessages(self::containsEqual('The filter is back to its default settings.'), MessageInterface::TYPE_SUCCESS);
        $config = $this->freshRepository()->getByCode(self::CODE);
        self::assertSame(6, $config->getVisibleOptions());
        self::assertTrue($config->isActive());
    }

    /**
     * @param array<string, mixed> $values
     * @return void
     */
    private function save(array $values): void
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST)
            ->setPostValue(['key' => self::CODE, 'form_key' => $this->formKey()] + $values);
        $this->dispatch('backend/openfilter/filter/save');
    }

    private function formKey(): string
    {
        return $this->_objectManager->get(FormKey::class)->getFormKey();
    }

    private function storeId(): int
    {
        return (int) $this->_objectManager->get(StoreManagerInterface::class)->getStore('default')->getId();
    }

    private function freshRepository(): FilterConfigRepositoryInterface
    {
        AdminFixture::clean();

        return $this->_objectManager->get(FilterConfigRepositoryInterface::class);
    }
}
