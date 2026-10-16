<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\GraphQl;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Api\FilterStateInterface;
use Iranimij\OpenFilter\Model\Config\CategoryRule;
use Iranimij\OpenFilter\Model\Config\DependencyRule;
use Iranimij\OpenFilter\Model\Url\FilterState;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Layer\CategoryTree;
use Iranimij\OpenFilter\Model\Url\SlugProvider;
use Iranimij\OpenFilter\ViewModel\Swatch;
use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\GraphQl\Query\Uid;

/**
 * OpenFilter's layered navigation for a GraphQL `products` result (1.1): the store view's filters in the merchant's
 * order and only where they show, each with its options, counts (from the same search request, each filter counted
 * without its own picks), the values picked in the `filter` argument, URL names, display settings and tooltip.
 * Built-in rating, stock, sale and new filters depend on the shopper's session and are left out.
 */
class AggregationsBuilder
{
    /** URL parameter of the category tree on the storefront. */
    private const CATEGORY_PARAM = 'cat';

    /** Numeric ranges: select-like filters are answered with options, these with a range (sliders) or ranges. */
    private const RANGE_MODES = [Filter::DISPLAY_SLIDER, Filter::DISPLAY_FROM_TO];

    /**
     * @param FilterConfigProvider $filters
     * @param CategoryRule $categoryRule
     * @param CategoryTree $tree
     * @param SlugProvider $slugs
     * @param EavConfig $eavConfig
     * @param RangeFilters $ranges
     * @param Swatch $swatches
     * @param Uid $uid category_uid of the filter argument
     * @param DependencyRule $dependencies filters that wait for a pick in another filter (1.2)
     */
    public function __construct(
        private readonly FilterConfigProvider $filters,
        private readonly CategoryRule $categoryRule,
        private readonly CategoryTree $tree,
        private readonly SlugProvider $slugs,
        private readonly EavConfig $eavConfig,
        private readonly RangeFilters $ranges,
        private readonly Swatch $swatches,
        private readonly Uid $uid,
        private readonly DependencyRule $dependencies
    ) {
    }

    /**
     * @param AggregationInterface|null $aggregation Magento's aggregation of the search
     * @param array<string, mixed> $engine the engine's raw aggregations (slider stats)
     * @param array<string, mixed> $filterArgs the `filter` argument of `products`
     * @param int $storeId
     * @return array<int, array<string, mixed>>
     */
    public function build(?AggregationInterface $aggregation, array $engine, array $filterArgs, int $storeId): array
    {
        if ($aggregation === null) {
            return [];
        }
        $category = $this->pageCategory($filterArgs, $storeId);
        $path = $category === null ? null : $this->tree->pathIds($category, $storeId);
        $slugs = $this->slugs->forStore($storeId);
        $active = $this->filters->getActive($storeId);
        $state = $this->argState($active, $filterArgs);
        $filters = array_values(array_filter(
            $active,
            fn (Filter $filter): bool => ($path === null || $this->categoryRule->showsIn($filter, $path))
                && $this->dependencies->allows($filter, $state)
        ));
        usort($filters, static fn (Filter $a, Filter $b): int => $a->getPosition() <=> $b->getPosition());
        $result = [];
        foreach ($filters as $filter) {
            $built = match (true) {
                $filter->getCode() === Filter::BUILTIN_CATEGORY && $filter->isBuiltin()
                    => $this->category($filter, $aggregation, $filterArgs, $category, $storeId),
                $filter->isBuiltin() && $filter->getCode() !== Filter::BUILTIN_PRICE => null,
                $filter->getDisplayMode() === Filter::DISPLAY_RANGES => $this->rangeList($filter, $engine, $filterArgs),
                in_array($filter->getDisplayMode(), self::RANGE_MODES, true) => $this->range($filter, $engine, $filterArgs),
                default => $this->options($filter, $aggregation, $filterArgs, $slugs, $storeId),
            };
            if ($built !== null) {
                $result[] = $built + $this->common($filter, $slugs, $storeId, count($result));
            }
        }

        return $result;
    }

