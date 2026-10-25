<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Admin;

use Iranimij\OpenFilter\Model\ResourceModel\InsightStorage;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * M6 S9 admin: the Insights page (its own ACL resource) reads the counts of a store view, and "Apply to Sort filters"
 * opens Sort filters in the suggested order, saving nothing.
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation disabled
 * @magentoCache openfilter enabled
 */
class InsightsPageTest extends AbstractBackendController
{
    private const CODE = 'of_m6_insight_attr';

    private int $storeId = 0;

    protected function setUp(): void
    {
        $this->resource = 'Iranimij_OpenFilter::insights';
        $this->uri = 'backend/openfilter/insights/index';
        parent::setUp();
        AdminFixture::selectAttribute(self::CODE, ['Eins', 'Zwei']);
        AdminFixture::clean();
        $this->storeId = (int) $this->_objectManager->get(StoreManagerInterface::class)->getStore('default')->getId();
        $this->clean();
        $dateTime = $this->_objectManager->get(DateTime::class);
        $row = ['day' => $dateTime->gmtDate('Y-m-d'), 'store_id' => $this->storeId, 'category_id' => 0, 'filter_key' => '', 'value' => ''];
        // insertMultiple takes the columns of the first row: every row has them in the same order.
        $this->connection()->insertMultiple($this->table(), [
            array_merge($row, ['kind' => 'view', 'hits' => 60]),
            array_merge($row, ['kind' => 'filter', 'filter_key' => self::CODE, 'hits' => 45]),
            array_merge($row, ['kind' => 'filter', 'filter_key' => 'price', 'hits' => 5]),
            array_merge($row, ['kind' => 'zero', 'value' => self::CODE . '=999999;price', 'hits' => 2]),
        ]);
    }

    protected function tearDown(): void
    {
        $this->clean();
        AdminFixture::deleteAttribute(self::CODE);
        parent::tearDown();
    }

    public function testThePageShowsTheStoreViewsCounts(): void
    {
        $this->dispatch($this->uri . '/store/' . $this->storeId);

        $body = $this->getResponse()->getBody();
        self::assertStringContainsString('Most used filters', $body);
        self::assertStringContainsString('60 filter pages viewed in this period.', $body);
        self::assertMatchesRegularExpression('#<td>Of m6 insight attr</td>\s*<td>45</td>\s*<td>75 %</td>#', $body);
        self::assertStringContainsString('Of m6 insight attr: 999999 + Price', $body, 'an unknown option shows its id');
        self::assertStringContainsString('Filter combinations without products', $body);
        self::assertStringContainsString('Apply to Sort filters', $body);
        self::assertStringContainsString('openfilter/filter/sort/suggested/1/store/' . $this->storeId, $body);
        self::assertStringContainsString('does not collect insights', $body, 'off by default');
    }

    public function testApplyToSortFiltersShowsTheSuggestedOrder(): void
    {
        $this->dispatch('backend/openfilter/filter/sort/suggested/1/store/' . $this->storeId . '/days/30');

        $body = $this->getResponse()->getBody();
        self::assertStringContainsString('data-of-sort-suggested', $body);
        $mine = strpos($body, 'data-of-key="' . self::CODE . '"');
        $price = strpos($body, 'data-of-key="price"');
        self::assertNotFalse($mine);
        self::assertNotFalse($price);
        self::assertLessThan($price, $mine, 'the most used filter first');
    }

    private function clean(): void
    {
        $this->connection()->delete($this->table());
    }

    private function table(): string
    {
        return $this->connection()->getTableName(InsightStorage::TABLE);
    }

    private function connection(): AdapterInterface
    {
        return $this->_objectManager->get(ResourceConnection::class)->getConnection();
    }
}
