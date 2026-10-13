<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Catalog;

use Magento\Eav\Model\Config as EavConfig;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;

/**
 * Core's search indexer keeps process-wide caches of the searchable attributes and their mapping. Attributes a test
 * creates after another test indexed would never reach the index, so catalog fixtures start the indexer fresh.
 */
class SearchIndexReset
{
    /** Shared instances holding attribute lists or mappings (Magento 2.4.7–2.4.9). */
    private const CACHED = [
        \Magento\CatalogSearch\Model\Indexer\Fulltext\Action\DataProvider::class,
        \Magento\CatalogSearch\Model\Indexer\Fulltext\Action\Full::class,
        \Magento\Elasticsearch\Model\Adapter\FieldMapper\Product\AttributeProvider::class,
        \Magento\Elasticsearch\Model\Adapter\BatchDataMapper\ProductDataMapper::class,
        \Magento\Elasticsearch\Model\Adapter\Elasticsearch::class,
        \Magento\Framework\Search\Request\Config::class,
    ];

    /**
     * @return void
     */
    public static function forNewAttributes(): void
    {
        /** @var ObjectManager $om */
        $om = Bootstrap::getObjectManager();
        $om->get(EavConfig::class)->clear();
        foreach (self::CACHED as $class) {
            $om->removeSharedInstance($class);
        }
    }
}
