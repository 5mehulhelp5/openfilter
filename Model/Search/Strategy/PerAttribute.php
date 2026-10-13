<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Search\Strategy;

use Iranimij\OpenFilter\Api\FacetCountStrategyInterface;
use Iranimij\OpenFilter\Model\Search\QueryClauses;
use Magento\Elasticsearch\SearchAdapter\ConnectionManager;

/**
 * Fallback: the main request stays as Magento builds it; for every attribute with an active filter one extra
 * request (no hits, only that attribute's aggregation) runs without that attribute's own filter.
 */
class PerAttribute implements FacetCountStrategyInterface
{
    public const CODE = 'per_attribute';

    /**
     * @param QueryClauses $clauses
     * @param ConnectionManager $connectionManager
     */
    public function __construct(
        private readonly QueryClauses $clauses,
        private readonly ConnectionManager $connectionManager
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getCode(): string
    {
        return self::CODE;
    }

    /**
     * @inheritDoc
     */
    public function prepareQuery(array $query, array $facetFilters): array
    {
        return $query;
    }

    /**
     * @inheritDoc
     */
    public function prepareResponse(array $query, array $response, array $facetFilters): array
    {
        foreach ($facetFilters as $filter) {
            $bucket = $filter->getBucketName();
            $countQuery = $this->clauses->without($query, [$filter]);
            if ($countQuery === null || !isset($query['body']['aggregations'][$bucket])) {
                continue;
            }
            $countQuery['body']['from'] = 0;
            $countQuery['body']['size'] = 0;
            unset($countQuery['body']['sort']);
            $countQuery['body']['aggregations'] = [$bucket => $query['body']['aggregations'][$bucket]];
            $connection = $this->connectionManager->getConnection();
            if (!method_exists($connection, 'query')) {
                continue;
            }
            $result = $connection->query($countQuery);
            if (isset($result['aggregations'][$bucket])) {
                $response['aggregations'][$bucket] = $result['aggregations'][$bucket];
            }
        }

        return $response;
    }
}
