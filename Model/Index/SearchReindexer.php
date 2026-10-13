<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Index;

use Magento\CatalogSearch\Model\Indexer\Fulltext;
use Magento\Framework\Indexer\IndexerRegistry;

/**
 * Re-indexes products in the search index when data behind OpenFilter's fields changes outside the fulltext
 * index's own triggers (review summaries, price index rows for special price windows, legacy stock status).
 */
class SearchReindexer
{
    /**
     * @param IndexerRegistry $indexerRegistry
     */
    public function __construct(
        private readonly IndexerRegistry $indexerRegistry
    ) {
    }

    /**
     * @param array<int|string> $productIds
     * @return void
     */
    public function reindex(array $productIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if ($ids === []) {
            return;
        }
        $indexer = $this->indexerRegistry->get(Fulltext::INDEXER_ID);
        if (!$indexer->isInvalid()) {
            $indexer->reindexList($ids);
        }
    }
}
