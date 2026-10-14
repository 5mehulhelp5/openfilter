<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Api;

/**
 * The active filter values of a request, by filter key (attribute code or built-in code; an attribute named like a
 * built-in is "attribute:<code>"). Attribute values are option ids, sliders "min-max", toggles "1", rating "1".."5".
 * Immutable: with*() return a new state.
 *
 * @api
 */
interface FilterStateInterface
{
    /**
     * @return array<string, string[]> filter key => values
     */
    public function getValues(): array;

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * @param string $key
     * @return string[]
     */
    public function get(string $key): array;

    /**
     * Number of active values over all filters.
     *
     * @return int
     */
    public function count(): int;

    /**
     * @param string $key
     * @param string[] $values empty removes the filter
     * @return FilterStateInterface
     */
    public function with(string $key, array $values): FilterStateInterface;

    /**
     * @param string $key
     * @return FilterStateInterface
     */
    public function without(string $key): FilterStateInterface;
}
