<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config;

use Iranimij\OpenFilter\Api\Data\FilterConfigSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

/**
 * Search results of FilterConfigRepositoryInterface::getList().
 */
class FilterConfigSearchResults extends SearchResults implements FilterConfigSearchResultsInterface
{
}
