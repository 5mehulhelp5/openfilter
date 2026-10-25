<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Insights;

use Iranimij\OpenFilter\Model\ResourceModel\InsightStorage;
use Iranimij\OpenFilter\Test\Integration\Catalog\M1Catalog;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * M6 S9 insight beacon endpoint: 404 while insights are off; on, a valid beacon is added to the day's counts (an
 * existing count goes up), a beacon with an unknown filter or too many values is refused (400) and counts nothing.
 * One dispatch per test.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class CollectTest extends AbstractController
{
    private const PATH = '/openfilter/insights/collect';

    /** @var array{category: int, store: int, options: array<string, array<string, int>>} */
    private static array $catalog;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$catalog = M1Catalog::create();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();
        parent::tearDown();
    }

    public function testOffByDefault(): void
    {
        $this->post(['f' => [M1Catalog::COLOR => [$this->red()]]]);

        self::assertSame(404, $this->getResponse()->getHttpResponseCode());
        self::assertSame([], $this->rows());
    }

    /**
     * @magentoConfigFixture current_store openfilter/insights/enabled 1
     */
    public function testAValidBeaconIsAddedToTheDaysCounts(): void
    {
        $red = (string) $this->red();
        $this->connection()->insert($this->table(), [
            'day' => $this->today(), 'store_id' => $this->storeId(), 'category_id' => self::$catalog['category'],
            'kind' => 'value', 'filter_key' => M1Catalog::COLOR, 'value' => $red, 'hits' => 4,
        ]);

        $this->post(['f' => [M1Catalog::COLOR => [$red], 'price' => ['10-20']], 'b' => 0]);

        self::assertSame(204, $this->getResponse()->getHttpResponseCode());
        self::assertSame([
            'filter|' . M1Catalog::COLOR . '|' => 1,
            'filter|price|' => 1,
            'value|' . M1Catalog::COLOR . '|' . $red => 5,
            'view||' => 1,
            'zero||' . M1Catalog::COLOR . '=' . $red . ';price' => 1,
        ], $this->rows());
    }

    /**
     * @magentoConfigFixture current_store openfilter/insights/enabled 1
     */
    public function testAnUnknownFilterIsRefused(): void
    {
        $this->post(['f' => ['of_no_such_filter' => ['1']]]);

        self::assertSame(400, $this->getResponse()->getHttpResponseCode());
        self::assertSame([], $this->rows());
    }

    /**
     * @magentoConfigFixture current_store openfilter/insights/enabled 1
     */
    public function testTooManyValuesAreRefused(): void
    {
        $this->post(['f' => [M1Catalog::COLOR => array_map('strval', range(1, 21))]]);

        self::assertSame(400, $this->getResponse()->getHttpResponseCode());
        self::assertSame([], $this->rows());
    }

    /**
     * @param array<string, mixed> $data
     * @return void
     */
    private function post(array $data): void
    {
        $body = (string) json_encode($data + ['s' => 'default', 'c' => self::$catalog['category'], 'b' => 2, 'f' => []]);
        $this->getRequest()->setMethod('POST')->setContent($body);
        $this->dispatch(self::PATH);
    }

    private function red(): int
    {
        return self::$catalog['options'][M1Catalog::COLOR]['Red'];
    }

    /**
     * @return array<string, int> "kind|filter|value" => hits of the default store view
     */
    private function rows(): array
    {
        $select = $this->connection()->select()->from($this->table(), ['kind', 'filter_key', 'value', 'hits'])
            ->where('store_id = ?', $this->storeId());
        $rows = [];
        foreach ($this->connection()->fetchAll($select) as $row) {
            $rows[$row['kind'] . '|' . $row['filter_key'] . '|' . $row['value']] = (int) $row['hits'];
        }
        ksort($rows);

        return $rows;
    }

    private function clean(): void
    {
        $this->connection()->delete($this->table());
    }

    private function today(): string
    {
        return Bootstrap::getObjectManager()->get(DateTime::class)->gmtDate('Y-m-d');
    }

    private function storeId(): int
    {
        return (int) Bootstrap::getObjectManager()->get(StoreManagerInterface::class)->getStore('default')->getId();
    }

    private function table(): string
    {
        return $this->connection()->getTableName(InsightStorage::TABLE);
    }

    private function connection(): AdapterInterface
    {
        return Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection();
    }
}
