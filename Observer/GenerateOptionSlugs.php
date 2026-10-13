<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Observer;

use Iranimij\OpenFilter\Api\SlugRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * New options of a filterable attribute get URL names when the attribute is saved; existing names never change.
 */
class GenerateOptionSlugs implements ObserverInterface
{
    /**
     * @param SlugRepositoryInterface $slugRepository
     */
    public function __construct(
        private readonly SlugRepositoryInterface $slugRepository
    ) {
    }

    /**
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        $attribute = $observer->getEvent()->getData('attribute');
        if (!$attribute instanceof Attribute || !$attribute->getId()) {
            return;
        }
        $filterable = (int) $attribute->getData('is_filterable') > 0
            || (int) $attribute->getData('is_filterable_in_search') > 0;
        if ($filterable && in_array($attribute->getFrontendInput(), ['select', 'multiselect'], true)) {
            $this->slugRepository->regenerate((int) $attribute->getId());
        }
    }
}
