<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Ui\DataProvider;

use Iranimij\OpenFilter\Model\Landing\LandingPage;
use Iranimij\OpenFilter\Model\Landing\LandingPages;
use Iranimij\OpenFilter\Model\Landing\LandingUrl;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\Api\Filter;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * The landing page form (1.2): the saved page (or the values of a save that failed), and what its filters are.
 */
class LandingForm extends AbstractDataProvider
{
    public const PERSISTOR_KEY = 'openfilter_landing';

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param LandingPages $pages
     * @param LandingUrl $landingUrl
     * @param CategoryRepositoryInterface $categories
     * @param RequestInterface $request
     * @param DataPersistorInterface $dataPersistor the values of a save that failed
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        private readonly LandingPages $pages,
        private readonly LandingUrl $landingUrl,
        private readonly CategoryRepositoryInterface $categories,
        private readonly RequestInterface $request,
        private readonly DataPersistorInterface $dataPersistor,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * The form asks for its record with a filter on the primary field; the request carries the id already.
     *
     * @param Filter $filter
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function addFilter(Filter $filter)
    {
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function getData()
    {
        $pageId = (int) $this->request->getParam('page_id');
        $key = $pageId === 0 ? '' : $pageId;
        $failed = $this->dataPersistor->get(self::PERSISTOR_KEY);
        $this->dataPersistor->clear(self::PERSISTOR_KEY);
        if (is_array($failed) && $failed !== []) {
            return [$key => $failed];
        }
        if ($pageId === 0) {
            return ['' => ['is_active' => '1', 'store_ids' => ['0'], 'robots' => LandingPage::ROBOTS[0]]];
        }
        try {
            $page = $this->pages->get($pageId);
        } catch (NoSuchEntityException $e) {
            return [];
        }

        return [$pageId => array_map(
            static fn ($value) => is_bool($value) ? (string) (int) $value : $value,
            array_diff_key($page->toArray(), ['filters' => true, 'updated_at' => true])
        ) + ['store_ids' => array_map('strval', $page->getStoreIds())]];
    }

    /**
     * The filters of a saved page and a link to it, next to the address field.
     *
     * @return array<string, mixed>
     */
    public function getMeta()
    {
        $meta = parent::getMeta();
        $pageId = (int) $this->request->getParam('page_id');
        if ($pageId === 0) {
            return $meta;
        }
        try {
            $page = $this->pages->get($pageId);
            $category = (string) $this->categories->get($page->getCategoryId())->getName();
        } catch (NoSuchEntityException $e) {
            return $meta;
        }
        $filters = [];
        foreach ($page->getFilters() as $key => $values) {
            $filters[] = $key . ': ' . implode(', ', $values);
        }
        $meta['general']['children']['page_url']['arguments']['data']['config']['notice'] = (string) __(
            'Now: %1 with %2. Leave empty to keep these filters. The page: %3',
            $category,
            implode('; ', $filters),
            $this->landingUrl->url($page)
        );

        return $meta;
    }
}
