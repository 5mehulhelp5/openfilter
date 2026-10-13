<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Seo;

use Iranimij\OpenFilter\Model\Config;

/**
 * Option links get rel="nofollow" once the page already has the configured number of active values (default 2), so
 * crawlers do not walk the combinations. Links that remove a value stay followable; 0 switches the rule off.
 */
class NofollowPolicy
{
    public const NOFOLLOW = 'nofollow';

    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * Value of the link's rel attribute ('' for none).
     *
     * @param int $activeValues values active on the current page
     * @param bool $selected the link removes a value
     * @param int $storeId
     * @return string
     */
    public function rel(int $activeValues, bool $selected, int $storeId): string
    {
        $threshold = $this->config->getNofollowThreshold($storeId);

        return !$selected && $threshold > 0 && $activeValues >= $threshold ? self::NOFOLLOW : '';
    }
}
