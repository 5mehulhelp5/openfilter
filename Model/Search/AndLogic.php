<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Search;

use Iranimij\OpenFilter\Api\Data\FacetFilterInterface;
use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Magento\Store\Model\StoreManagerInterface;

/**
 * "Products must match all of the picked values" (1.1): core's request holds one terms filter per attribute (any of
 * the values), so an attribute set to "all" gets one term clause per picked value next to it. Its filter stays in
 * the main query instead of becoming a facet filter: its own counts (and every other count) then include it, which
 * is what "all" means — an option's count is the number of products that have it as well.
 */
class AndLogic
{
    /**
     * @param FilterConfigProvider $provider
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly FilterConfigProvider $provider,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param FacetFilterInterface[] $filters active facet filters of the request
     * @return array{FacetFilterInterface[], array<int, array<string, mixed>>} the facet filters that stay
     *     disjunctive, and the term clauses to add to the main query
     */
    public function split(array $filters): array
    {
        $fields = $this->andFields();
        if ($fields === []) {
            return [$filters, []];
        }
        $kept = [];
        $clauses = [];
        foreach ($filters as $filter) {
            if (!isset($fields[$filter->getField()])) {
                $kept[] = $filter;
                continue;
            }
            foreach ($filter->getClauses() as $clause) {
                foreach ((array) ($clause['terms'] ?? []) as $field => $values) {
                    foreach ((array) $values as $value) {
                        $clauses[] = ['term' => [$field => $value]];
                    }
                }
            }
        }

        return [$kept, $clauses];
    }

    /**
     * Attribute codes set to "all" in the current store view.
     *
     * @return array<string, true>
     */
    private function andFields(): array
    {
        $fields = [];
        foreach ($this->provider->getForStore((int) $this->storeManager->getStore()->getId()) as $config) {
            if (!$config->isBuiltin() && $config->getLogic() === Filter::LOGIC_AND) {
                $fields[$config->getCode()] = true;
            }
        }

        return $fields;
    }
}
