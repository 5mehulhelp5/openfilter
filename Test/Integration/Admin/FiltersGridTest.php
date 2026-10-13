<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Admin;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Message\MessageInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Catalog > OpenFilter > Filters: grid data, inline edit and mass actions through the admin front controller.
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation disabled
 * @magentoCache openfilter enabled
 */
class FiltersGridTest extends AbstractBackendController
{
    private const CODE = 'of_m2_grid_attr';

    protected function setUp(): void
    {
        $this->resource = 'Iranimij_OpenFilter::filters';
        $this->uri = 'backend/openfilter/filter/inlineEdit';
        $this->httpMethod = HttpRequest::METHOD_POST;
        parent::setUp();
        AdminFixture::selectAttribute(self::CODE, ['Alpha', 'Beta', 'Gamma']);
        AdminFixture::resetBuiltins();
    }

    protected function tearDown(): void
    {
        AdminFixture::deleteAttribute(self::CODE);
        AdminFixture::resetBuiltins();
        parent::tearDown();
    }

    public function testGetListFindsAttributesAndBuiltinsBySearchCriteria(): void
    {
        $repository = $this->_objectManager->get(FilterConfigRepositoryInterface::class);
        $criteria = $this->_objectManager->create(SearchCriteriaBuilder::class)
            ->addFilter('builtin', 1)
            ->addFilter('active', 0)
            ->create();

        $codes = array_map(static fn ($c): string => $c->getCode(), $repository->getList($criteria)->getItems());

        self::assertSame(['rating', 'stock', 'sale', 'new'], $codes, 'the built-ins that start switched off');
        $mine = $repository->getList(
            $this->_objectManager->create(SearchCriteriaBuilder::class)->addFilter('code', self::CODE)->create()
        );
        self::assertSame(1, $mine->getTotalCount());
        self::assertSame('Of m2 grid attr', $mine->getItems()[0]->getLabel());
    }

    public function testTheGridRendersRowsFromTheRepository(): void
    {
        $this->getRequest()->setParams([
            'namespace' => 'openfilter_filter_listing',
            'search' => '',
            'isAjax' => 'true',
            'paging' => ['pageSize' => 200, 'current' => 1],
        ]);
        $this->getRequest()->getHeaders()->addHeaderLine('Accept', 'application/json');
        $this->dispatch('backend/mui/index/render');

        $body = $this->getResponse()->getBody();
        self::assertJson($body, substr($body, 0, 500));
        $data = $this->_objectManager->get(Json::class)->unserialize($body);
        $rows = array_column($data['data']['items'] ?? $data['items'] ?? [], null, 'key');
        self::assertArrayHasKey(self::CODE, $rows);
        self::assertArrayHasKey('rating', $rows);
        self::assertSame(0, $rows['rating']['active']);
        self::assertStringContainsString('openfilter/filter/edit/code/' . self::CODE, $rows[self::CODE]['actions']['edit']['href']);
    }

    public function testInlineEditSavesEachRowAndReportsBadRowsWithoutStoppingTheOthers(): void
    {
        $this->post('backend/openfilter/filter/inlineEdit', [
            'isAjax' => 'true',
            'items' => [
                self::CODE => ['display_mode' => 'list', 'multiselect' => '0', 'visible_options' => '2',
                    'search_threshold' => '9', 'seo_indexable' => '1', 'position' => '77', 'active' => '0'],
                'rating' => ['display_mode' => 'swatch'],
            ],
        ]);

        $result = $this->_objectManager->get(Json::class)->unserialize($this->getResponse()->getBody());
        self::assertTrue($result['error']);
        self::assertCount(1, $result['messages']);
        self::assertStringContainsString('[rating]', $result['messages'][0]);

        $config = $this->fresh()->getByCode(self::CODE);
        self::assertFalse($config->isMultiselect());
        self::assertSame(2, $config->getVisibleOptions());
        self::assertSame(9, $config->getSearchThreshold());
        self::assertTrue($config->isSeoIndexable());
        self::assertSame(77, $config->getPosition());
        self::assertFalse($config->isActive());
        self::assertSame(Filter::DISPLAY_RATING, $this->fresh()->getByCode('rating')->getDisplayMode(), 'bad row unchanged');

        $storeId = (int) $this->_objectManager->get(StoreManagerInterface::class)->getStore('default')->getId();
        self::assertArrayNotHasKey(self::CODE, $this->_objectManager->get(FilterConfigProvider::class)->getActive($storeId));
    }

    public function testShowAppliesToTheTickedRows(): void
    {
        $this->massAction('show', ['selected' => ['rating', 'stock']]);

        self::assertTrue($this->fresh()->getByCode('rating')->isActive());
        self::assertTrue($this->fresh()->getByCode('stock')->isActive());
        $this->assertSessionMessages(self::containsEqual('2 filter(s) are shown on the storefront.'), MessageInterface::TYPE_SUCCESS);
    }

    public function testHideWithSelectAllFollowsTheGridFiltersAndExclusions(): void
    {
        $repository = $this->fresh();
        $repository->save($repository->getByCode('rating')->setActive(true));
        $repository->save($this->fresh()->getByCode('stock')->setActive(true));

        $this->massAction('hide', ['excluded' => ['rating'], 'filters' => ['builtin' => '1']]);

        self::assertTrue($this->fresh()->getByCode('rating')->isActive(), 'excluded');
        self::assertFalse($this->fresh()->getByCode('stock')->isActive());
        self::assertFalse($this->fresh()->getByCode('price')->isActive());
        self::assertTrue($this->fresh()->getByCode(self::CODE)->isActive(), 'not a built-in: outside the grid filter');
    }

    public function testResetPutsTheSelectionBackToDefaults(): void
    {
        $repository = $this->fresh();
        $repository->save($repository->getByCode('rating')->setActive(true));
        $repository->save($this->fresh()->getByCode('price')->setActive(false));
        $repository->save($this->fresh()->getByCode(self::CODE)->setActive(false)->setVisibleOptions(1));

        $this->massAction('reset', ['selected' => ['rating', 'price', self::CODE]]);

        self::assertFalse($this->fresh()->getByCode('rating')->isActive());
        self::assertTrue($this->fresh()->getByCode('price')->isActive());
        self::assertTrue($this->fresh()->getByCode(self::CODE)->isActive());
        self::assertSame(6, $this->fresh()->getByCode(self::CODE)->getVisibleOptions());
    }

    public function testAnUnknownMassActionChangesNothing(): void
    {
        $this->massAction('delete', ['selected' => ['rating']]);

        $this->assertSessionMessages(self::containsEqual('Please correct the data sent.'), MessageInterface::TYPE_ERROR);
    }

    /**
     * @param string $action
     * @param array<string, mixed> $selection
     * @return void
     */
    private function massAction(string $action, array $selection): void
    {
        $this->post('backend/openfilter/filter/massAction/do/' . $action, $selection + [
            'namespace' => 'openfilter_filter_listing',
        ]);
        AdminFixture::clean();
    }

    /**
     * @param string $uri
     * @param array<string, mixed> $params
     * @return void
     */
    private function post(string $uri, array $params): void
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST)->setPostValue($params + ['form_key' => $this->getFormKey()]);
        $this->dispatch($uri);
    }

    private function getFormKey(): string
    {
        return $this->_objectManager->get(\Magento\Framework\Data\Form\FormKey::class)->getFormKey();
    }

    private function fresh(): FilterConfigRepositoryInterface
    {
        AdminFixture::clean();

        return $this->_objectManager->get(FilterConfigRepositoryInterface::class);
    }
}
