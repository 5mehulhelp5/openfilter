<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Seo;

use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Magento\Catalog\Model\Category;

/**
 * The category URL for canonical tags, from OpenFilter's cache: Category::getUrl() reads the URL rewrite table, a
 * query core does not run on a category page (02 · Architecture §10: no extra SQL). The entry carries the category's
 * own cache tag, so saving the category (a new URL key) cleans it.
 */
class CategoryUrl
{
    /**
     * @param CacheType $cache
     */
    public function __construct(
        private readonly CacheType $cache
    ) {
    }

    /**
     * @param Category $category
     * @param int $storeId
     * @return string the URL without a query
     */
    public function get(Category $category, int $storeId): string
    {
        $id = 'openfilter_category_url_' . $storeId . '_' . (int) $category->getId();
        $cached = $this->cache->load($id);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }
        $url = (string) $category->getUrl();
        $url = strtok($url, '?') ?: $url;
        $this->cache->save($url, $id, [Category::CACHE_TAG . '_' . (int) $category->getId()]);

        return $url;
    }
}
