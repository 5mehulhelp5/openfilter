<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Search;

/**
 * Built-in filter counts of the last layered navigation response of this request.
 */
class BuiltinCounts
{
    /** @var array<string, int|array<int, int>> */
    private array $counts = [];

    /**
     * @param array<string, int|array<int, int>> $counts
     * @return void
     */
    public function set(array $counts): void
    {
        $this->counts = $counts;
    }

    /**
     * @param string $code
     * @return int|array<int, int>|null
     */
    public function get(string $code): int|array|null
    {
        return $this->counts[$code] ?? null;
    }
}
