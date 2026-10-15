<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Api;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Changes a filter's settings by its code (REST `PUT /V1/openfilter/filters/:code`, 1.1).
 *
 * @api
 */
interface FilterConfigManagementInterface
{
    /**
     * Changes the settings sent in $config (how it looks and behaves, SEO, where it shows); settings left out stay
     * as they are. Read-only fields (code, attribute, label, position) cannot be sent. Labels, tooltips and URL
     * names change through the config import.
     *
     * @param string $code built-in code or attribute code ("attribute:<code>" for an attribute named like a built-in)
     * @param \Iranimij\OpenFilter\Api\Data\FilterConfigInterface $config
     * @return \Iranimij\OpenFilter\Api\Data\FilterConfigInterface the saved filter
     * @throws NoSuchEntityException
     * @throws LocalizedException when a value does not fit the filter
     */
    public function update(string $code, FilterConfigInterface $config): FilterConfigInterface;
}
