<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Admin;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Message\MessageInterface;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * M5 S5 admin: "Sort filters" (page and save, all or nothing), "Where it shows" saved with the filter form, and the
 * storefront link of the "Why is this filter not showing?" panel.
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation disabled
 * @magentoCache openfilter enabled
 */
class SortAndWhereTest extends AbstractBackendController
{
    private const CODE = 'of_m5_sort_attr';

    protected function setUp(): void
    {
        $this->resource = 'Iranimij_OpenFilter::filters';
        $this->uri = 'backend/openfilter/filter/sort';
        parent::setUp();
        AdminFixture::selectAttribute(self::CODE, ['Eins', 'Zwei']);
        AdminFixture::clean();
    }

    protected function tearDown(): void
    {
        AdminFixture::deleteAttribute(self::CODE);
        AdminFixture::resetBuiltins();
        parent::tearDown();
    }

    public function testTheSortPageListsEveryFilterWithMoveButtons(): void
    {
        $this->dispatch($this->uri);

        $body = $this->getResponse()->getBody();
        self::assertStringContainsString('Sort filters', $body);
        self::assertStringContainsString('data-of-key="' . self::CODE . '"', $body);
        self::assertStringContainsString('data-of-key="price"', $body);
        self::assertStringContainsString('aria-label="Move&#x20;Of&#x20;m5&#x20;sort&#x20;attr&#x20;up"', $body);
        self::assertStringContainsString('name="order[]"', $body);
    }

    public function testSavingTheOrderWritesPositionsInStepsOfTen(): void
    {
        $this->post('backend/openfilter/filter/sortSave', ['order' => [self::CODE, 'price', 'category']]);

        $this->assertSessionMessages(self::containsEqual('The new order of the filters is saved.'), MessageInterface::TYPE_SUCCESS);
        $this->assertRedirect(self::stringContains('openfilter/filter/sort'));
        AdminFixture::clean();
        $repository = $this->_objectManager->get(FilterConfigRepositoryInterface::class);
        self::assertSame([10, 20, 30], [
            $repository->getByCode(self::CODE)->getPosition(),
            $repository->getByCode('price')->getPosition(),
            $repository->getByCode('category')->getPosition(),
        ]);
    }

    public function testAnUnknownFilterWritesNothing(): void
    {
        $before = $this->_objectManager->get(FilterConfigRepositoryInterface::class)->getByCode(self::CODE)->getPosition();

        $this->post('backend/openfilter/filter/sortSave', ['order' => [self::CODE, 'no_such_filter']]);

        $this->assertSessionMessages(self::logicalNot(self::isEmpty()), MessageInterface::TYPE_ERROR);
        AdminFixture::clean();
        self::assertSame($before, $this->_objectManager->get(FilterConfigRepositoryInterface::class)->getByCode(self::CODE)->getPosition());
    }

    public function testWhereItShowsIsSavedWithTheForm(): void
    {
        $this->post('backend/openfilter/filter/save', [
            'key' => self::CODE,
            'category_mode' => Filter::CATEGORIES_EXCEPT,
            'category_ids' => ['2'],
        ]);

        $this->assertSessionMessages(self::containsEqual('You saved the filter.'), MessageInterface::TYPE_SUCCESS);
        AdminFixture::clean();
        $config = $this->_objectManager->get(FilterConfigRepositoryInterface::class)->getByCode(self::CODE);
        self::assertSame([Filter::CATEGORIES_EXCEPT, [2]], [$config->getCategoryMode(), $config->getCategoryIds()]);
    }

    public function testOnlyWithoutCategoriesIsRefused(): void
    {
        $this->post('backend/openfilter/filter/save', ['key' => self::CODE, 'category_mode' => Filter::CATEGORIES_ONLY]);

        $this->assertSessionMessages(self::containsEqual('Pick at least one category for where the filter shows.'), MessageInterface::TYPE_ERROR);
    }

    public function testTheWhyPanelLinksTheCategoryOnTheStorefront(): void
    {
        $this->dispatch('backend/openfilter/filter/diagnose/code/' . self::CODE . '/store/1/category/2');

        $data = json_decode($this->getResponse()->getBody(), true);
        self::assertArrayHasKey('preview', $data);
        self::assertTrue($data['preview'] === null || str_starts_with((string) $data['preview'], 'http'));
    }

    /**
     * @param string $uri
     * @param array<string, mixed> $values
     */
    private function post(string $uri, array $values): void
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST)
            ->setPostValue(['form_key' => $this->_objectManager->get(FormKey::class)->getFormKey()] + $values);
        $this->dispatch($uri);
    }
}
