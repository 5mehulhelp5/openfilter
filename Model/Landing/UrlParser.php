<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Landing;

use Iranimij\OpenFilter\Api\UrlCodecInterface;
use Iranimij\OpenFilter\Model\Url\PathUrl;
use Laminas\Uri\Http as HttpUri;
use Magento\Framework\Exception\InputException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

/**
 * Reads the address of a filtered category page (copied from the storefront) into its category and filter state, for
 * landing pages (1.2): the merchant picks filters the way shoppers do. Query and path filter URLs, URL names or
 * option ids, with or without the store's base URL.
 */
class UrlParser
{
    /**
     * @param StoreManagerInterface $storeManager
     * @param UrlFinderInterface $urlFinder
     * @param PathUrl $pathUrl
     * @param UrlCodecInterface $codec
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlFinderInterface $urlFinder,
        private readonly PathUrl $pathUrl,
        private readonly UrlCodecInterface $codec
    ) {
    }

    /**
     * @param string $url
     * @param int $storeId the store view whose URL names and category URLs the address uses
     * @return array{category_id: int, filters: array<string, string[]>}
     * @throws InputException when the address is no filtered category page of the store view
     */
    public function parse(string $url, int $storeId): array
    {
        $uri = new HttpUri(trim($url));
        $path = trim((string) $uri->getPath(), '/');
        $store = $this->storeManager->getStore($storeId);
        $base = $store instanceof Store ? trim((string) (new HttpUri($store->getBaseUrl()))->getPath(), '/') : '';
        foreach (array_filter([$base, 'index.php']) as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                $path = trim(substr($path, strlen($prefix)), '/');
            }
        }
        $query = [];
        foreach ($uri->getQueryAsArray() as $name => $value) {
            if (is_scalar($value)) {
                $query[(string) $name] = (string) $value;
            }
        }
        $parsed = $this->pathUrl->isActive($storeId) ? $this->pathUrl->parse($path, $storeId) : null;
        if ($parsed !== null) {
            [$path, $params] = $parsed;
            $query = $params + $query;
        }
        $categoryId = $this->categoryId($path, $storeId);
        if ($categoryId === null) {
            throw new InputException(__('"%1" is not the address of a category page in this store view.', $url));
        }
        $filters = $this->codec->decode($query, $storeId)->getValues();
        if ($filters === []) {
            throw new InputException(__('"%1" has no filters. Pick filters on the category page, then copy its address.', $url));
        }

        return ['category_id' => $categoryId, 'filters' => $filters];
    }

    /**
     * @param string $path
     * @param int $storeId
     * @return int|null
     */
    private function categoryId(string $path, int $storeId): ?int
    {
        if (preg_match('#^catalog/category/view/(?:.*/)?id/(\d+)#', $path, $match) === 1) {
            return (int) $match[1];
        }
        $rewrite = $this->urlFinder->findOneByData([
            UrlRewrite::REQUEST_PATH => $path,
            UrlRewrite::STORE_ID => $storeId,
            UrlRewrite::ENTITY_TYPE => 'category',
        ]);

        return $rewrite === null ? null : (int) $rewrite->getEntityId();
    }
}
