<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Search;

use Iranimij\OpenFilter\Api\Data\FacetFilterInterface;

/**
 * Categories picked in the category tree (1.1) in the engine query: one "any of" clause on category_ids next to the
 * page's own category, counted like every facet — the tree's counts ignore the picks, the page's category stays.
 * The layer filter registers the picks before the search runs; the mapper takes them once.
 */
class CategoryPicks
{
    public const FIELD = 'category_ids';
    public const BUCKET = 'category_bucket';

    /** @var int[] */
    private array $picked = [];

    /**
     * @param int[] $categoryIds
     * @return void
     */
    public function register(array $categoryIds): void
    {
        $this->picked = array_values(array_unique(array_map('intval', $categoryIds)));
    }

    /**
     * @param array<string, mixed> $query engine query
     * @param FacetFilterInterface[] $filters active facet filters
     * @return array{array<string, mixed>, FacetFilterInterface[]}
     */
    public function apply(array $query, array $filters): array
    {
        $picked = $this->picked;
        $this->picked = [];
        if ($picked === []) {
            return [$query, $filters];
        }
        $clause = ['terms' => [self::FIELD => $picked]];
        $query['body']['query']['bool']['must'][] = $clause;
        $filters[] = new FacetFilter(self::FIELD, self::BUCKET, [$clause]);

        return [$query, $filters];
    }
}
