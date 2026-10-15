<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Config;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;

/**
 * The merchant's "Where it shows" (1.1): everywhere, only in some categories, or everywhere except some; a category
 * counts with the categories below it. Shared by the storefront, the XML sitemap and GraphQL.
 */
class CategoryRule
{
    /**
     * @param Filter $filter
     * @param int[] $path ids of the page's category and the categories above it
     * @return bool
     */
    public function showsIn(Filter $filter, array $path): bool
    {
        if ($filter->getCategoryMode() === Filter::CATEGORIES_ALL) {
            return true;
        }
        $inside = array_intersect($path, $filter->getCategoryIds()) !== [];

        return $filter->getCategoryMode() === Filter::CATEGORIES_ONLY ? $inside : !$inside;
    }
}
