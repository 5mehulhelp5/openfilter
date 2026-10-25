<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Block\Adminhtml;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Insights\Report;
use Magento\Backend\Block\Template;

/**
 * The Insights page (1.2): a store view and period picker, then the report of that store view's counts.
 */
class Insights extends Template
{
    /** Periods offered, in days. */
    public const PERIODS = [7, 30, 90];
    public const DEFAULT_PERIOD = 30;

    /** @var array<string, mixed>|null */
    private ?array $report = null;

    /**
     * @param Template\Context $context
     * @param Report $insights
     * @param Config $config
     * @param array<string, mixed> $data
     */
    public function __construct(
        Template\Context $context,
        private readonly Report $insights,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<int, string> store id => "Website / Store view"
     */
    public function getStores(): array
    {
        $stores = [];
        foreach ($this->_storeManager->getStores() as $store) {
            $stores[(int) $store->getId()] = $store->getName();
        }
        ksort($stores);

        return $stores;
    }

    /**
     * @return int the chosen store view, else the default one
     */
    public function getStoreId(): int
    {
        $stores = $this->getStores();
        $chosen = (int) $this->getRequest()->getParam('store');
        if (isset($stores[$chosen])) {
            return $chosen;
        }
        $default = $this->_storeManager->getDefaultStoreView();

        return $default !== null ? (int) $default->getId() : (int) array_key_first($stores);
    }

    /**
     * @return int
     */
    public function getDays(): int
    {
        $days = (int) $this->getRequest()->getParam('days');

        return in_array($days, self::PERIODS, true) ? $days : self::DEFAULT_PERIOD;
    }

    /**
     * @return bool whether the chosen store view collects insights now
     */
    public function isCollecting(): bool
    {
        return $this->config->isInsightsEnabled($this->getStoreId());
    }

    /**
     * @return array{views: int, enough: bool,
     *     filters: array<int, array{key: string, label: string, hits: int, share: float}>,
     *     values: array<int, array{filter: string, label: string, hits: int}>,
     *     unused: string[],
     *     zero: array<int, array{label: string, hits: int}>,
     *     indexable: array<int, array{key: string, label: string, hits: int, share: float}>,
     *     order: string[]}
     */
    public function getReport(): array
    {
        return $this->report ??= $this->insights->build($this->getStoreId(), $this->getDays());
    }

    /**
     * @return array<string, string> filter key => label, in the suggested order (active filters only)
     */
    public function getSuggestedOrder(): array
    {
        $report = $this->getReport();
        $labels = array_column($report['filters'], 'label', 'key');
        $order = [];
        foreach ($report['order'] as $key) {
            if (isset($labels[$key])) {
                $order[$key] = $labels[$key];
            }
        }

        return $order;
    }

    /**
     * @return string
     */
    public function getSortUrl(): string
    {
        return $this->getUrl('openfilter/filter/sort', ['suggested' => 1, 'store' => $this->getStoreId(), 'days' => $this->getDays()]);
    }

    /**
     * @return string
     */
    public function getSettingsUrl(): string
    {
        return $this->getUrl('adminhtml/system_config/edit', ['section' => 'openfilter']);
    }

    /**
     * @param string $key
     * @return string the filter's edit form
     */
    public function getFilterUrl(string $key): string
    {
        return $this->getUrl('openfilter/filter/edit', ['code' => $key]);
    }

    /**
     * @return string
     */
    public function getFormUrl(): string
    {
        return $this->getUrl('openfilter/insights/index');
    }
}
