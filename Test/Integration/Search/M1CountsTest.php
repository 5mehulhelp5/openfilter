<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Search;

use Iranimij\OpenFilter\Model\Config;
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
 * M1 "done when": disjunctive counts equal brute force on simple and configurable products, three customer groups
 * and two store views, for both strategies, on category and search pages. A second oracle, the catalog's own PHP
 * model, checks the brute force itself.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class M1CountsTest extends TestCase
{
    /** @var array{category: int, store: int, options: array<string, array<string, int>>} */
    private static array $catalog;

    public static function setUpBeforeClass(): void
    {
        self::$catalog = M1Catalog::create();
    }

    /**
     * @return array<string, array{string, array{page: string, store: string, group: int, price: ?array{float, float},
     *     selection: array<string, string[]>}}>
     */
    public static function cases(): array
    {
        $scenarios = [
            'category, guest, two colours and a size' => [
                'page' => 'category', 'store' => 'default', 'group' => 0, 'price' => null,
                'selection' => [M1Catalog::COLOR => ['Red', 'Blue'], M1Catalog::SIZE => ['M']],
            ],
            'category, wholesale group, price range and a colour' => [
                'page' => 'category', 'store' => 'default', 'group' => M1Catalog::WHOLESALE, 'price' => [20.0, 39.99],
                'selection' => [M1Catalog::COLOR => ['Red'], M1Catalog::MATERIAL => ['Cotton', 'Wool']],
            ],
            'category, retailer group, German store view' => [
                'page' => 'category', 'store' => M1Catalog::STORE, 'group' => 3, 'price' => null,
                'selection' => [M1Catalog::COLOR => ['Blue', 'Black'], M1Catalog::MATERIAL => ['Cotton']],
            ],
            'search page, general group, three attributes' => [
                'page' => 'search', 'store' => 'default', 'group' => 1, 'price' => null,
                'selection' => [
                    M1Catalog::COLOR => ['Red', 'Green'],
                    M1Catalog::SIZE => ['L', 'S'],
                    M1Catalog::MATERIAL => ['Wool'],
                ],
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
     * @param string $strategy
     * @param array{page: string, store: string, group: int, price: ?array{float, float},
     *     selection: array<string, string[]>} $scenario
     */
    #[DataProvider('cases')]
    public function testCountsEqualBruteForceAndTheCatalogModel(string $strategy, array $scenario): void
    {
        $om = Bootstrap::getObjectManager();
        $om->get(StoreManagerInterface::class)->setCurrentStore($scenario['store']);
        $om->get(CustomerSession::class)->setCustomerGroupId($scenario['group']);
        $om->get(MutableScopeConfigInterface::class)->setValue(StrategyResolver::XML_PATH_STRATEGY, $strategy);
        $active = $this->ids($scenario['selection']);

        $model = $this->model($scenario);
        self::assertSame(count($this->modelMatches($scenario, $scenario['selection'])), $this->collection($scenario, $active)->getSize());
        foreach (array_keys(M1Catalog::OPTIONS) as $code) {
            $facets = $this->facetCounts($scenario, $active, $code);
            $label = sprintf('%s counts, %s', $code, json_encode($scenario['selection']));
            self::assertSame($this->bruteForce($scenario, $active, $code), $facets, "$label: brute force");
            self::assertSame($model[$code], $facets, "$label: catalog model");
        }
    }

    public function testStrategyChoiceGoesToTheOpenFilterLogInDebugMode(): void
    {
        $om = Bootstrap::getObjectManager();
        $config = $om->get(MutableScopeConfigInterface::class);
        $config->setValue(Config::XML_PATH_DEBUG, '1');
        $config->setValue(StrategyResolver::XML_PATH_STRATEGY, StrategyResolver::AUTO);
        // The handler writes below the Magento root (BP), not the test sandbox.
        $file = BP . '/var/log/openfilter.log';
        $before = is_file($file) ? (int) filesize($file) : 0;

        $code = $om->create(StrategyResolver::class)->resolve()->getCode();
        $config->setValue(Config::XML_PATH_DEBUG, '0');

        clearstatcache();
        self::assertFileExists($file);
        self::assertStringContainsString(
            'facet count strategy: ' . $code,
            (string) file_get_contents($file, false, null, $before)
        );
    }

    /**
     * Counts per attribute from the PHP model: products in scope matching every other active filter.
     *
     * @param array{page: string, store: string, group: int, price: ?array{float, float},
     *     selection: array<string, string[]>} $scenario
     * @return array<string, array<int, int>>
     */
    private function model(array $scenario): array
    {
        $counts = [];
        foreach (array_keys(M1Catalog::OPTIONS) as $code) {
            $others = $scenario['selection'];
            unset($others[$code]);
            $byOption = [];
            foreach ($this->modelMatches($scenario, $others) as $product) {
                foreach ($this->values($product, $code) as $label) {
                    $id = self::$catalog['options'][$code][$label];
                    $byOption[$id] = ($byOption[$id] ?? 0) + 1;
                }
            }
            ksort($byOption);
            $counts[$code] = $byOption;
        }

        return $counts;
    }

    /**
     * @param array{page: string, store: string, group: int, price: ?array{float, float},
     *     selection: array<string, string[]>} $scenario
     * @param array<string, string[]> $selection
     * @return array<string, array{colors: string[], sizes: string[], material: string, price: float,
     *     wholesale: float, category: bool, german: bool}>
     */
    private function modelMatches(array $scenario, array $selection): array
    {
        return array_filter(M1Catalog::products(), function (array $product) use ($scenario, $selection): bool {
            if (($scenario['page'] === 'category' && !$product['category'])
                || ($scenario['store'] === M1Catalog::STORE && !$product['german'])) {
                return false;
            }
            if ($scenario['price'] !== null) {
                $price = $scenario['group'] === M1Catalog::WHOLESALE ? $product['wholesale'] : $product['price'];
                if ($price < $scenario['price'][0] || $price > $scenario['price'][1]) {
                    return false;
                }
            }
            foreach ($selection as $code => $labels) {
                if (array_intersect($this->values($product, $code), $labels) === []) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * @param array{colors: string[], sizes: string[], material: string} $product
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
    private function bruteForce(array $scenario, array $active, string $code): array
    {
        $this->enabled(false);
        $others = $active;
        unset($others[$code]);
        $counts = [];
        foreach (self::$catalog['options'][$code] as $optionId) {
            $size = $this->collection($scenario, $others + [$code => [$optionId]])->getSize();
            if ($size > 0) {
                $counts[$optionId] = $size;
            }
        }
        $this->enabled(true);
        ksort($counts);

        return $counts;
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
        // Core's fulltext collection takes the search criteria builder from ObjectManager::get(): one instance per
        // process whose filters are never cleared, so every collection would inherit the previous one's filters.
        // A storefront request builds one layer collection; tests build many, so they start from a fresh builder.
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
        if ($scenario['price'] !== null) {
            $collection->addFieldToFilter('price', ['from' => $scenario['price'][0], 'to' => $scenario['price'][1]]);
        }
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
     * @param bool $enabled
     */
    private function enabled(bool $enabled): void
    {
        $store = Bootstrap::getObjectManager()->get(StoreManagerInterface::class)->getStore()->getCode();
        Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class)
            ->setValue(Config::XML_PATH_ENABLED, $enabled ? '1' : '0', 'store', $store);
    }
}
