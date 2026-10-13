<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Api\Data;

/**
 * An active filter on an attribute that also has a term aggregation (a facet): its counts must ignore the
 * filter itself (disjunctive counts) while every other facet's counts must include it.
 *
 * @api
 */
interface FacetFilterInterface
{
    /**
     * Attribute code the filter and the aggregation work on, e.g. "color".
     *
     * @return string
     */
    public function getField(): string;

    /**
     * Name of the aggregation (bucket) on the same field, e.g. "color_bucket".
     *
     * @return string
     */
    public function getBucketName(): string;

    /**
     * The engine clauses this filter adds to the query's bool.must, exactly as Magento's filter builder makes them.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getClauses(): array;
}
