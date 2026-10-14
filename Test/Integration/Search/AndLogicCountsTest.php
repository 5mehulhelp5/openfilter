<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Search;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Search\StrategyResolver;
use Iranimij\OpenFilter\Test\Integration\Catalog\M1Catalog;
use Magento\Catalog\Model\Layer\Category as CategoryLayer;
use Magento\Catalog\Model\Layer\Search as SearchLayer;
use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * M5 S2 "Products must match all of the picked values": result sizes and counts equal two oracles for both
 * strategies, on category and search pages, two store views and a customer group — engine brute force with
 * OpenFilter off (all of = the intersection of one-value results) and the catalog's PHP model (a product has
 * every picked value). Configurables carry the colours and sizes of their children, so "Red and Blue" matches.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 * @magentoCache openfilter enabled
 */
class AndLogicCountsTest extends TestCase
{
    /** @var array{category: int, store: int, options: array<string, array<string, int>>}|null */
    private static ?array $catalog = null;

    public static function setUpBeforeClass(): void
    {
        self::$catalog = M1Catalog::create();
    }

    protected function setUp(): void
    {
        $this->logic([M1Catalog::COLOR => Filter::LOGIC_AND, M1Catalog::SIZE => Filter::LOGIC_AND]);
    }

    protected function tearDown(): void
    {
        $this->logic([M1Catalog::COLOR => Filter::LOGIC_OR, M1Catalog::SIZE => Filter::LOGIC_OR]);
    }

    /**
     * @return array<string, array{string, array{page: string, store: string, group: int,
     *     selection: array<string, string[]>}}>
     */
    public static function cases(): array
    {
        $scenarios = [
            'category, two colours (all of)' => [
                'page' => 'category', 'store' => 'default', 'group' => 0,
                'selection' => [M1Catalog::COLOR => ['Red', 'Blue']],
            ],
            'category, two colours and two sizes (all of each) and a material (any of)' => [
                'page' => 'category', 'store' => 'default', 'group' => 1,
                'selection' => [M1Catalog::COLOR => ['Red', 'Green'], M1Catalog::SIZE => ['L', 'S'], M1Catalog::MATERIAL => ['Wool', 'Cotton']],
            ],
            'category, one colour (all of) and a material, German store view' => [
                'page' => 'category', 'store' => M1Catalog::STORE, 'group' => 3,
                'selection' => [M1Catalog::COLOR => ['Black'], M1Catalog::MATERIAL => ['Cotton']],
            ],
            'search page, two sizes (all of)' => [
                'page' => 'search', 'store' => 'default', 'group' => 0,
                'selection' => [M1Catalog::SIZE => ['S', 'M']],
            ],
        ];
        $cases = [];
        foreach ([StrategyResolver::SINGLE_QUERY, StrategyResolver::PER_ATTRIBUTE] as $strategy) {
            foreach ($scenarios as $name => $scenario) {
                $cases["$strategy: $name"] = [$strategy, $scenario];
            }
        }

        return $cases;
    }

    /**
     * @dataProvider cases
     * @param array{page: string, store: string, group: int, selection: array<string, string[]>} $scenario
     */
    #[DataProvider('cases')]
    public function testCountsEqualBruteForceAndTheCatalogModel(string $strategy, array $scenario): void
    {
        $om = Bootstrap::getObjectManager();
        $om->get(StoreManagerInterface::class)->setCurrentStore($scenario['store']);
        $om->get(CustomerSession::class)->setCustomerGroupId($scenario['group']);
        $om->get(MutableScopeConfigInterface::class)->setValue(StrategyResolver::XML_PATH_STRATEGY, $strategy);
        $active = $this->ids($scenario['selection']);

        $expectedIds = $this->bruteForceIds($scenario, $active);
        self::assertCount(count($this->modelMatches($scenario, $scenario['selection'])), $expectedIds, 'oracles agree');
        self::assertSame($expectedIds, $this->sortedIds($this->collection($scenario, $active)));
        foreach (array_keys(M1Catalog::OPTIONS) as $code) {
            $label = sprintf('%s counts, %s', $code, json_encode($scenario['selection']));
            $facets = $this->facetCounts($scenario, $active, $code);
            self::assertSame($this->bruteForceCounts($scenario, $active, $code), $facets, "$label: brute force");
            self::assertSame($this->modelCounts($scenario, $code), $facets, "$label: catalog model");
        }
    }

    public function testAllOfReallyNarrows(): void
    {
        $om = Bootstrap::getObjectManager();
        $om->get(StoreManagerInterface::class)->setCurrentStore('default');
        $scenario = ['page' => 'category', 'store' => 'default', 'group' => 0, 'selection' => []];

        $all = $this->collection($scenario, $this->ids([M1Catalog::COLOR => ['Red', 'Blue']]))->getSize();
        $this->logic([M1Catalog::COLOR => Filter::LOGIC_OR]);
        $any = $this->collection($scenario, $this->ids([M1Catalog::COLOR => ['Red', 'Blue']]))->getSize();

        self::assertSame(1, $all, 'only the configurable with red and blue children');
        self::assertGreaterThan(10, $any);
    }

    /**
     * Engine brute force with OpenFilter off: "all of" values intersect one-value results, "any of" values are one
     * filter as core builds it.
     *
     * @param array<string, mixed> $scenario
     * @param array<string, int[]> $active
     * @return int[] sorted product ids
     */
    private function bruteForceIds(array $scenario, array $active): array
    {
        $this->enabled(false);
        $anyOf = array_diff_key($active, array_flip([M1Catalog::COLOR, M1Catalog::SIZE]));
        $ids = $this->sortedIds($this->collection($scenario, $anyOf));
        foreach ([M1Catalog::COLOR, M1Catalog::SIZE] as $code) {
            foreach ($active[$code] ?? [] as $value) {
                $ids = array_values(array_intersect($ids, $this->sortedIds($this->collection($scenario, [$code => [$value]]))));
            }
        }
        $this->enabled(true);

        return $ids;
    }

