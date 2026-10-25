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
use Iranimij\OpenFilter\Model\Insights\Report;
use Magento\Backend\Block\Template;

/**
 * The filters of "Sort filters" in their storefront order, or in the order Insights suggests (1.2, `suggested/1`:
 * shown for the merchant to check and save, nothing is saved before).
 */
class Sort extends Template
{
    /**
     * @param Template\Context $context
     * @param FilterConfigRepositoryInterface $repository
     * @param FilterConfigProvider $provider
     * @param Defaults $defaults
     * @param Report $insights
     * @param array<string, mixed> $data
     */
    public function __construct(
        Template\Context $context,
        private readonly FilterConfigRepositoryInterface $repository,
        private readonly FilterConfigProvider $provider,
        private readonly Defaults $defaults,
        private readonly Report $insights,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<int, array{key: string, label: string, active: bool, position: int}> in position order, or in
     *     the suggested order
     */
    public function getFilters(): array
    {
        $filters = $this->filters();
        if (!$this->isSuggested()) {
            return $filters;
        }
        $place = array_flip($this->insights->suggestedOrder(
            (int) $this->getRequest()->getParam('store'),
            max(1, (int) $this->getRequest()->getParam('days'))
        ));
        $index = array_flip(array_column($filters, 'key'));
        usort($filters, static fn (array $a, array $b): int => [$place[$a['key']] ?? PHP_INT_MAX, $index[$a['key']]]
            <=> [$place[$b['key']] ?? PHP_INT_MAX, $index[$b['key']]]);

        return $filters;
    }

    /**
     * @return bool whether the page shows the order Insights suggests
     */
    public function isSuggested(): bool
    {
        return (string) $this->getRequest()->getParam('suggested') === '1';
    }

    /**
     * @return array<int, array{key: string, label: string, active: bool, position: int}> in position order
     */
    private function filters(): array
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
