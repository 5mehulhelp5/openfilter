<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Insights;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\ResourceModel\InsightStorage;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Nightly cron job (1.2): deletes the insight counts older than "Keep the counts for this many days". Runs whether
 * insights are on or off, so switching them off never leaves old counts behind for longer.
 */
class Purge
{
    /**
     * @param Config $config
     * @param InsightStorage $storage
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly Config $config,
        private readonly InsightStorage $storage,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * @return int rows deleted
     */
    public function execute(): int
    {
        $days = $this->config->getInsightsRetentionDays();

        return $this->storage->purge($this->dateTime->gmtDate('Y-m-d', $this->dateTime->gmtTimestamp() - ($days - 1) * 86400));
    }
}
