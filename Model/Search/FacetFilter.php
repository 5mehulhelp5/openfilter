<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Search;

use Iranimij\OpenFilter\Api\Data\FacetFilterInterface;

/**
 * Immutable active facet filter.
 */
class FacetFilter implements FacetFilterInterface
{
    /**
     * @param string $field
     * @param string $bucketName
     * @param array<int, array<string, mixed>> $clauses
     */
    public function __construct(
        private readonly string $field,
        private readonly string $bucketName,
        private readonly array $clauses
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @inheritDoc
     */
    public function getBucketName(): string
    {
        return $this->bucketName;
    }

    /**
     * @inheritDoc
     */
    public function getClauses(): array
    {
        return $this->clauses;
    }
}
