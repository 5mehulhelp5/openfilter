<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Resolver;

use Iranimij\OpenFilter\Model\Landing\LandingPages;
use Iranimij\OpenFilter\Model\Landing\LandingUrl;
use Magento\Cms\Model\Template\Filter as CmsFilter;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Query\Uid;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Store\Model\StoreManagerInterface;

/**
 * `openfilter_landing_page(url_key)` (1.2): an enabled landing page of the store view, or null.
 */
class LandingPage implements ResolverInterface
{
    /**
     * @param LandingPages $pages
     * @param LandingUrl $landingUrl
     * @param FilterProvider $filterProvider
     * @param Uid $uid
     * @param StoreManagerInterface $storeManager GraphQL's store view (the Store header)
     */
    public function __construct(
        private readonly LandingPages $pages,
        private readonly LandingUrl $landingUrl,
        private readonly FilterProvider $filterProvider,
        private readonly Uid $uid,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param Field $field
     * @param mixed $context
     * @param ResolveInfo $info
     * @param array<string, mixed>|null $value
     * @param array<string, mixed>|null $args
     * @return array<string, mixed>|null
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $page = $this->pages->byUrlKey((string) ($args['url_key'] ?? ''), $storeId);
        if ($page === null) {
            return null;
        }
        $description = $page->getDescription();
        /** @var CmsFilter $filter CMS's block filter */
        $filter = $this->filterProvider->getBlockFilter();
        $filters = [];
        foreach ($page->getFilters() as $code => $values) {
            $filters[] = ['code' => $code, 'values' => $values];
        }

        return [
            'page_id' => $page->getId(),
            'url_key' => $page->getUrlKey(),
            'url' => $this->landingUrl->url($page, $storeId),
            'title' => $page->getTitle(),
            'heading' => $page->getHeading(),
            'meta_title' => $page->getMetaTitle(),
            'meta_description' => $page->getMetaDescription(),
            'description' => $description === null ? null : (string) $filter->setStoreId($storeId)->filter($description),
            'robots' => $page->getRobots(),
            'category_uid' => $this->uid->encode((string) $page->getCategoryId()),
            'filters' => $filters,
        ];
    }
}
