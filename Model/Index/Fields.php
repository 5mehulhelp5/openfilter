<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Index;

/**
 * Names of OpenFilter's search index fields.
 */
class Fields
{
    public const RATING = 'of_rating';
    public const IN_STOCK = 'of_in_stock';
    public const NEWS_FROM = 'of_news_from';
    public const NEWS_TO = 'of_news_to';
    public const DATE_FORMAT = 'Y-m-d H:i:s';
    /** Index and query format of the news dates (engine syntax). */
    public const ENGINE_DATE_FORMAT = 'yyyy-MM-dd HH:mm:ss';
    /** End of a news window without an end date. */
    public const OPEN_END = '9999-12-31 23:59:59';
    /** Start of a news window without a start date. */
    public const OPEN_START = '1970-01-01 00:00:00';

    /**
     * On-sale flag per customer group and website (final price below the regular price in the price index).
     *
     * @param int $groupId
     * @param int $websiteId
     * @return string
     */
    public function onSale(int $groupId, int $websiteId): string
    {
        return sprintf('of_on_sale_%d_%d', $groupId, $websiteId);
    }
}
