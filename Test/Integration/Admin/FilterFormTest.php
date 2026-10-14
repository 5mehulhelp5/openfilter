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
use Iranimij\OpenFilter\Model\Url\OptionSlugBatch;
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

    private const STORE = 'of_s0_de';

    private ?int $attributeId = null;

    /** @var array<string, int> option admin label => id */
    private ?array $options = [];

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

    public static function tearDownAfterClass(): void
    {
        AdminFixture::deleteStoreView(self::STORE);
        parent::tearDownAfterClass();
    }

    public function testTheFormShowsTheFilterWithItsOptions(): void
    {
        $this->dispatch($this->uri);

        $body = $this->getResponse()->getBody();
        self::assertStringContainsString('Filter: Of m2 form attr', $body);
        self::assertStringContainsString('openfilter_filter_form', $body);
        self::assertStringContainsString('"url_name_0":"gruen"', $body);
        self::assertStringContainsString('"option_label":"Blau"', $body);
        self::assertStringContainsString('Why is this filter not showing?', $body);
        self::assertStringContainsString('data-of-check=\\"url_names\\"', $body);
        self::assertStringContainsString('"store":"0"', $body);
        self::assertStringContainsString('Reset to defaults', $body);
    }

    public function testTheWhyPanelIsPartOfTheFormAndAnswersAsJson(): void
    {
        $this->dispatch('backend/openfilter/filter/diagnose/code/' . self::CODE . '/store/' . $this->storeId());

        $checks = json_decode($this->getResponse()->getBody(), true)['checks'] ?? [];
        $codes = array_column($checks, 'code');
        self::assertSame(['enabled', 'coexistence', 'shown', 'category_pages', 'search_pages', 'url_names'], array_slice($codes, 0, 6));
        self::assertTrue($checks[0]['ok']);
    }

    public function testAnUnknownFilterGoesBackToTheGrid(): void
    {
        $this->dispatch('backend/openfilter/filter/edit/code/no_such_filter');

        $this->assertRedirect(self::stringContains('openfilter/filter/index'));
        $this->assertSessionMessages(self::containsEqual('No filter with the code &quot;no_such_filter&quot;.'), MessageInterface::TYPE_ERROR);
    }

    public function testSaveForAllStoreViewsWritesSettingsTheSharedLabelAndTheSharedNamesOnly(): void
    {
        $de = $this->germanStore();
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
            'label_' . $de => 'not for this scope',
            'url_name' => 'farbton',
            'option_urls' => [
                ['option_id' => $this->options['Rot'], 'url_name_0' => 'blau', 'url_name_' . $de => 'not-for-this-scope'],
                ['option_id' => $this->options['Blau'], 'url_name_0' => 'rot'],
                ['option_id' => $this->options['Grün'], 'url_name_0' => 'x', 'regenerate' => '1'],
            ],
        ]);

        $this->assertSessionMessages(self::containsEqual('You saved the filter.'), MessageInterface::TYPE_SUCCESS);
        $this->assertRedirect(self::logicalNot(self::stringContains('/store/')));
        AdminFixture::clean();
        $provider = $this->_objectManager->get(FilterConfigProvider::class);
        $config = $provider->getForStore($de)[self::CODE];
        self::assertSame(Filter::DISPLAY_LIST, $config->getDisplayMode());
        self::assertFalse($config->isMultiselect());
        self::assertSame(3, $config->getVisibleOptions());
        self::assertTrue($config->getExpanded());
        self::assertSame('name', $config->getSortBy());
        self::assertSame(-3, $config->getPosition());
        self::assertSame('Farbton', $config->getLabel(), 'the store view follows the shared label');
        self::assertSame('Farbton', $provider->getForStore(0)[self::CODE]->getLabel());

        $slugs = $this->_objectManager->get(SlugRepositoryInterface::class);
        self::assertSame('farbton', $slugs->getAttributeSlug($this->attributeId, $de));
        $expected = [$this->options['Rot'] => 'blau', $this->options['Blau'] => 'rot', $this->options['Grün'] => 'gruen'];
        self::assertSame($expected, $slugs->getOptionSlugs($this->attributeId, 0));
        self::assertSame($expected, $slugs->getOptionSlugs($this->attributeId, $de), 'no store view row written');
    }

    public function testSaveForAStoreViewWritesOnlyItsOwnLabelAndNames(): void
    {
        $de = $this->germanStore();
        $this->save([
            'store' => (string) $de,
            'display_mode' => 'slider',
            'visible_options' => '1',
            'label_0' => 'not for this scope',
            'label_' . $de => 'Farbe',
            'url_name' => 'not-for-this-scope',
            'url_name_store' => 'farbe',
            'option_urls' => [
                ['option_id' => $this->options['Rot'], 'url_name_0' => 'nope', 'url_name_' . $de => 'rot-de'],
                ['option_id' => $this->options['Blau'], 'url_name_0' => 'nope-2', 'url_name_' . $de => ''],
            ],
        ]);

        $this->assertSessionMessages(self::containsEqual('You saved the filter.'), MessageInterface::TYPE_SUCCESS);
        $this->assertRedirect(self::stringContains('openfilter/filter/edit/code/' . self::CODE . '/store/' . $de));
        $config = $this->freshRepository()->getByCode(self::CODE);
        self::assertSame(6, $config->getVisibleOptions(), 'settings are not edited in a store view');
        self::assertNotSame('slider', $config->getDisplayMode());
        $provider = $this->_objectManager->get(FilterConfigProvider::class);
        self::assertSame('Of m2 form attr', $provider->getForStore(0)[self::CODE]->getLabel());
        self::assertSame('Farbe', $provider->getForStore($de)[self::CODE]->getLabel());

        $slugs = $this->_objectManager->get(SlugRepositoryInterface::class);
        self::assertSame(self::CODE, $slugs->getAttributeSlug($this->attributeId, 0));
        self::assertSame('farbe', $slugs->getAttributeSlug($this->attributeId, $de));
        self::assertSame(
            [$this->options['Rot'] => 'rot', $this->options['Blau'] => 'blau', $this->options['Grün'] => 'gruen'],
            $slugs->getOptionSlugs($this->attributeId, 0)
        );
        self::assertSame(
            [$this->options['Rot'] => 'rot-de', $this->options['Blau'] => 'blau', $this->options['Grün'] => 'gruen'],
            $slugs->getOptionSlugs($this->attributeId, $de)
        );
    }

    public function testAnEmptyStoreViewUrlNameFollowsTheSharedNameAgain(): void
    {
        $de = $this->germanStore();
        $slugs = $this->_objectManager->get(SlugRepositoryInterface::class);
        $slugs->saveAttributeSlug($this->attributeId, $de, 'farbe');
        self::assertSame('farbe', $slugs->getAttributeSlug($this->attributeId, $de));

        $this->save(['store' => (string) $de, 'url_name_store' => '']);

        AdminFixture::clean();
        self::assertSame(self::CODE, $slugs->getAttributeSlug($this->attributeId, $de));
    }

    public function testACollisionInAStoreViewIsRefusedAndNothingIsSaved(): void
    {
        $de = $this->germanStore();
        $this->save([
            'store' => (string) $de,
            'label_' . $de => 'Should not stay',
            'option_urls' => [
                ['option_id' => $this->options['Rot'], 'url_name_' . $de => 'gleich'],
                ['option_id' => $this->options['Blau'], 'url_name_' . $de => 'gleich'],
            ],
        ]);

        $this->assertSessionMessages(
            self::containsEqual('Another option of this filter already uses the URL name &quot;gleich&quot;.'),
            MessageInterface::TYPE_ERROR
        );
        $this->assertRedirect(self::stringContains('/store/' . $de));
        AdminFixture::clean();
        self::assertSame('Of m2 form attr', $this->_objectManager->get(FilterConfigProvider::class)->getForStore($de)[self::CODE]->getLabel());
        self::assertSame('rot', $this->_objectManager->get(SlugRepositoryInterface::class)->getOptionSlugs($this->attributeId, $de)[$this->options['Rot']]);
    }

    public function testGenerateAgainInAStoreViewTouchesOnlyThatStoreView(): void
    {
        $de = $this->germanStore();
        $this->_objectManager->get(OptionSlugBatch::class)
            ->save($this->attributeId, [$this->options['Grün'] => [0 => 'eigenes-gruen']]);

        $this->save([
            'store' => (string) $de,
            'option_urls' => [['option_id' => $this->options['Grün'], 'url_name_' . $de => '', 'regenerate' => '1']],
        ]);

        AdminFixture::clean();
        $slugs = $this->_objectManager->get(SlugRepositoryInterface::class);
        self::assertSame('eigenes-gruen', $slugs->getOptionSlugs($this->attributeId, 0)[$this->options['Grün']]);
        self::assertSame('gruen', $slugs->getOptionSlugs($this->attributeId, $de)[$this->options['Grün']]);
    }

    public function testARefusedSaveLeavesNoUncommittedNameInTheCache(): void
    {
        $de = $this->germanStore();
        $this->save([
            'store' => (string) $de,
            'url_name_store' => 'farbe',
            'option_urls' => [
                ['option_id' => $this->options['Rot'], 'url_name_' . $de => 'gleich'],
                ['option_id' => $this->options['Blau'], 'url_name_' . $de => 'gleich'],
            ],
        ]);

        $this->assertSessionMessages(self::containsEqual('Another option of this filter already uses the URL name &quot;gleich&quot;.'), MessageInterface::TYPE_ERROR);
        self::assertSame(
            self::CODE,
            $this->_objectManager->get(SlugRepositoryInterface::class)->getAttributeSlug($this->attributeId, $de),
            'read without cleaning first: the rollback dropped the cached copy'
        );
    }

    public function testTheStoreViewFormShowsItsOwnColumnsAndNoReset(): void
    {
        $de = $this->germanStore();
        $default = $this->storeId();

        $this->dispatch($this->uri . '/store/' . $de);

        $body = $this->getResponse()->getBody();
        self::assertStringContainsString('store-switcher', $body);
        self::assertStringContainsString('"store":"' . $de . '"', $body);
        self::assertStringContainsString('"url_name_' . $de . '":"', $body);
        self::assertStringContainsString('"option_store_label":"Rot"', $body);
        self::assertStringContainsString('[GLOBAL]', $body);
        self::assertStringNotContainsString('"url_name_' . $default . '"', $body);
        self::assertStringNotContainsString('Reset to defaults', $body);
    }

    public function testTwentyStoreViewsKeepTheFormToTwoNameColumns(): void
    {
        $codes = [];
        try {
            $ids = [$this->germanStore()];
            for ($n = 1; $n <= 18; $n++) {
                $codes[] = $code = sprintf('of_s0_store_%02d', $n);
                $ids[] = AdminFixture::storeView($code, 'Store ' . $n, 100 + $n);
            }
            self::assertGreaterThanOrEqual(20, count($this->_objectManager->get(StoreManagerInterface::class)->getStores()));
            $scope = $ids[10];

            $this->dispatch($this->uri . '/store/' . $scope);

            preg_match_all('/"url_name_(\d+)"/', $this->getResponse()->getBody(), $match);
            self::assertSame(['0', (string) $scope], array_values(array_unique($match[1])));

            $this->_objectManager->get(OptionSlugBatch::class)
                ->save($this->attributeId, [$this->options['Rot'] => [$scope => 'rouge']]);
            AdminFixture::clean();
            $slugs = $this->_objectManager->get(SlugRepositoryInterface::class);
            foreach ($ids as $id) {
                self::assertSame($id === $scope ? 'rouge' : 'rot', $slugs->getOptionSlugs($this->attributeId, $id)[$this->options['Rot']]);
            }
        } finally {
            foreach ($codes as $code) {
                AdminFixture::deleteStoreView($code);
            }
        }
    }

    public function testMatchAllAndTooltipsAreSavedPerScope(): void
    {
        $de = $this->germanStore();
        $this->save(['logic' => 'and', 'tooltip_0' => ' Pick all that apply ']);

        $this->assertSessionMessages(self::containsEqual('You saved the filter.'), MessageInterface::TYPE_SUCCESS);
        $provider = $this->_objectManager->get(FilterConfigProvider::class);
        AdminFixture::clean();
        self::assertSame(Filter::LOGIC_AND, $provider->getForStore($de)[self::CODE]->getLogic());
        self::assertSame('Pick all that apply', $provider->getForStore($de)[self::CODE]->getTooltip());
        $this->_objectManager->get(FilterConfigRepositoryInterface::class)->saveTooltip(self::CODE, $de, 'Alle passenden wählen');
        AdminFixture::clean();
        self::assertSame('Alle passenden wählen', $provider->getForStore($de)[self::CODE]->getTooltip());
    }

    public function testAStoreViewSavesItsTooltipAndCannotChangeTheLogic(): void
    {
        $de = $this->germanStore();
        $this->save(['store' => (string) $de, 'logic' => 'and', 'tooltip_' . $de => 'Hinweis', 'tooltip_0' => 'not for this scope']);

        AdminFixture::clean();
        $provider = $this->_objectManager->get(FilterConfigProvider::class);
        self::assertSame(Filter::LOGIC_OR, $provider->getForStore($de)[self::CODE]->getLogic());
        self::assertSame('Hinweis', $provider->getForStore($de)[self::CODE]->getTooltip());
        self::assertNull($provider->getForStore(0)[self::CODE]->getTooltip());
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

    private function germanStore(): int
    {
        return AdminFixture::storeView(self::STORE, 'Deutsch');
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
