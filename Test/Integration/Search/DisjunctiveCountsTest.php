<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Search;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Search\StrategyResolver;
use Magento\Catalog\Model\Layer\Category as CategoryLayer;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * M0 search spike (07 · Build Brief §4.5): disjunctive facet counts from each strategy equal brute-force counts,
 * and the number of search requests each strategy adds over core, measured on the search engine itself.
 *
 * Disjunctive count of option o of attribute A = products in the category matching every active filter except
 * the ones on A, plus A = o. Brute force asks the engine once per option with OpenFilter switched off.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class DisjunctiveCountsTest extends TestCase
{
    /** @var array{category: int, options: array<string, array<string, int>>} */
    private static ?array $catalog = null;

    public static function setUpBeforeClass(): void
    {
        self::$catalog = SpikeCatalog::create();
    }

    public static function tearDownAfterClass(): void
    {
        SpikeCatalog::remove();
    }

    /**
     * @return array<string, array{string, array<string, string[]>}>
     */
    public static function cases(): array
    {
        $cases = [];
        $selections = [
            'one value' => [SpikeCatalog::COLOR => ['Red']],
            'two values of one attribute' => [SpikeCatalog::COLOR => ['Red', 'Blue']],
            'two attributes' => [SpikeCatalog::COLOR => ['Red', 'Blue'], SpikeCatalog::SIZE => ['M']],
            'three attributes' => [
                SpikeCatalog::COLOR => ['Red', 'Black'],
                SpikeCatalog::SIZE => ['S', 'L'],
                SpikeCatalog::MATERIAL => ['Wool'],
            ],
        ];
        foreach ([StrategyResolver::SINGLE_QUERY, StrategyResolver::PER_ATTRIBUTE] as $strategy) {
            foreach ($selections as $name => $selection) {
                $cases[$strategy . ': ' . $name] = [$strategy, $selection];
            }
        }

        return $cases;
    }

    /**
     * @dataProvider cases
     * @param string $strategy
     * @param array<string, string[]> $selection
     */
    #[DataProvider('cases')]
    public function testCountsEqualBruteForce(string $strategy, array $selection): void
    {
        $this->useStrategy($strategy);
        $active = $this->ids($selection);

        foreach (array_keys(SpikeCatalog::OPTIONS) as $code) {
            $expected = $this->bruteForce($active, $code);
            $actual = $this->facetCounts($active, $code);
            self::assertSame($expected, $actual, sprintf('%s counts with %s', $code, json_encode($selection)));
        }
    }

    /**
     * @dataProvider cases
     * @param string $strategy
     * @param array<string, string[]> $selection
     */
    #[DataProvider('cases')]
    public function testSearchRequestsAddedOverCore(string $strategy, array $selection): void
    {
        $active = $this->ids($selection);
        $this->enabled(false);
        $core = $this->countRequests(fn () => $this->facetCounts($active, SpikeCatalog::COLOR));
        $this->enabled(true);
        $this->useStrategy($strategy);
        $openFilter = $this->countRequests(fn () => $this->facetCounts($active, SpikeCatalog::COLOR));

        $expected = $strategy === StrategyResolver::SINGLE_QUERY ? 0 : count($active);
        self::assertSame($expected, $openFilter - $core, "requests added by $strategy (core: $core)");
    }

    public function testAutoResolvesToAStrategyForThisEngine(): void
    {
        $this->useStrategy(StrategyResolver::AUTO);
        $code = Bootstrap::getObjectManager()->create(StrategyResolver::class)->resolve()->getCode();

        self::assertContains($code, [StrategyResolver::SINGLE_QUERY, StrategyResolver::PER_ATTRIBUTE]);
    }

    public function testWithoutActiveFiltersCountsAreCoreCounts(): void
    {
        $this->useStrategy(StrategyResolver::SINGLE_QUERY);
        $withOpenFilter = $this->facetCounts([], SpikeCatalog::SIZE);
        $this->enabled(false);
        $core = $this->facetCounts([], SpikeCatalog::SIZE);
        $this->enabled(true);

        self::assertSame($core, $withOpenFilter);
        self::assertSame(36, array_sum($core));
    }

    /**
     * @param array<string, int[]> $active
     * @param string $code
     * @return array<int, int> count by option id, options with at least one product
     */
    private function bruteForce(array $active, string $code): array
    {
        $this->enabled(false);
        $others = $active;
        unset($others[$code]);
        $counts = [];
        foreach (self::$catalog['options'][$code] as $optionId) {
            $size = $this->collection($others + [$code => [$optionId]])->getSize();
            if ($size > 0) {
                $counts[$optionId] = $size;
            }
        }
        $this->enabled(true);
        ksort($counts);

        return $counts;
    }

    /**
     * @param array<string, int[]> $active
     * @param string $code
     * @return array<int, int>
     */
    private function facetCounts(array $active, string $code): array
    {
        $counts = [];
        foreach ($this->collection($active)->getFacetedData($code) as $value => $metrics) {
            if ((int) $metrics['count'] > 0) {
                $counts[(int) $value] = (int) $metrics['count'];
            }
        }
        ksort($counts);

        return $counts;
    }

    /**
     * @param array<string, int[]> $active
     * @return Collection
     */
    private function collection(array $active): Collection
    {
        $om = Bootstrap::getObjectManager();
        // Core's fulltext collection takes the search criteria builder from ObjectManager::get(): one instance per
        // process whose filters are never cleared, so every collection would inherit the previous one's filters.
        // A storefront request builds one layer collection; tests build many, so they start from a fresh builder.
        $om->removeSharedInstance(SearchCriteriaBuilder::class);
        $layer = $om->create(CategoryLayer::class);
        $layer->setCurrentCategory(self::$catalog['category']);
        /** @var Collection $collection */
        $collection = $layer->getProductCollection();
        foreach ($active as $code => $ids) {
            $collection->addFieldToFilter($code, array_values($ids));
        }

        return $collection;
    }

    /**
     * @param array<string, string[]> $selection
     * @return array<string, int[]>
     */
    private function ids(array $selection): array
    {
        $ids = [];
        foreach ($selection as $code => $labels) {
            $ids[$code] = array_map(static fn (string $label): int => self::$catalog['options'][$code][$label], $labels);
        }

        return $ids;
    }

    /**
     * @param callable $callback
     * @return int search requests the engine served while the callback ran
     */
    private function countRequests(callable $callback): int
    {
        $before = $this->engineQueryTotal();
        $callback();

        return $this->engineQueryTotal() - $before;
    }

    /**
     * Sum of indices.search.query_total over all nodes (one per request on a one-shard index).
     *
     * @return int
     */
    private function engineQueryTotal(): int
    {
        $om = Bootstrap::getObjectManager();
        $config = $om->get(ScopeConfigInterface::class);
        $engine = (string) $config->getValue('catalog/search/engine');
        $host = (string) $config->getValue("catalog/search/{$engine}_server_hostname");
        $port = (string) $config->getValue("catalog/search/{$engine}_server_port");
        $curl = $om->create(Curl::class);
        $curl->get(sprintf('http://%s:%s/_nodes/stats/indices/search', $host, $port));
        $stats = json_decode($curl->getBody(), true);
        $total = 0;
        foreach ($stats['nodes'] ?? [] as $node) {
            $total += (int) $node['indices']['search']['query_total'];
        }

        return $total;
    }

    /**
     * @param string $strategy
     */
    private function useStrategy(string $strategy): void
    {
        Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class)
            ->setValue(StrategyResolver::XML_PATH_STRATEGY, $strategy);
    }

    /**
     * @param bool $enabled
     */
    private function enabled(bool $enabled): void
    {
        Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class)
            ->setValue(Config::XML_PATH_ENABLED, $enabled ? '1' : '0', 'store', 'default');
    }
}
