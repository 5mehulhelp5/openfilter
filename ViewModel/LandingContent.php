<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\ViewModel;

use Iranimij\OpenFilter\Model\Landing\CurrentPage;
use Magento\Cms\Model\Template\Filter as CmsFilter;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The landing page's content (1.2), filtered like CMS block content (widgets, store and media variables).
 */
class LandingContent implements ArgumentInterface
{
    /**
     * @param CurrentPage $current
     * @param FilterProvider $filterProvider
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly CurrentPage $current,
        private readonly FilterProvider $filterProvider,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return string
     */
    public function html(): string
    {
        $description = $this->current->get()?->getDescription();
        if ($description === null) {
            return '';
        }

        /** @var CmsFilter $filter CMS's block filter */
        $filter = $this->filterProvider->getBlockFilter();

        return (string) $filter->setStoreId((int) $this->storeManager->getStore()->getId())->filter($description);
    }
}
