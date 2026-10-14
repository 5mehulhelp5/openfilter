<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Plugin\Index;

use Iranimij\OpenFilter\Model\Index\SearchReindexer;
use Iranimij\OpenFilter\Plugin\Index\IndexerRowsPlugin;
use Iranimij\OpenFilter\Plugin\Index\ReviewAggregatePlugin;
use Magento\Framework\DataObject;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Review\Model\ResourceModel\Review;
use PHPUnit\Framework\TestCase;

class ReindexTriggersTest extends TestCase
{
    public function testReindexerSkipsEmptyListsAndInvalidIndexes(): void
    {
        $indexer = $this->createMock(IndexerInterface::class);
        $indexer->method('isInvalid')->willReturnOnConsecutiveCalls(false, true);
        $indexer->expects(self::once())->method('reindexList')->with([5, 7]);
        $registry = $this->createStub(IndexerRegistry::class);
        $registry->method('get')->willReturn($indexer);
        $reindexer = new SearchReindexer($registry);

        $reindexer->reindex(['5', 7, 5, 0]);
        $reindexer->reindex([]);
        $reindexer->reindex([9]);
    }

    public function testPluginsPassTheChangedProducts(): void
    {
        $calls = [];
        $reindexer = $this->createStub(SearchReindexer::class);
        $reindexer->method('reindex')->willReturnCallback(static function (array $ids) use (&$calls): void {
            $calls[] = $ids;
        });
        $rows = new IndexerRowsPlugin($reindexer);
        $subject = new \stdClass();

        self::assertSame('r', $rows->afterExecute($subject, 'r', [1, 2]));
        self::assertNull($rows->afterExecuteList($subject, null, [3]));
        self::assertNull($rows->afterExecuteRow($subject, null, 4));
        $review = new ReviewAggregatePlugin($reindexer);
        $subjectReview = $this->createStub(Review::class);
        self::assertSame('x', $review->afterAggregate($subjectReview, 'x', new DataObject(['entity_id' => 1, 'entity_pk_value' => 9])));
        $review->afterAggregate($subjectReview, 'x', new DataObject(['entity_id' => 2, 'entity_pk_value' => 10]));

        self::assertSame([[1, 2], [3], [4], [9]], $calls, 'category reviews (entity 2) are ignored');
    }
}
