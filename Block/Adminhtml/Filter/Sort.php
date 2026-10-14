<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Block\Adminhtml\Filter;

use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Magento\Backend\Block\Template;

/**
 * The filters of "Sort filters" in their storefront order.
 */
class Sort extends Template
{
    /**
     * @param Template\Context $context
     * @param FilterConfigRepositoryInterface $repository
     * @param FilterConfigProvider $provider
     * @param Defaults $defaults
     * @param array<string, mixed> $data
     */
    public function __construct(
        Template\Context $context,
        private readonly FilterConfigRepositoryInterface $repository,
        private readonly FilterConfigProvider $provider,
        private readonly Defaults $defaults,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<int, array{key: string, label: string, active: bool, position: int}> in position order
     */
    public function getFilters(): array
    {
        $filters = [];
        foreach ($this->repository->getAll() as $config) {
            $label = $config->getLabel()
                ?? ($config->isBuiltin() ? (string) __($this->defaults->builtinLabel($config->getCode())) : $config->getCode());
            $filters[] = [
                'key' => $this->provider->keyOf($config),
                'label' => $label,
                'active' => $config->isActive(),
                'position' => $config->getPosition(),
            ];
        }

        return $filters;
    }

    /**
     * @return string
     */
    public function getSaveUrl(): string
    {
        return $this->getUrl('*/*/sortSave');
    }

    /**
     * @return string
     */
    public function getBackUrl(): string
    {
        return $this->getUrl('*/*/index');
    }
}
