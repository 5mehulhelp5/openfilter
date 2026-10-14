<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Search;

use Iranimij\OpenFilter\Api\Data\FacetFilterInterface;
use Iranimij\OpenFilter\Api\FacetCountStrategyInterface;
use Magento\Framework\Search\RequestInterface;

/**
 * Carries a rewritten query from the mapper to the aggregation builder of the same search request.
 */
class QueryState
{
    /**
     * @var array<int, array{original: array<string, mixed>, prepared: array<string, mixed>,
     *     filters: FacetFilterInterface[], strategy: FacetCountStrategyInterface}>
     */
    private array $states = [];

    /**
     * @param RequestInterface $request
     * @param array<string, mixed> $original
     * @param array<string, mixed> $prepared
     * @param FacetFilterInterface[] $filters
     * @param FacetCountStrategyInterface $strategy
     */
    public function remember(
        RequestInterface $request,
        array $original,
        array $prepared,
        array $filters,
        FacetCountStrategyInterface $strategy
    ): void {
        $this->states[spl_object_id($request)] = [
            'original' => $original,
            'prepared' => $prepared,
            'filters' => $filters,
            'strategy' => $strategy,
        ];
    }

    /**
     * Mapper's original query for a prepared query, so code that re-runs the query (core price intervals) sees
     * exactly what core would have sent.
     *
     * @param array<string, mixed> $prepared
     * @return array<string, mixed>|null
     */
    public function originalOf(array $prepared): ?array
    {
        foreach ($this->states as $state) {
            if ($state['prepared'] === $prepared) {
                return $state['original'];
            }
        }

        return null;
    }

    /**
     * Returns and forgets the state of a request.
     *
     * @param RequestInterface $request
     * @return array{original: array<string, mixed>, prepared: array<string, mixed>,
     *     filters: FacetFilterInterface[], strategy: FacetCountStrategyInterface}|null
     */
    public function take(RequestInterface $request): ?array
    {
        $id = spl_object_id($request);
        $state = $this->states[$id] ?? null;
        unset($this->states[$id]);

        return $state;
    }
}