    /**
     * An option's count: products matching every other filter and this option — for an "all of" attribute
     * together with its own picked values, for an "any of" attribute instead of them.
     *
     * @param array<string, mixed> $scenario
     * @param array<string, int[]> $active
     * @param string $code
     * @return array<int, int>
     */
    private function bruteForceCounts(array $scenario, array $active, string $code): array
    {
        $allOf = in_array($code, [M1Catalog::COLOR, M1Catalog::SIZE], true);
        $counts = [];
        foreach (self::$catalog['options'][$code] as $optionId) {
            $selection = $active;
            $selection[$code] = $allOf ? array_values(array_unique([...($active[$code] ?? []), $optionId])) : [$optionId];
            $size = count($this->bruteForceIds($scenario, $selection));
            if ($size > 0) {
                $counts[$optionId] = $size;
            }
        }
        ksort($counts);

        return $counts;
    }

    /**
     * @param array{page: string, store: string, group: int, selection: array<string, string[]>} $scenario
     * @param string $code
     * @return array<int, int>
     */
    private function modelCounts(array $scenario, string $code): array
    {
        $allOf = in_array($code, [M1Catalog::COLOR, M1Catalog::SIZE], true);
        $selection = $scenario['selection'];
        if (!$allOf) {
            unset($selection[$code]);
        }
        $counts = [];
        foreach ($this->modelMatches($scenario, $selection) as $product) {
            foreach ($this->values($product, $code) as $label) {
                $id = self::$catalog['options'][$code][$label];
                $counts[$id] = ($counts[$id] ?? 0) + 1;
            }
        }
        ksort($counts);

        return $counts;
    }

    /**
     * @param array{page: string, store: string, group: int, selection: array<string, string[]>} $scenario
     * @param array<string, string[]> $selection
     * @return array<string, array<string, mixed>>
     */
    private function modelMatches(array $scenario, array $selection): array
    {
        return array_filter(M1Catalog::products(), function (array $product) use ($scenario, $selection): bool {
            if (($scenario['page'] === 'category' && !$product['category'])
                || ($scenario['store'] === M1Catalog::STORE && !$product['german'])) {
                return false;
            }
            foreach ($selection as $code => $labels) {
                $values = $this->values($product, $code);
                $allOf = in_array($code, [M1Catalog::COLOR, M1Catalog::SIZE], true);
                if ($allOf ? array_diff($labels, $values) !== [] : array_intersect($values, $labels) === []) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * @param array<string, mixed> $product
     * @param string $code
     * @return string[]
     */
    private function values(array $product, string $code): array
    {
        return match ($code) {
            M1Catalog::COLOR => $product['colors'],
            M1Catalog::SIZE => $product['sizes'],
            default => [$product['material']],
        };
    }

    /**
     * @param array<string, mixed> $scenario
     * @param array<string, int[]> $active
     * @param string $code
     * @return array<int, int>
     */
    private function facetCounts(array $scenario, array $active, string $code): array
    {
        $counts = [];
        foreach ($this->collection($scenario, $active)->getFacetedData($code) as $value => $metrics) {
            if ((int) $metrics['count'] > 0) {
                $counts[(int) $value] = (int) $metrics['count'];
            }
        }
        ksort($counts);

        return $counts;
    }

    /**
     * @param array<string, mixed> $scenario
     * @param array<string, int[]> $active
     * @return Collection
     */
    private function collection(array $scenario, array $active): Collection
    {
        $om = Bootstrap::getObjectManager();
        // A fresh criteria builder per collection (core shares one per process, see 02 §11).
        $om->removeSharedInstance(SearchCriteriaBuilder::class);
        if ($scenario['page'] === 'search') {
            /** @var Collection $collection */
            $collection = $om->create(SearchLayer::class)->getProductCollection();
            $collection->addSearchFilter(M1Catalog::TOKEN);
        } else {
            $layer = $om->create(CategoryLayer::class);
            $layer->setCurrentCategory(self::$catalog['category']);
            /** @var Collection $collection */
            $collection = $layer->getProductCollection();
        }
        foreach ($active as $code => $ids) {
            $collection->addFieldToFilter($code, count($ids) === 1 ? reset($ids) : array_values($ids));
        }

        return $collection;
    }

    /**
     * @param Collection $collection
     * @return int[]
     */
    private function sortedIds(Collection $collection): array
    {
        // Loading renders the search filters (getAllIds() would read the plain product table).
        $collection->setPageSize(1000);
        $ids = array_map('intval', $collection->getColumnValues('entity_id'));
        sort($ids);

        return $ids;
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
     * @param array<string, string> $logic attribute code => or / and
     */
    private function logic(array $logic): void
    {
        $om = Bootstrap::getObjectManager();
        $repository = $om->get(FilterConfigRepositoryInterface::class);
        foreach ($logic as $code => $value) {
            $repository->save($repository->getByCode($code)->setLogic($value));
        }
        $om->get(CacheType::class)->clean();
        $om->get(FilterConfigProvider::class)->reset();
    }

    /**
     * @param bool $enabled
     */
    private function enabled(bool $enabled): void
    {
        $store = Bootstrap::getObjectManager()->get(StoreManagerInterface::class)->getStore()->getCode();
        Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class)
            ->setValue(Config::XML_PATH_ENABLED, $enabled ? '1' : '0', 'store', $store);
    }
}
