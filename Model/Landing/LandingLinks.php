<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Landing;

use Iranimij\OpenFilter\Api\FilterStateInterface;
use Iranimij\OpenFilter\Model\Seo\CategoryUrl;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Layer\Resolver;

/**
 * Filter links and landing pages (1.2): a link whose filters are exactly a landing page's (same category) goes to the
 * landing page; on a landing page the other links start from the category's URL, not from the landing page's.
 */
class LandingLinks
{
    /**
     * @param CurrentPage $current
     * @param LandingPages $pages
     * @param LandingUrl $landingUrl
     * @param Resolver $layerResolver
     * @param CategoryUrl $categoryUrl
     */
    public function __construct(
        private readonly CurrentPage $current,
        private readonly LandingPages $pages,
        private readonly LandingUrl $landingUrl,
        private readonly Resolver $layerResolver,
        private readonly CategoryUrl $categoryUrl
    ) {
    }

    /**
     * The URL filter links start from on a landing page (its category's); null elsewhere.
     *
     * @param int $storeId
     * @return string|null
     */
    public function base(int $storeId): ?string
    {
        $category = $this->current->get() === null ? null : $this->category();

        return $category === null ? null : $this->categoryUrl->get($category, $storeId);
    }

    /**
     * The landing page's URL for a link with this filter state on the page's category; null = none.
     *
     * @param FilterStateInterface $state
     * @param array<string, string> $keep other query parameters (sort, limit)
     * @param int $storeId
     * @return string|null
     */
    public function urlFor(FilterStateInterface $state, array $keep, int $storeId): ?string
    {
        $category = $this->category();
        $page = $category === null ? null : $this->pages->forState((int) $category->getId(), $state->getValues(), $storeId);
        if ($page === null) {
            return null;
        }
        $url = $this->landingUrl->url($page, $storeId);

        return $keep === [] ? $url : $url . '?' . implode('&', array_map(
            static fn (string $name, string $value): string => rawurlencode($name) . '=' . rawurlencode($value),
            array_keys($keep),
            $keep
        ));
    }

    /**
     * @return Category|null
     */
    private function category(): ?Category
    {
        $category = $this->layerResolver->get()->getCurrentCategory();

        return $category instanceof Category && $category->getId() ? $category : null;
    }
}
