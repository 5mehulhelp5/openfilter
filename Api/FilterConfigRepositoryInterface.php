<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Api;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface;
use Iranimij\OpenFilter\Api\Data\FilterConfigSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Reads and saves filter settings. Reads come from the per-store cache; saves clean it.
 *
 * @api
 */
interface FilterConfigRepositoryInterface
{
    /**
     * @param string $code built-in code or attribute code ("attribute:<code>" for an attribute named like a built-in)
     * @return FilterConfigInterface
     * @throws NoSuchEntityException when no filterable attribute or built-in has this code
     */
    public function getByCode(string $code): FilterConfigInterface;

    /**
     * @param int $attributeId
     * @return FilterConfigInterface
     * @throws NoSuchEntityException when the attribute is not filterable
     */
    public function getByAttributeId(int $attributeId): FilterConfigInterface;

    /**
     * Every filterable attribute and built-in, in position order (admin scope labels).
     *
     * @return FilterConfigInterface[]
     */
    public function getAll(): array;

    /**
     * Filters matching the criteria (admin scope labels, never null). Fields: key (unique: "attribute:<code>" for an
     * attribute named like a built-in), code, label, builtin, attribute_id, input_type, display_mode, multiselect,
     * visible_options, search_threshold, expanded, sort_by, hide_single_option, seo_indexable, position_override,
     * position, active.
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return FilterConfigSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): FilterConfigSearchResultsInterface;

    /**
     * @param FilterConfigInterface $config
     * @return FilterConfigInterface
     * @throws LocalizedException when a value does not fit the attribute
     */
    public function save(FilterConfigInterface $config): FilterConfigInterface;

    /**
     * Label for one store view; null or '' removes the override.
     *
     * @param string $code
     * @param int $storeId
     * @param string|null $label
     * @return void
     * @throws NoSuchEntityException
     */
    public function saveLabel(string $code, int $storeId, ?string $label): void;

    /**
     * Tooltip for one store view (store 0 = all store views); null or '' removes it (1.1).
     *
     * @param string $code filter key
     * @param int $storeId
     * @param string|null $tooltip plain text
     * @return void
     * @throws NoSuchEntityException
     */
    public function saveTooltip(string $code, int $storeId, ?string $tooltip): void;

    /**
     * Back to the defaults OpenFilter derives from the attribute (store labels are kept).
     *
     * @param string $code
     * @return FilterConfigInterface
     * @throws NoSuchEntityException
     */
    public function resetToDefault(string $code): FilterConfigInterface;
}
