<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Sitemap;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Magento\Framework\Api\Search\SearchCriteriaBuilderFactory;
use Magento\Framework\ObjectManagerInterface;

/**
 * The search engine's category page collection (the virtual type every engine module uses), each with its own search
 * criteria builder: the shared builder keeps every filter added to it, so one category's filter would stay on the next.
 */
class CategoryCollectionFactory
{
    /**
     * @param ObjectManagerInterface $objectManager
     * @param SearchCriteriaBuilderFactory $criteriaFactory
     * @param string $instanceName
     */
    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
        private readonly SearchCriteriaBuilderFactory $criteriaFactory,
        private readonly string $instanceName = 'elasticsearchCategoryCollection'
    ) {
    }

    /**
     * @return Collection
     */
    public function create(): Collection
    {
        /** @var Collection $collection */
        $collection = $this->objectManager->create($this->instanceName, [
            'searchCriteriaBuilder' => $this->criteriaFactory->create(),
        ]);

        return $collection;
    }
}