    /**
     * Select and multiple-select attributes: options in the attribute's order (or by name / count), zero counts left
     * out unless picked.
     *
     * @param Filter $filter
     * @param AggregationInterface $aggregation
     * @param array<string, mixed> $filterArgs
     * @param array{attributes: array<int, string>, options: array<int, array<int, string>>} $slugs
     * @param int $storeId
     * @return array<string, mixed>|null
     */
    private function options(Filter $filter, AggregationInterface $aggregation, array $filterArgs, array $slugs, int $storeId): ?array
    {
        $bucket = $aggregation->getBucket($filter->getCode() . '_bucket');
        $attribute = $this->attribute($filter->getCode());
        if ($bucket === null || $attribute === null) {
            return null;
        }
        $counts = [];
        foreach ($bucket->getValues() as $value) {
            $counts[(string) $value->getValue()] = (int) ($value->getMetrics()['count'] ?? 0);
        }
        $picked = $this->pickedValues($filterArgs[$filter->getCode()] ?? null);
        $attribute->setData('store_id', $storeId);
        $labels = [];
        foreach ((array) $attribute->getSource()->getAllOptions() as $option) {
            $id = (string) ($option['value'] ?? '');
            if ($id !== '' && (($counts[$id] ?? 0) > 0 || in_array($id, $picked, true))) {
                // Labels are stored as HTML ("Cocona&reg;"); GraphQL answers text.
                // phpcs:ignore Magento2.Functions.DiscouragedFunction
                $labels[$id] = trim(html_entity_decode(strip_tags((string) ($option['label'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }
        if ($labels === [] || ($filter->isHideSingleOption() && count($labels) === 1 && $picked === [])) {
            return null;
        }
        $swatches = match ($filter->getDisplayMode()) {
            Filter::DISPLAY_SWATCH => $this->swatches->forOptions($labels),
            Filter::DISPLAY_IMAGES => $this->swatches->forImages($labels, $storeId),
            default => [],
        };
        $attributeId = (int) $filter->getAttributeId();
        $options = [];
        foreach ($labels as $id => $label) {
            $options[] = [
                'value' => (string) $id,
                'label' => $label,
                // Yes/No attributes have no URL names: the storefront's URLs use 0 and 1.
                'url_value' => $slugs['options'][$attributeId][(int) $id] ?? (string) $id,
                'count' => $counts[(string) $id] ?? 0,
                'selected' => in_array((string) $id, $picked, true),
                'swatch' => isset($swatches[$id]) ? ['type' => $swatches[$id]['type'], 'value' => $swatches[$id]['value']] : null,
            ];
        }
        if ($filter->getSortBy() === Filter::SORT_NAME) {
            usort($options, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));
        } elseif ($filter->getSortBy() === Filter::SORT_COUNT) {
            usort($options, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        }

        return ['filter_key' => $filter->getCode(), 'label' => $this->label($filter, $attribute, $storeId), 'options' => $options];
    }

    /**
     * Sliders and from–to fields: the lowest and highest value of the products found without this filter's pick.
     *
     * @param Filter $filter
     * @param array<string, mixed> $engine
     * @param array<string, mixed> $filterArgs
     * @return array<string, mixed>|null
     */
    private function range(Filter $filter, array $engine, array $filterArgs): ?array
    {
        $stats = $this->stats($engine, $filter->getCode());
        if ($stats === null) {
            return null;
        }
        $arg = $filterArgs[$filter->getCode()] ?? [];

        return [
            'filter_key' => $filter->getCode(),
            'label' => $this->label($filter, $this->attribute($filter->getCode()), null),
            'options' => [],
            'range' => [
                'min' => $stats['min'],
                'max' => $stats['max'],
                'from' => is_array($arg) && is_numeric($arg['from'] ?? null) ? (float) $arg['from'] : null,
                'to' => is_array($arg) && is_numeric($arg['to'] ?? null) ? (float) $arg['to'] : null,
            ],
        ];
    }

    /**
     * Price and decimal filters shown as a list of ranges, counted in the same request.
     *
     * @param Filter $filter
     * @param array<string, mixed> $engine
     * @param array<string, mixed> $filterArgs
     * @return array<string, mixed>|null
     */
    private function rangeList(Filter $filter, array $engine, array $filterArgs): ?array
    {
        $items = $this->ranges->options($filter, $filterArgs, $this->stats($engine, $filter->getCode()));
        if ($items === []) {
            return null;
        }

        return [
            'filter_key' => $filter->getCode(),
            'label' => $this->label($filter, $this->attribute($filter->getCode()), null),
            'options' => array_map(static fn (array $item): array => [
                'value' => $item['value'],
                'label' => $item['value'],
                'url_value' => $item['value'],
                'count' => $item['count'],
                'selected' => $item['selected'],
                'from' => $item['from'],
                'to' => $item['to'],
            ], $items),
        ];
    }

    /**
     * The categories below the page's category (store root without one), down to the tree depth (list: one level),
     * with Magento's counts; picking one narrows to it (`category_id: {eq}`).
     *
     * @param Filter $filter
     * @param AggregationInterface $aggregation
     * @param array<string, mixed> $filterArgs
     * @param int|null $category
     * @param int $storeId
     * @return array<string, mixed>|null
     */
    private function category(Filter $filter, AggregationInterface $aggregation, array $filterArgs, ?int $category, int $storeId): ?array
    {
        $bucket = $aggregation->getBucket('category_bucket');
        $root = $category ?? $this->storeRoot($storeId);
        if ($bucket === null || $root === null) {
            return null;
        }
        $counts = [];
        foreach ($bucket->getValues() as $value) {
            $counts[(int) $value->getValue()] = (int) ($value->getMetrics()['count'] ?? 0);
        }
        $depth = $filter->getDisplayMode() === Filter::DISPLAY_TREE ? max(1, $filter->getTreeDepth()) : 1;
        $picked = array_map('intval', $this->pickedValues($filterArgs['category_id'] ?? null));
        $options = [];
        foreach ($this->tree->below($root, $depth, $storeId) as $node) {
            $count = $counts[$node['id']] ?? 0;
            if ($count > 0) {
                $options[] = [
                    'value' => (string) $node['id'],
                    'label' => $node['name'],
                    'url_value' => (string) $node['id'],
                    'count' => $count,
                    'selected' => in_array($node['id'], $picked, true),
                    'depth' => $node['depth'],
                ];
            }
        }
        if ($options === []) {
            return null;
        }

        return ['filter_key' => 'category_id', 'label' => $filter->getLabel() ?? (string) __('Category'), 'options' => $options];
    }

    /**
     * @param Filter $filter
     * @param array{attributes: array<int, string>, options: array<int, array<int, string>>} $slugs
     * @param int $storeId
     * @param int $position
     * @return array<string, mixed>
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function common(Filter $filter, array $slugs, int $storeId, int $position): array
    {
        $param = match (true) {
            $filter->isBuiltin() && $filter->getCode() === Filter::BUILTIN_CATEGORY => self::CATEGORY_PARAM,
            $filter->isBuiltin() => $filter->getCode(),
            default => $slugs['attributes'][(int) $filter->getAttributeId()] ?? $filter->getCode(),
        };

        return [
            'code' => $filter->getCode(),
            'url_param' => $param,
            'display_mode' => $filter->getDisplayMode(),
            'multiselect' => $filter->isMultiselect(),
            'logic' => $filter->getLogic(),
            'tooltip' => $filter->getTooltip(),
            'position' => $position,
        ];
    }

    /**
     * @param Filter $filter
     * @param Attribute|null $attribute
     * @param int|null $storeId
     * @return string
     */
    private function label(Filter $filter, ?Attribute $attribute, ?int $storeId): string
    {
        $label = $filter->getLabel();
        if ($label !== null && $label !== '') {
            return $label;
        }
        if ($filter->isBuiltin() && $filter->getCode() === Filter::BUILTIN_PRICE) {
            return (string) __('Price');
        }

        return $attribute === null ? $filter->getCode() : (string) $attribute->getStoreLabel($storeId);
    }

    /**
     * The picks of the `filter` argument by filter key, in the storefront's value format (option ids, "from-to").
     *
     * @param array<string, Filter> $filters
     * @param array<string, mixed> $filterArgs
     * @return FilterStateInterface
     */
    private function argState(array $filters, array $filterArgs): FilterStateInterface
    {
        $values = [];
        foreach ($filters as $key => $filter) {
            $code = $filter->getCode() === Filter::BUILTIN_CATEGORY && $filter->isBuiltin() ? 'category_id' : $filter->getCode();
            $arg = $filterArgs[$code] ?? null;
            $picked = $this->pickedValues($arg);
            if ($picked === [] && is_array($arg) && (isset($arg['from']) || isset($arg['to']))) {
                $picked = [sprintf('%s-%s', $arg['from'] ?? '', $arg['to'] ?? '')];
            }
            if ($picked !== []) {
                $values[(string) $key] = $picked;
            }
        }

        return new FilterState($values);
    }

    /**
     * Values of an `in` or `eq` filter argument.
     *
     * @param mixed $arg
     * @return string[]
     */
    private function pickedValues($arg): array
    {
        if (!is_array($arg)) {
            return [];
        }
        $values = isset($arg['in']) ? (array) $arg['in'] : (isset($arg['eq']) ? [$arg['eq']] : []);

        return array_values(array_map('strval', array_filter($values, 'is_scalar')));
    }

    /**
     * The category the query lists (`category_id` or `category_uid` with `eq`), if any.
     *
     * @param array<string, mixed> $filterArgs
     * @param int $storeId
     * @return int|null
     */
    private function pageCategory(array $filterArgs, int $storeId): ?int
    {
        $id = $filterArgs['category_id']['eq'] ?? null;
        if (!is_scalar($id) && is_scalar($filterArgs['category_uid']['eq'] ?? null)) {
            $uid = (string) $filterArgs['category_uid']['eq'];
            $id = $this->uid->isValidBase64($uid) ? $this->uid->decode($uid) : null;
        }
        $id = is_scalar($id) && ctype_digit((string) $id) ? (int) $id : null;

        return $id !== null && isset($this->tree->forStore($storeId)[$id]) ? $id : null;
    }

    /**
     * @param int $storeId
     * @return int|null
     */
    private function storeRoot(int $storeId): ?int
    {
        foreach ($this->tree->forStore($storeId) as $id => $node) {
            if ($node['parent'] === 1) {
                return $id;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $engine
     * @param string $code
     * @return array{min: float, max: float}|null
     */
    private function stats(array $engine, string $code): ?array
    {
        $stats = $engine[$code . '_bucket'] ?? null;
        if (!is_array($stats) || (int) ($stats['count'] ?? 0) === 0 || !is_numeric($stats['min'] ?? null) || !is_numeric($stats['max'] ?? null)) {
            return null;
        }

        return ['min' => (float) $stats['min'], 'max' => (float) $stats['max']];
    }

    /**
     * @param string $code
     * @return Attribute|null
     */
    private function attribute(string $code): ?Attribute
    {
        $attribute = $this->eavConfig->getAttribute(Product::ENTITY, $code);

        return $attribute instanceof Attribute && $attribute->getId() ? $attribute : null;
    }
}
