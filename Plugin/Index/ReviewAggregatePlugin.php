<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Plugin\Index;

use Iranimij\OpenFilter\Model\Index\SearchReindexer;
use Magento\Framework\DataObject;
use Magento\Review\Model\ResourceModel\Review;

/**
 * A new or changed review changes the product's rating summary, which the search index does not follow by itself.
 */
class ReviewAggregatePlugin
{
    /**
     * @param SearchReindexer $reindexer
     */
    public function __construct(
        private readonly SearchReindexer $reindexer
    ) {
    }

    /**
     * @param Review $subject
     * @param mixed $result
     * @param DataObject $object review
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterAggregate(Review $subject, $result, $object)
    {
        if ($object instanceof DataObject && (int) $object->getData('entity_id') === 1) {
            $this->reindexer->reindex([$object->getData('entity_pk_value')]);
        }

        return $result;
    }
}
