<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Search;

use Iranimij\OpenFilter\Api\FacetCountStrategyInterface;
use Iranimij\OpenFilter\Model\Search\QueryClauses;
use Iranimij\OpenFilter\Model\Search\QueryState;
use Magento\Framework\Search\RequestInterface;
use PHPUnit\Framework\TestCase;

class QueryStateTest extends TestCase
{
    public function testRemembersPerRequestAndForgetsOnTake(): void
    {
        $state = new QueryState();
        $request = $this->createStub(RequestInterface::class);
        $other = $this->createStub(RequestInterface::class);
        $strategy = $this->createStub(FacetCountStrategyInterface::class);
        $state->remember($request, ['q' => 'original'], ['q' => 'prepared'], [], $strategy);

        self::assertSame(['q' => 'original'], $state->originalOf(['q' => 'prepared']));
        self::assertNull($state->originalOf(['q' => 'unknown']));
        self::assertNull($state->take($other));
        self::assertSame(['q' => 'prepared'], $state->take($request)['prepared'] ?? null);
        self::assertNull($state->take($request), 'taken once');
    }

    public function testClausesOfNoFiltersAndAQueryWithoutMust(): void
    {
        $clauses = new QueryClauses();

        self::assertSame([], $clauses->clauses([]));
        self::assertSame(['body' => ['query' => ['bool' => ['must' => []]]]], $clauses->without(['body' => []], []));
        self::assertNull($clauses->without(['body' => ['query' => ['bool' => ['must' => 'x']]]], []));
    }
}
