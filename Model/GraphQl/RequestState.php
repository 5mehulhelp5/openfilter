<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\GraphQl;

/**
 * A GraphQL `products` field that asks for `openfilter_aggregations` (1.1): while its search runs, OpenFilter treats
 * the GraphQL search request like a layered navigation page and keeps the engine's raw aggregations (slider stats).
 */
class RequestState
{
    private bool $active = false;

    /** @var array<string, mixed> */
    private array $aggregations = [];

    /**
     * @return void
     */
    public function start(): void
    {
        $this->active = true;
        $this->aggregations = [];
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * @param array<string, mixed> $aggregations the engine's aggregations of the search response
     * @return void
     */
    public function keep(array $aggregations): void
    {
        $this->aggregations = $aggregations;
    }

    /**
     * Ends the search; returns the kept aggregations.
     *
     * @return array<string, mixed>
     */
    public function finish(): array
    {
        $aggregations = $this->aggregations;
        $this->active = false;
        $this->aggregations = [];

        return $aggregations;
    }
}
