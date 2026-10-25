<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Insights;

use Iranimij\OpenFilter\Model\Insights\Purge;
use Iranimij\OpenFilter\Model\ResourceModel\InsightStorage;
use Magento\Cron\Model\Config as CronConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * M6 S9 retention: the nightly job is registered and keeps the last "Keep the counts" days, today included.
 *
 * @magentoDbIsolation enabled
 */
class PurgeTest extends TestCase
{
    /**
     * @magentoConfigFixture default/openfilter/insights/retention_days 30
     */
    public function testDaysOlderThanTheRetentionAreDeleted(): void
    {
        $om = Bootstrap::getObjectManager();
        $connection = $om->get(ResourceConnection::class)->getConnection();
        $table = $connection->getTableName(InsightStorage::TABLE);
        $dateTime = $om->get(DateTime::class);
        $storeId = (int) $om->get(StoreManagerInterface::class)->getStore('default')->getId();
        $day = static fn (int $ago): string => $dateTime->gmtDate('Y-m-d', $dateTime->gmtTimestamp() - $ago * 86400);
        $connection->delete($table);
        foreach ([0, 29, 30, 200] as $ago) {
            $connection->insert($table, [
                'day' => $day($ago), 'store_id' => $storeId, 'category_id' => 0, 'kind' => 'view',
                'filter_key' => '', 'value' => '', 'hits' => 1,
            ]);
        }

        $deleted = $om->get(Purge::class)->execute();

        self::assertSame(2, $deleted);
        self::assertSame(
            [$day(29), $day(0)],
            $connection->fetchCol($connection->select()->from($table, ['day'])->order('day ASC'))
        );
    }

    public function testTheJobRunsEveryNight(): void
    {
        $jobs = Bootstrap::getObjectManager()->get(CronConfig::class)->getJobs();

        self::assertSame(Purge::class, $jobs['default']['openfilter_insights_purge']['instance'] ?? null);
        self::assertSame('17 3 * * *', $jobs['default']['openfilter_insights_purge']['schedule'] ?? null);
    }
}
