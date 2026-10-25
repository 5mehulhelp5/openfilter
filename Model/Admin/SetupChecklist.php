<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Admin;

use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Setup\Patch\Data\DefaultFilterConfig;
use Magento\Framework\App\Cache\StateInterface as CacheState;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\FlagManager;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;

/**
 * "Set up OpenFilter" (1.2, Review #1): five steps above the Filters grid, each with its state read from what the
 * merchant has done (no step is ticked by hand), until the merchant hides the list.
 */
class SetupChecklist
{
    public const FLAG_HIDDEN = 'openfilter_checklist_hidden';

    /** Set by Sort filters when the merchant saves an order. */
    public const FLAG_SORTED = 'openfilter_filters_sorted';

    /**
     * @param FlagManager $flags
     * @param FilterConfigProvider $provider
     * @param ResourceConnection $resource
     * @param CacheState $cacheState
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly FlagManager $flags,
        private readonly FilterConfigProvider $provider,
        private readonly ResourceConnection $resource,
        private readonly CacheState $cacheState,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * @return bool
     */
    public function isHidden(): bool
    {
        return (bool) $this->flags->getFlagData(self::FLAG_HIDDEN);
    }

    /**
     * @return void
     */
    public function hide(): void
    {
        $this->flags->saveFlag(self::FLAG_HIDDEN, '1');
    }

    /**
     * @return array<int, array{code: string, title: string, done: bool, url: string, link: string}>
     */
    public function steps(): array
    {
        $configs = $this->provider->getForStore(Store::DEFAULT_STORE_ID);
        $settings = $this->settings();

        return [
            $this->step('review', __('Review which filters show and how they look'), (bool) $this->flags->getFlagData(FirstRunNotice::FLAG), 'openfilter/filter/index', ['reviewed' => 1], __('Filters')),
            $this->step('order', __('Put the filters in the order shoppers need them'), $this->isSorted($configs), 'openfilter/filter/sort', [], __('Sort filters')),
            $this->step('display', __('Choose where filters show on the storefront'), isset($settings['display']), 'adminhtml/system_config/edit', ['section' => 'openfilter'], __('Settings')),
            $this->step('seo', __('Set the search engine rules of filtered pages'), isset($settings['seo']), 'adminhtml/system_config/edit', ['section' => 'openfilter'], __('Settings')),
            $this->step('cache', __('Keep the OpenFilter and full page caches on'), $this->cacheState->isEnabled(CacheType::TYPE_IDENTIFIER) && $this->cacheState->isEnabled('full_page'), 'adminhtml/cache/index', [], __('Cache Management')),
        ];
    }

    /**
     * @param string $code
     * @param \Magento\Framework\Phrase $title
     * @param bool $done
     * @param string $route
     * @param array<string, mixed> $params
     * @param \Magento\Framework\Phrase $link
     * @return array{code: string, title: string, done: bool, url: string, link: string}
     */
    private function step(string $code, $title, bool $done, string $route, array $params, $link): array
    {
        return [
            'code' => $code,
            'title' => (string) $title,
            'done' => $done,
            'url' => $this->url->getUrl($route, $params),
            'link' => (string) $link,
        ];
    }

    /**
     * The merchant saved an order: Sort filters since 1.2, or a sort position before 1.2 (installs before 1.2 got no
     * order from OpenFilter).
     *
     * @param \Iranimij\OpenFilter\Model\Config\FilterConfig[] $configs
     * @return bool
     */
    private function isSorted(array $configs): bool
    {
        if ($this->flags->getFlagData(self::FLAG_SORTED)) {
            return true;
        }

        return !$this->flags->getFlagData(DefaultFilterConfig::FLAG_ORDERED)
            && array_filter($configs, static fn ($config): bool => $config->getPositionOverride() !== null) !== [];
    }

    /**
     * Groups of OpenFilter settings saved in any scope ("display", "seo" …).
     *
     * @return array<string, true>
     */
    private function settings(): array
    {
        $connection = $this->resource->getConnection();
        $paths = $connection->fetchCol(
            $connection->select()->distinct()
                ->from($this->resource->getTableName('core_config_data'), 'path')
                ->where('path LIKE ?', 'openfilter/%')
        );
        $groups = [];
        foreach ($paths as $path) {
            $groups[explode('/', (string) $path)[1] ?? ''] = true;
        }

        return $groups;
    }
}
