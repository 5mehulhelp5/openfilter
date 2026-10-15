<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Api;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface;
use Iranimij\OpenFilter\Api\FilterConfigManagementInterface;
use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Magento\Framework\Exception\LocalizedException;

class FilterConfigManagement implements FilterConfigManagementInterface
{
    /**
     * @param FilterConfigRepositoryInterface $repository
     */
    public function __construct(
        private readonly FilterConfigRepositoryInterface $repository
    ) {
    }

    /**
     * @inheritDoc
     */
    public function update(string $code, FilterConfigInterface $config): FilterConfigInterface
    {
        $current = $this->repository->getByCode($code);
        if (!$current instanceof FilterConfig) {
            throw new LocalizedException(__('%1: this filter cannot be edited.', $code));
        }

        // Only the fields of the request change (Magento calls a setter per field); copies, because the repository
        // hands out its cached object.
        $changed = $config instanceof FilterConfig ? $current->withSettingsOf($config) : $current->withBehaviourOf($config);

        return $this->repository->save($changed);
    }
}
