<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Ui\DataProvider;

use Iranimij\OpenFilter\Model\Landing\LandingPage;
use Iranimij\OpenFilter\Model\Landing\LandingPages;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Api\Filter;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * The landing pages grid (1.2): every page, newest first, with a keyword search over title and URL key.
 */
class LandingListing extends AbstractDataProvider
{
    private string $keyword = '';

    /** @var array{0: int, 1: int}|null offset, size */
    private ?array $page = null;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param LandingPages $pages
     * @param CategoryRepositoryInterface $categories
     * @param StoreManagerInterface $storeManager
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        private readonly LandingPages $pages,
        private readonly CategoryRepositoryInterface $categories,
        private readonly StoreManagerInterface $storeManager,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * @param Filter $filter
     * @return void
     */
    public function addFilter(Filter $filter)
    {
        if ($filter->getField() === 'fulltext') {
            $this->keyword = mb_strtolower(trim((string) $filter->getValue()));
        }
    }

    /**
     * @param string $field
     * @param string $direction
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function addOrder($field, $direction)
    {
    }

    /**
     * @param int $offset page number from 1
     * @param int $size
     * @return void
     */
    public function setLimit($offset, $size)
    {
        $this->page = [max(0, ((int) $offset - 1) * (int) $size), (int) $size];
    }

    /**
     * @return array{totalRecords: int, items: array<int, array<string, mixed>>}
     */
    public function getData()
    {
        $rows = [];
        foreach ($this->pages->getAll() as $page) {
            if ($this->keyword !== '' && !str_contains(mb_strtolower($page->getTitle() . ' ' . $page->getUrlKey()), $this->keyword)) {
                continue;
            }
            $rows[] = $this->row($page);
        }
        $total = count($rows);
        if ($this->page !== null && $this->page[1] > 0) {
            $rows = array_slice($rows, $this->page[0], $this->page[1]);
        }

        return ['totalRecords' => $total, 'items' => $rows];
    }

    /**
     * @param LandingPage $page
     * @return array<string, mixed>
     */
    private function row(LandingPage $page): array
    {
        try {
            $category = (string) $this->categories->get($page->getCategoryId())->getName();
        } catch (NoSuchEntityException $e) {
            $category = (string) $page->getCategoryId();
        }
        $stores = [];
        foreach ($page->getStoreIds() as $storeId) {
            $stores[] = $storeId === 0 ? (string) __('All store views') : (string) $this->storeManager->getStore($storeId)->getName();
        }

        return [
            'page_id' => $page->getId(),
            'title' => $page->getTitle(),
            'url_key' => $page->getUrlKey(),
            'category' => $category,
            'filters' => count($page->getFilters()),
            'store_ids' => implode(', ', $stores),
            'is_active' => (int) $page->isActive(),
            'robots' => $page->getRobots(),
        ];
    }
}
