<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Admin;

use Magento\Backend\Model\Menu\Config as MenuConfig;
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
}
