<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Migration;

use Iranimij\OpenFilter\Api\UrlCodecInterface;
use Iranimij\OpenFilter\Model\Url\PathUrl;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

/**
 * Turns an old filter URL of another layered navigation module into the OpenFilter URL of the same category and
 * filters (1.2 migration): the longest category path at the start of the address (one URL rewrite query for all of
 * them), then the rest read by OldUrlParser. Query parameters stay (Magento's price=10-20 is OpenFilter's too).
 */
class OldUrlResolver
{
    public const XML_PATH_ENABLED = 'openfilter/migration/redirects';
    public const XML_PATH_FILTER_KEYS = 'openfilter/migration/filter_keys';

    /** Path segments read at most (a category path plus its filters). */
    private const MAX_SEGMENTS = 12;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param UrlFinderInterface $urlFinder
     * @param Vocabulary $vocabulary
     * @param OldUrlParser $parser
     * @param UrlCodecInterface $codec
     * @param PathUrl $pathUrl
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlFinderInterface $urlFinder,
        private readonly Vocabulary $vocabulary,
        private readonly OldUrlParser $parser,
        private readonly UrlCodecInterface $codec,
        private readonly PathUrl $pathUrl
    ) {
    }

    /**
     * Whether old filter URLs redirect in a store view (off by default).
     *
     * @param int $storeId
     * @return bool
     */
    public function isEnabled(int $storeId): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * The OpenFilter URL of an old filter URL, or null when the address is not one.
     *
     * @param string $path the request path without the store's base path ("women/tops-women/black-blue.html")
     * @param array<string, string> $query the request's query parameters
     * @param int $storeId
     * @return string|null
     */
    public function resolve(string $path, array $query, int $storeId): ?string
    {
        $suffix = (string) $this->scopeConfig->getValue(PathUrl::SUFFIX_PATH, ScopeInterface::SCOPE_STORE, $storeId);
        $path = trim($path, '/');
        if ($suffix !== '' && str_ends_with($path, $suffix)) {
            $path = substr($path, 0, -strlen($suffix));
        }
        $segments = explode('/', $path);
        if (count($segments) < 2 || count($segments) > self::MAX_SEGMENTS) {
            return null;
        }
        $candidates = [];
        for ($length = count($segments) - 1; $length >= 1; $length--) {
            $candidates[implode('/', array_slice($segments, 0, $length)) . $suffix] = $length;
        }
        $category = null;
        foreach ($this->urlFinder->findAllByData([
            UrlRewrite::REQUEST_PATH => array_keys($candidates),
            UrlRewrite::STORE_ID => $storeId,
            UrlRewrite::ENTITY_TYPE => 'category',
            UrlRewrite::REDIRECT_TYPE => 0,
        ]) as $rewrite) {
            $length = $candidates[$rewrite->getRequestPath()] ?? 0;
            if ($category === null || $length > $category[1]) {
                $category = [$rewrite->getRequestPath(), $length];
            }
        }
        if ($category === null) {
            return null;
        }
        $vocabulary = $this->vocabulary->forStore($storeId);
        $picks = $this->parser->parse(
            implode('/', array_slice($segments, $category[1])),
            $vocabulary,
            $this->filterKeys($storeId)
        );
        if ($picks === null) {
            return null;
        }
        $params = [];
        foreach ($picks as $key => $slugs) {
            $params[$vocabulary['params'][$key]] = implode(',', $slugs);
        }
        $names = $this->codec->getParameterNames($storeId);
        $state = $this->codec->decode($params + array_intersect_key($query, $names), $storeId);
        $keep = array_diff_key($query, $names);
        $store = $this->storeManager->getStore($storeId);
        $base = ($store instanceof Store ? rtrim($store->getBaseUrl(), '/') : '') . '/' . $category[0];

        return $this->pathUrl->isActive($storeId)
            ? $this->pathUrl->build($state, $base, $keep, $storeId)
            : $this->codec->urlFor($state, $base, $keep, $storeId);
    }

    /**
     * @param int $storeId
     * @return string[]
     */
    private function filterKeys(int $storeId): array
    {
        $keys = (string) $this->scopeConfig->getValue(self::XML_PATH_FILTER_KEYS, ScopeInterface::SCOPE_STORE, $storeId);

        return array_values(array_filter(array_map('trim', explode(',', $keys))));
    }
}
