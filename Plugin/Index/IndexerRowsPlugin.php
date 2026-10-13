<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Plugin\Index;

use Iranimij\OpenFilter\Model\Index\SearchReindexer;

/**
 * After a partial price or legacy stock reindex (special price windows refreshed by cron, stock changes), the same
 * products are re-indexed in search so of_on_sale_* and of_in_stock follow.
 */
class IndexerRowsPlugin
{
    /**
     * @param SearchReindexer $reindexer
     */
    public function __construct(
        private readonly SearchReindexer $reindexer
    ) {
    }

    /**
     * Mview (scheduled) runs.
     *
     * @param object $subject
     * @param mixed $result
     * @param int[] $ids
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecute($subject, $result, $ids)
    {
        $this->reindexer->reindex((array) $ids);

        return $result;
    }

    /**
     * @param object $subject
     * @param mixed $result
     * @param int[] $ids
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecuteList($subject, $result, array $ids)
    {
        $this->reindexer->reindex($ids);

        return $result;
    }

    /**
     * @param object $subject
     * @param mixed $result
     * @param int $id
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterExecuteRow($subject, $result, $id)
    {
        $this->reindexer->reindex([$id]);

        return $result;
    }
}
