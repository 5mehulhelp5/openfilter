<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * URL names ("slugs") of filter parameters and options, per store view. Generated once and never changed by a label
 * change (Spec Slug, "Stability").
 *
 * @api
 */
interface SlugRepositoryInterface
{
    /**
     * Option URL names of one attribute in a store view (store row, else the admin row).
     *
     * @param int $attributeId
     * @param int $storeId
     * @return array<int, string> option id => slug
     */
    public function getOptionSlugs(int $attributeId, int $storeId): array;

    /**
     * URL parameter name of an attribute in a store view (default: the attribute code).
     *
     * @param int $attributeId
     * @param int $storeId
     * @return string
     * @throws NoSuchEntityException when the attribute is not filterable
     */
    public function getAttributeSlug(int $attributeId, int $storeId): string;

    /**
     * @param int $optionId
     * @param int $storeId 0 = all store views without their own name
     * @param string $slug lowercase letters, digits and single dashes
     * @return void
     * @throws LocalizedException when the shape is wrong or another option of the attribute uses it
     */
    public function saveOptionSlug(int $optionId, int $storeId, string $slug): void;

    /**
     * @param int $attributeId
     * @param int $storeId
     * @param string $slug
     * @return void
     * @throws LocalizedException when reserved, malformed or used by another filter
     */
    public function saveAttributeSlug(int $attributeId, int $storeId, string $slug): void;

    /**
     * Generates missing URL names (or all of them with $overwrite, which changes published URLs).
     *
     * @param int $attributeId
     * @param int|null $storeId null = admin and every store view
     * @param bool $overwrite
     * @return void
     */
    public function regenerate(int $attributeId, ?int $storeId = null, bool $overwrite = false): void;
}
