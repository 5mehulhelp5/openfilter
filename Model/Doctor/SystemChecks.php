<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Doctor;

use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Iranimij\OpenFilter\Model\Search\StrategyResolver;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Framework\App\Cache\StateInterface as CacheState;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;

/**
 * Checks of the whole installation for `openfilter:doctor`: search engine reachable, count strategy, OpenFilter's
 * cache, missing URL names, and other code that replaces or wraps Magento's layered navigation classes (found in the
 * merged DI configuration, reported by class name; OpenFilter never names other vendors itself).
 */
class SystemChecks
{
    /** Core layered navigation classes another module would replace or wrap. */
    public const WATCHED = [
        'Magento\Catalog\Model\Layer\FilterList',
        'Magento\Catalog\Model\Layer\Category\FilterableAttributeList',
        'Magento\Catalog\Model\Layer\Search\FilterableAttributeList',
        'Magento\Catalog\Model\Layer\Filter\Item',
        'Magento\CatalogSearch\Model\Layer\Filter\Attribute',
        'Magento\CatalogSearch\Model\Layer\Filter\Price',
        'Magento\LayeredNavigation\Block\Navigation',
        'Magento\LayeredNavigation\Block\Navigation\State',
    ];

    /** Namespaces whose changes are expected (Magento itself, OpenFilter). */
    private const OWN = ['Magento\\', 'Iranimij\\OpenFilter'];

    /**
     * @param EngineProbe $engine
     * @param StrategyResolver $strategies
     * @param CacheState $cacheState
     * @param SlugStorage $slugStorage
     * @param ConfigLoaderInterface $diConfig
     * @param AttributeCollectionFactory $attributes product attributes (search page use)
     */
    public function __construct(
        private readonly EngineProbe $engine,
        private readonly StrategyResolver $strategies,
        private readonly CacheState $cacheState,
        private readonly SlugStorage $slugStorage,
        private readonly ConfigLoaderInterface $diConfig,
        private readonly AttributeCollectionFactory $attributes
    ) {
    }

    /**
     * @param int $storeId store view whose product index is probed
     * @param array<int, string> $optionAttributes attribute id => filter key of the option filters to check
     * @return Check[]
     */
    public function run(int $storeId, array $optionAttributes): array
    {
        return [
            $this->engineReachable($storeId),
            $this->strategy(),
            $this->cache(),
            $this->urlNames($optionAttributes),
            $this->searchPageFilters(),
            $this->replacements(),
        ];
    }

    /**
     * Search result pages offer only the attributes set to "Use in Search Results Layered Navigation"; many stores
     * have none, so their search pages show no attribute filter at all.
     *
     * @return Check
     */
    public function searchPageFilters(): Check
    {
        $count = $this->attributes->create()->addFieldToFilter('is_filterable_in_search', '1')->getSize();

        return new Check(
            'search_page_filters',
            (string) __('Search result pages have attribute filters'),
            $count > 0,
            $count > 0
                ? (string) __('%1 attributes are used in search result layered navigation.', $count)
                : (string) __('No attribute is used in search result layered navigation: search pages offer only the built-in filters.'),
            (string) __('In Catalog > OpenFilter > Filters, select the filters and choose Actions > Show on search pages (or set "Use in Search Results Layered Navigation" on the attributes).')
        );
    }

    /**
     * @param int $storeId
     * @return Check
     */
    private function engineReachable(int $storeId): Check
    {
        $fields = $this->engine->fields($storeId);

        return new Check(
            'engine',
            (string) __('The search engine answers and has a product index'),
            $fields !== null,
            $fields === null
                ? (string) __('No product index for this store view (engine %1).', $this->strategies->engine())
                : (string) __('Engine %1, %2 fields in the product index.', $this->strategies->engine(), count($fields)),
            (string) __('Check the search engine connection in Stores > Configuration > Catalog > Catalog Search, then run bin/magento indexer:reindex catalogsearch_fulltext.')
        );
    }

    /**
     * @return Check
     */
    private function strategy(): Check
    {
        return new Check(
            'strategy',
            (string) __('Filter counts have a strategy'),
            true,
            (string) __('%1 (engine %2).', $this->strategies->code(), $this->strategies->engine())
        );
    }

    /**
     * @return Check
     */
    private function cache(): Check
    {
        $on = $this->cacheState->isEnabled(CacheType::TYPE_IDENTIFIER);

        return new Check(
            'cache',
            (string) __('The OpenFilter cache is enabled'),
            $on,
            $on ? '' : (string) __('Without it, every page reads filter settings and URL names from the database.'),
            (string) __('Run bin/magento cache:enable openfilter.')
        );
    }

    /**
     * @param array<int, string> $optionAttributes
     * @return Check
     */
    private function urlNames(array $optionAttributes): Check
    {
        $missing = [];
        foreach ($optionAttributes as $attributeId => $key) {
            $options = $this->slugStorage->optionLabels($attributeId)['admin'];
            $named = $this->slugStorage->optionSlugRows($attributeId)[0] ?? [];
            $count = count(array_diff_key($options, $named));
            if ($count > 0) {
                $missing[] = sprintf('%s (%d)', $key, $count);
            }
        }

        return new Check(
            'url_names',
            (string) __('Every option has a URL name'),
            $missing === [],
            $missing === [] ? '' : (string) __('Options without a URL name: %1.', implode(', ', $missing)),
            (string) __('Run bin/magento openfilter:slugs:generate (existing URL names stay as they are).')
        );
    }

    /**
     * @return Check
     */
    private function replacements(): Check
    {
        $found = [];
        foreach (['global', 'frontend'] as $area) {
            $config = (array) $this->diConfig->load($area);
            foreach (self::WATCHED as $type) {
                $preference = $config['preferences'][$type] ?? null;
                if (is_string($preference) && $preference !== $type && !$this->isOwn($preference)) {
                    $found[] = sprintf('%s replaces %s', $preference, $type);
                }
                foreach ((array) ($config[$type]['plugins'] ?? []) as $plugin) {
                    $instance = is_array($plugin) ? (string) ($plugin['instance'] ?? '') : '';
                    $disabled = is_array($plugin) && !empty($plugin['disabled']);
                    if ($instance !== '' && !$disabled && !$this->isOwn($instance)) {
                        $found[] = sprintf('%s wraps %s', $instance, $type);
                    }
                }
            }
        }
        $found = array_values(array_unique($found));

        return new Check(
            'replacements',
            (string) __('No other module changes Magento\'s layered navigation'),
            $found === [],
            implode('; ', $found),
            (string) __('Two layered navigations on one page break filters: disable the other module, or OpenFilter.')
        );
    }

    /**
     * @param string $class
     * @return bool
     */
    private function isOwn(string $class): bool
    {
        $class = ltrim($class, '\\');
        foreach (self::OWN as $prefix) {
            if (str_starts_with($class, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
