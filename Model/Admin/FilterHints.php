<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Admin;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface;
use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Doctor\SystemChecks;
use Magento\Framework\Api\SearchCriteriaBuilder;

/**
 * Hints on the Filters grid (Review #1): active filters shoppers would see under the same name, and search result
 * pages without attribute filters.
 */
class FilterHints
{
    /**
     * @param FilterConfigRepositoryInterface $filters labels resolved as in the grid (built-ins' default labels)
     * @param SearchCriteriaBuilder $criteria
     * @param SystemChecks $checks
     */
    public function __construct(
        private readonly FilterConfigRepositoryInterface $filters,
        private readonly SearchCriteriaBuilder $criteria,
        private readonly SystemChecks $checks
    ) {
    }

    /**
     * @return string[]
     */
    public function warnings(): array
    {
        $warnings = [];
        $byLabel = [];
        foreach ($this->filters->getList($this->criteria->create())->getItems() as $config) {
            $label = trim((string) $config->getLabel());
            if ($config->isActive() && $label !== '') {
                $byLabel[mb_strtolower($label)][] = $config;
            }
        }
        foreach ($byLabel as $configs) {
            if (count($configs) > 1) {
                $warnings[] = (string) __(
                    'Shoppers see %1 filters called "%2" (%3). Rename one in its Label and tooltip section, or hide one.',
                    count($configs),
                    (string) $configs[0]->getLabel(),
                    implode(', ', array_map(static fn (FilterConfigInterface $c): string => (string) ($c->isBuiltin()
                        ? __('built-in %1', $c->getCode())
                        : __('attribute %1', $c->getCode())), $configs))
                );
            }
        }
        $search = $this->checks->searchPageFilters();
        if (!$search->ok) {
            $warnings[] = $search->detail . ' ' . $search->fix;
        }

        return $warnings;
    }
}
