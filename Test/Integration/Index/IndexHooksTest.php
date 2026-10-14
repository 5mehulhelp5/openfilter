<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Index;

use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProvider;
use Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProviderInterface;
use Magento\Catalog\Model\Indexer\Product\Price as PriceIndexer;
use Magento\Elasticsearch\Model\Adapter\BatchDataMapper\ProductDataMapper;
use Magento\Framework\Interception\PluginListInterface;
use Magento\Framework\Mview\ConfigInterface as MviewConfig;
use Magento\Framework\ObjectManager\ConfigInterface as DiConfig;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * M0 verification (07 · Build Brief §4.6): the core hooks OpenFilter's built-in index fields (rating, in stock,
 * on sale, new) plug into exist on this Magento version, and which catalog changes reindex a product's search
 * document by themselves. Findings: docs/index-fields.md.
 */
class IndexHooksTest extends TestCase
{
    public function testAdditionalFieldsProviderPoolIsTheExtensionPointOfTheProductDataMapper(): void
    {
        $config = Bootstrap::getObjectManager()->get(DiConfig::class);
        $mapperArguments = $config->getArguments(ProductDataMapper::class);
        $pool = $mapperArguments['additionalFieldsProvider']['instance'] ?? null;

        self::assertSame('additionalFieldsProviderForElasticsearch', $pool);
        self::assertSame(AdditionalFieldsProvider::class, ltrim($config->getInstanceType($pool), '\\'));
        $providers = $config->getArguments($pool)['fieldsProviders'] ?? [];
        self::assertArrayHasKey('prices', $providers, 'core registers its price fields here too');
    }

    public function testAProviderInThePoolAddsFieldsToTheDocument(): void
    {
        $probe = new class implements AdditionalFieldsProviderInterface {
            /**
             * @param array<int, int> $productIds
             * @param int $storeId
             * @return array<int, array<string, int>>
             */
            public function getFields(array $productIds, $storeId)
            {
                return array_fill_keys($productIds, ['of_probe' => (int) $storeId]);
            }
        };
        $pool = Bootstrap::getObjectManager()->create(
            AdditionalFieldsProvider::class,
            ['fieldsProviders' => ['probe' => $probe]]
        );

        self::assertSame([7 => ['of_probe' => 1], 9 => ['of_probe' => 1]], $pool->getFields([7, 9], 1));
    }

    public function testFieldMappingProvidersAreSharedByOpenSearchAndElasticsearch(): void
    {
        $config = Bootstrap::getObjectManager()->get(DiConfig::class);
        $providers = $config->getArguments('elasticsearch5FieldProvider')['providers'] ?? [];

        self::assertArrayHasKey('static', $providers);
        self::assertArrayHasKey('dynamic', $providers);
    }

    public function testSearchIndexFollowsPricesAndCatalogRulesButNotReviews(): void
    {
        $view = Bootstrap::getObjectManager()->get(MviewConfig::class)->getView('catalogsearch_fulltext');
        $tables = array_keys($view['subscriptions'] ?? []);

        self::assertContains('catalog_product_entity_decimal', $tables, 'price, special price');
        self::assertContains('catalog_product_entity_datetime', $tables, 'special and news dates');
        if (!in_array('catalogrule_product_price', $tables, true)) {
            // 2.4.7: the search mview does not follow catalog rule prices. The catalog rule indexer reindexes prices
            // (price indexer reindexList, or the price mview when scheduled), and OpenFilter's plugin on the price
            // indexer turns that into a search reindex of those products.
            $plugins = Bootstrap::getObjectManager()->get(PluginListInterface::class)
                ->getNext(PriceIndexer::class, 'executeList');
            self::assertNotEmpty($plugins, 'catalog price rules reach the search index through the price indexer');
        }
        self::assertNotContains('review_entity_summary', $tables, 'rating changes need OpenFilter\'s own trigger (M1)');
        self::assertNotContains('cataloginventory_stock_item', $tables, 'stock reaches the index through inventory code');
    }
}
