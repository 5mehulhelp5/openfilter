<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Ui\DataProvider;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface;
use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;

/**
 * Rows of Catalog > OpenFilter > Filters, from FilterConfigRepositoryInterface::getList() (no collection: the list
 * lives in the OpenFilter cache). The grid's filters, sorting and paging arrive as search criteria.
 */
class FilterListing extends DataProvider
{
    /** @var array<string, int>|null */
    private ?array $ranks = null;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ReportingInterface $reporting
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param FilterConfigRepositoryInterface $repository
     * @param FilterConfigProvider $provider
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        private readonly FilterConfigRepositoryInterface $repository,
        private readonly FilterConfigProvider $provider,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data
        );
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, totalRecords: int}
     */
    public function getData()
    {
        $results = $this->repository->getList($this->getSearchCriteria());

        return [
            'items' => array_values(array_map([$this, 'row'], $results->getItems())),
            'totalRecords' => $results->getTotalCount(),
        ];
    }

    /**
     * Keys of every row matching the current criteria, ignoring paging (mass actions).
     *
     * @return string[]
     */
    public function getMatchingKeys(): array
    {
        $this->setLimit(1, 0);

        return array_map([$this, 'keyOf'], $this->repository->getList($this->getSearchCriteria())->getItems());
    }

    /**
     * @param FilterConfigInterface $config
     * @return array<string, mixed>
     */
    public function row(FilterConfigInterface $config): array
    {
        return [
            'key' => $this->keyOf($config),
            'code' => $config->getCode(),
            'label' => (string) $config->getLabel(),
            'builtin' => (int) $config->isBuiltin(),
            'input_type' => $config->getInputType(),
            'display_mode' => $config->getDisplayMode(),
            'multiselect' => (int) $config->isMultiselect(),
            'visible_options' => $config->getVisibleOptions(),
            'search_threshold' => $config->getSearchThreshold(),
            'seo_indexable' => (int) $config->isSeoIndexable(),
            'position' => $config->getPosition(),
            'order' => $this->ranks()[$this->keyOf($config)] ?? null,
            'logic' => $config->getLogic(),
            'where' => match ($config->getCategoryMode()) {
                FilterConfigInterface::CATEGORIES_ONLY => (string) __('Only in %1 categories', count($config->getCategoryIds())),
                FilterConfigInterface::CATEGORIES_EXCEPT => (string) __('Except %1 categories', count($config->getCategoryIds())),
                default => (string) __('Every category'),
            },
            'active' => (int) $config->isActive(),
        ];
    }

    /**
     * The place of each filter on the storefront (1, 2, 3 …): by position, ties in Magento's attribute order, as the
     * storefront sorts them; hidden filters keep their place.
     *
     * @return array<string, int> filter key => place
     */
    private function ranks(): array
    {
        if ($this->ranks === null) {
            $configs = array_values($this->provider->getForStore(0));
            $order = array_keys($configs);
            usort($order, static fn (int $a, int $b): int => [$configs[$a]->getPosition(), $a] <=> [$configs[$b]->getPosition(), $b]);
            $this->ranks = [];
            foreach ($order as $place => $index) {
                $this->ranks[$this->keyOf($configs[$index])] = $place + 1;
            }
        }

        return $this->ranks;
    }

    /**
     * @param FilterConfigInterface $config
     * @return string
     */
    private function keyOf(FilterConfigInterface $config): string
    {
        return $this->provider->keyOf($config);
    }
}
