<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Doctor;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Model\Coexistence\Detector;
use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Index\Fields;
use Iranimij\OpenFilter\Model\ResourceModel\FilterConfigStorage;
use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\CatalogInventory\Model\Configuration as InventoryConfiguration;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * "Why is this filter not showing?": every condition a filter needs on a store view (and, with a category, on that
 * category page), each with what was found and how to fix it. The first failing check is usually the answer.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class FilterDiagnosis
{
    /** Customer group "NOT LOGGED IN": the price field the storefront uses for guests. */
    private const GUEST_GROUP = 0;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Detector $detector
     * @param FilterConfigProvider $provider
     * @param FilterConfigStorage $configStorage
     * @param SlugStorage $slugStorage
     * @param EngineProbe $engine
     * @param Fields $fields
     * @param CategoryRepositoryInterface $categories
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $url admin URLs for the fixes
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Detector $detector,
        private readonly FilterConfigProvider $provider,
        private readonly FilterConfigStorage $configStorage,
        private readonly SlugStorage $slugStorage,
        private readonly EngineProbe $engine,
        private readonly Fields $fields,
        private readonly CategoryRepositoryInterface $categories,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * @param string $key filter key (attribute code, built-in code or "attribute:<code>")
     * @param int $storeId store view
     * @param int|null $categoryId a category page to check, or null for the store view only
     * @return Check[]
     */
    public function diagnose(string $key, int $storeId, ?int $categoryId = null): array
    {
        $checks = [$this->enabled($storeId), $this->coexistence()];
        $config = $this->provider->getForStore($storeId)[$key] ?? null;
        if ($config === null) {
            $checks[] = new Check(
                'filter',
                (string) __('The attribute is used in layered navigation'),
                false,
                (string) __('"%1" is not a filter: it is not used in layered navigation.', $key),
                (string) __('Set "Use in Layered Navigation" to "Filterable (with results)" on the attribute.'),
                $this->url->getUrl('catalog/product_attribute/index')
            );

            return $checks;
        }
        $checks[] = $this->shown($config);
        $attributeId = $config->isBuiltin() ? null : $config->getAttributeId();
        $flags = $attributeId !== null ? $this->configStorage->attributeFlags($attributeId) : null;
        if ($attributeId !== null && $flags !== null) {
            array_push($checks, ...$this->layeredNavigation($attributeId, $flags));
            if (in_array($flags['input'], ['select', 'multiselect'], true)) {
                $checks[] = $this->urlNames($attributeId);
            }
        }
        if ($config->getCode() === Filter::BUILTIN_STOCK && $config->isBuiltin()) {
            $checks[] = $this->stockShown($storeId);
        }
        $field = $this->field($config, $storeId, $flags);
        if ($field !== null) {
            $checks[] = $this->indexField($storeId, $field);
        }
        if ($categoryId !== null) {
            array_push($checks, ...$this->category($config, $storeId, $categoryId, $field));
        }

        return $checks;
    }

    /**
     * @param int $storeId
     * @return Check
     */
    private function enabled(int $storeId): Check
    {
        $on = $this->scopeConfig->isSetFlag(Config::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);

        return new Check(
            'enabled',
            (string) __('OpenFilter is on for this store view'),
            $on,
            $on ? '' : (string) __('"Enable OpenFilter" is set to No.'),
            (string) __('Set "Enable OpenFilter" to Yes in Stores > Configuration > Iranimij > OpenFilter.'),
            $this->url->getUrl('adminhtml/system_config/edit', ['section' => 'openfilter'])
        );
    }

    /**
     * @return Check
     */
    private function coexistence(): Check
    {
        $other = $this->detector->getActiveReplacement();

        return new Check(
            'coexistence',
            (string) __('No other layered navigation is active'),
            $other === null,
            $other === null ? '' : (string) __('%1 provides the layered navigation, so OpenFilter steps aside.', $other),
            (string) __('Disable one of the two layered navigation modules.')
        );
    }

    /**
     * @param FilterConfig $config
     * @return Check
     */
    private function shown(FilterConfig $config): Check
    {
        return new Check(
            'shown',
            (string) __('The filter is set to show on the storefront'),
            $config->isActive(),
            $config->isActive() ? '' : (string) __('It is hidden in Catalog > OpenFilter > Filters.'),
            (string) __('Switch "Show on the storefront" on.'),
            $this->url->getUrl('openfilter/filter/edit', ['code' => $this->provider->keyOf($config)])
        );
    }

    /**
     * @param int $attributeId
     * @param array{code: string, input: string, is_filterable: int, is_filterable_in_search: int} $flags
     * @return Check[]
     */
    private function layeredNavigation(int $attributeId, array $flags): array
    {
        $edit = $this->url->getUrl('catalog/product_attribute/edit', ['attribute_id' => $attributeId]);

        return [
            new Check(
                'category_pages',
                (string) __('The attribute is used in layered navigation on category pages'),
                $flags['is_filterable'] > 0,
                $flags['is_filterable'] > 0 ? '' : (string) __('"Use in Layered Navigation" is set to No.'),
                (string) __('Set "Use in Layered Navigation" to "Filterable (with results)" (Storefront Properties).'),
                $edit
            ),
            new Check(
                'search_pages',
                (string) __('The attribute is used in layered navigation on search result pages'),
                $flags['is_filterable_in_search'] > 0,
                $flags['is_filterable_in_search'] > 0 ? '' : (string) __('"Use in Search Results Layered Navigation" is set to No.'),
                (string) __('Set "Use in Search Results Layered Navigation" to Yes (Storefront Properties).'),
                $edit
            ),
        ];
    }

    /**
     * @param int $attributeId
     * @return Check
     */
    private function urlNames(int $attributeId): Check
    {
        $labels = $this->slugStorage->optionLabels($attributeId)['admin'];
        $slugs = $this->slugStorage->optionSlugRows($attributeId)[0] ?? [];
        $missing = array_values(array_diff_key($labels, $slugs));
        $ok = $labels !== [] && $missing === [];
        $detail = match (true) {
            $labels === [] => (string) __('The attribute has no options.'),
            $missing !== [] => (string) __('Options without a URL name: %1.', implode(', ', array_slice($missing, 0, 5))),
            default => '',
        };

        return new Check(
            'url_names',
            (string) __('Every option has a URL name'),
            $ok,
            $detail,
            $labels === []
                ? (string) __('Add options to the attribute.')
                : (string) __('Save the attribute once: options get their URL names when the attribute is saved.'),
            $this->url->getUrl('catalog/product_attribute/edit', ['attribute_id' => $attributeId])
        );
    }

    /**
     * @param int $storeId
     * @return Check
     */
    private function stockShown(int $storeId): Check
    {
        $shown = $this->scopeConfig->isSetFlag(
            InventoryConfiguration::XML_PATH_SHOW_OUT_OF_STOCK,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return new Check(
            'out_of_stock_shown',
            (string) __('Out-of-stock products are shown in the catalog'),
            $shown,
            $shown ? '' : (string) __('All listed products are in stock, so an "In stock" filter would change nothing.'),
            (string) __('Set "Display Out of Stock Products" to Yes in Stores > Configuration > Catalog > Inventory.'),
            $this->url->getUrl('adminhtml/system_config/edit', ['section' => 'cataloginventory'])
        );
    }

    /**
     * @param int $storeId
     * @param string $field
     * @return Check
     */
    private function indexField(int $storeId, string $field): Check
    {
        $fields = $this->engine->fields($storeId);
        if ($fields === null) {
            return new Check(
                'engine',
                (string) __('The search engine answers'),
                false,
                (string) __('OpenFilter could not read the product index of this store view.'),
                (string) __('Check the search engine connection in Stores > Configuration > Catalog > Catalog > Catalog Search.')
            );
        }
        $found = in_array($field, $fields, true);

        return new Check(
            'index_field',
            (string) __('The search index has the field "%1"', $field),
            $found,
            $found ? '' : (string) __('The product index of this store view has no field "%1".', $field),
            (string) __('Reindex the search index: bin/magento indexer:reindex catalogsearch_fulltext')
        );
    }

    /**
     * @param FilterConfig $config
     * @param int $storeId
     * @param int $categoryId
     * @param string|null $field
     * @return Check[]
     */
    private function category(FilterConfig $config, int $storeId, int $categoryId, ?string $field): array
    {
        try {
            /** @var Category $category */
            $category = $this->categories->get($categoryId, $storeId);
        } catch (NoSuchEntityException) {
            return [new Check('category', (string) __('The category exists'), false, (string) __('No category with ID %1.', $categoryId))];
        }
        $edit = $this->url->getUrl('catalog/category/edit', ['id' => $categoryId, 'store' => $storeId]);
        $anchor = (bool) $category->getData('is_anchor') && $category->getDisplayMode() !== Category::DM_PAGE;
        $checks = [new Check(
            'category_anchor',
            (string) __('"%1" shows layered navigation', (string) $category->getName()),
            $anchor,
            $anchor ? '' : (string) __('The category is not an anchor, or it shows a static block only.'),
            (string) __('Set "Anchor" to Yes and "Display Mode" to show products (Catalog > Categories > Display Settings).'),
            $edit
        )];
        $clause = $this->clause($config, $field);
        if ($clause === null || $field === null) {
            return $checks;
        }
        $count = $this->engine->count($storeId, $categoryId, $clause);
        if ($count === null) {
            return $checks;
        }
        $checks[] = new Check(
            'products',
            (string) __('Products in "%1" match this filter', (string) $category->getName()),
            $count > 0,
            (string) __('%1 visible product(s) in this category have a value for this filter.', $count),
            $config->isBuiltin()
                ? (string) __('No product in this category qualifies yet (for example no ratings, no special prices).')
                : (string) __('Give the products in this category a value for the attribute, then reindex.'),
            $edit
        );
        if ($count > 0 && !$config->isBuiltin() && $config->isHideSingleOption()) {
            $distinct = $this->engine->distinct($storeId, $categoryId, $field);
            if ($distinct !== null) {
                $checks[] = new Check(
                    'single_option',
                    (string) __('More than one option has products here'),
                    $distinct > 1,
                    $distinct > 1 ? '' : (string) __('Only one option has products in this category, and "Hide when only one option is left" is on.'),
                    (string) __('Turn "Hide when only one option is left" off, or give products other values.'),
                    $this->url->getUrl('openfilter/filter/edit', ['code' => $this->provider->keyOf($config)])
                );
            }
        }

        return $checks;
    }

    /**
     * The index field a filter reads in a store view, or null when it has none (category).
     *
     * @param FilterConfig $config
     * @param int $storeId
     * @param array{code: string, input: string, is_filterable: int, is_filterable_in_search: int}|null $flags
     * @return string|null
     */
    private function field(FilterConfig $config, int $storeId, ?array $flags): ?string
    {
        if (!$config->isBuiltin()) {
            return $flags['code'] ?? $config->getCode();
        }
        $websiteId = (int) $this->storeManager->getStore($storeId)->getWebsiteId();

        return match ($config->getCode()) {
            Filter::BUILTIN_PRICE => sprintf('price_%d_%d', self::GUEST_GROUP, $websiteId),
            Filter::BUILTIN_RATING => Fields::RATING,
            Filter::BUILTIN_STOCK => Fields::IN_STOCK,
            Filter::BUILTIN_SALE => $this->fields->onSale(self::GUEST_GROUP, $websiteId),
            Filter::BUILTIN_NEW => Fields::NEWS_FROM,
            default => null,
        };
    }

    /**
     * The engine clause "this product has a value for the filter".
     *
     * @param FilterConfig $config
     * @param string|null $field
     * @return array<string, mixed>|null
     */
    private function clause(FilterConfig $config, ?string $field): ?array
    {
        if ($field === null) {
            return null;
        }
        if (!$config->isBuiltin()) {
            return ['exists' => ['field' => $field]];
        }

        return match ($config->getCode()) {
            Filter::BUILTIN_RATING => ['range' => [$field => ['gt' => 0]]],
            Filter::BUILTIN_STOCK, Filter::BUILTIN_SALE => ['term' => [$field => 1]],
            Filter::BUILTIN_NEW => ['bool' => ['filter' => [
                ['range' => [Fields::NEWS_FROM => ['lte' => 'now']]],
                ['range' => [Fields::NEWS_TO => ['gte' => 'now']]],
            ]]],
            default => ['exists' => ['field' => $field]],
        };
    }
}
