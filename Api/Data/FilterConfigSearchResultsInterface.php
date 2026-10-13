<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * A page of filters (attributes and built-ins) for search criteria.
 *
 * @api
 */
interface FilterConfigSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \Iranimij\OpenFilter\Api\Data\FilterConfigInterface[]
     */
    public function getItems();

    /**
     * @param \Iranimij\OpenFilter\Api\Data\FilterConfigInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
