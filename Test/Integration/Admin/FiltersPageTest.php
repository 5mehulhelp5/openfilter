<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Admin;

use Iranimij\OpenFilter\Model\Admin\FirstRunNotice;
use Magento\Backend\Model\Menu\Config as MenuConfig;
use Magento\Framework\FlagManager;
use Magento\Framework\Notification\MessageList;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Catalog > OpenFilter > Filters: route, menu and ACL (testAclHasAccess / testAclNoAccess come from the parent).
 *
 * @magentoAppArea adminhtml
 */
class FiltersPageTest extends AbstractBackendController
{
    protected function setUp(): void
    {
        $this->resource = 'Iranimij_OpenFilter::filters';
        $this->uri = 'backend/openfilter/filter/index';
        parent::setUp();
    }

    public function testThePageOpensUnderCatalogOpenFilter(): void
    {
        $this->dispatch($this->uri);

        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
        self::assertStringContainsString('<title>Filters', $this->getResponse()->getBody());

        $menu = $this->_objectManager->get(MenuConfig::class)->getMenu();
        $filters = $menu->get('Iranimij_OpenFilter::filters');
        self::assertNotNull($filters);
        self::assertSame('openfilter/filter/index', $filters->getAction());
        self::assertNotNull($menu->get('Magento_Catalog::catalog')?->getChildren()->get('Iranimij_OpenFilter::openfilter'));
    }

    public function testTheFirstRunNoticeShowsUntilTheDefaultsAreReviewed(): void
    {
        $flags = $this->_objectManager->get(FlagManager::class);
        $flags->deleteFlag(FirstRunNotice::FLAG);
        $notice = $this->_objectManager->get(MessageList::class)->getMessageByIdentity('openfilter_first_run');

        self::assertNotNull($notice);
        self::assertTrue($notice->isDisplayed());
        self::assertStringContainsString('openfilter/filter/index/reviewed/1', $notice->getText());

        $flags->saveFlag(FirstRunNotice::FLAG, '1');
        self::assertFalse($this->_objectManager->create(FirstRunNotice::class)->isDisplayed());
        $flags->deleteFlag(FirstRunNotice::FLAG);
    }

    public function testReviewDefaultsDismissesTheNotice(): void
    {
        $flags = $this->_objectManager->get(FlagManager::class);
        $flags->deleteFlag(FirstRunNotice::FLAG);

        $this->dispatch('backend/openfilter/filter/index/reviewed/1');

        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
        self::assertSame('1', (string) $flags->getFlagData(FirstRunNotice::FLAG));
        $flags->deleteFlag(FirstRunNotice::FLAG);
    }
}
