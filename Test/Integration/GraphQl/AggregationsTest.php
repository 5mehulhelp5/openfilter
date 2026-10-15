<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\GraphQl;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Search\StrategyResolver;
use Iranimij\OpenFilter\Model\Url\UrlCodec;
use Iranimij\OpenFilter\Test\Integration\Catalog\M1Catalog;
use Iranimij\OpenFilter\Test\Integration\Search\EngineRequests;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\Request\Http;
use Magento\GraphQl\Controller\GraphQl;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * M5 S8 `products { openfilter_aggregations }`: counts from the same search request, each filter without its own
 * picks (attributes set to "all" with them), range lists, slider bounds, URL names per store view, "Where it shows";
 * checked against the catalog's PHP model for both strategies. A query without the field keeps Magento's counts.
 * One GraphQL dispatch per test.
 *
 * @magentoAppArea graphql
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 * @magentoCache openfilter enabled
 */
class AggregationsTest extends TestCase
{
    /** Also Magento's aggregations: a process keeps the schema of its first query, and the baseline query is second. */
    private const FIELDS = 'total_count aggregations { attribute_code } openfilter_aggregations { code filter_key label
        url_param display_mode logic range { min max from to } options { value label url_value count selected from to } }';

    /** @var array{category: int, store: int, options: array<string, array<string, int>>}|null */
    private static ?array $catalog = null;

    public static function setUpBeforeClass(): void
    {
        self::$catalog = M1Catalog::create();
    }

    protected function tearDown(): void
    {
        $this->configure(M1Catalog::COLOR, static fn (Filter $f) => $f->setLogic(Filter::LOGIC_OR)
            ->setCategoryMode(Filter::CATEGORIES_ALL)->setCategoryIds([]));
        $this->configure(Filter::BUILTIN_PRICE, static fn (Filter $f) => $f->setDisplayMode(Filter::DISPLAY_SLIDER)->setRanges(null));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function strategies(): array
    {
        return ['single query' => [StrategyResolver::SINGLE_QUERY], 'per attribute' => [StrategyResolver::PER_ATTRIBUTE]];
    }

    /**
     * @dataProvider strategies
     */
    #[DataProvider('strategies')]
    public function testEachFilterIsCountedWithoutItsOwnPicks(string $strategy): void
    {
        Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class)->setValue(StrategyResolver::XML_PATH_STRATEGY, $strategy);
        $filter = [
            M1Catalog::COLOR => ['in' => [$this->option(M1Catalog::COLOR, 'Red'), $this->option(M1Catalog::COLOR, 'Blue')]],
            M1Catalog::SIZE => ['eq' => $this->option(M1Catalog::SIZE, 'S')],
        ];
        $products = null;
        $requests = EngineRequests::during(function () use (&$products, $filter): void {
            $products = $this->products($filter);
        });
        $magento = $this->magentosRequests($filter);

        self::assertSame(
            $strategy === StrategyResolver::SINGLE_QUERY ? 0 : 2,
            $requests - $magento,
            'no request over Magento\'s own aggregations, or one per other active filter'
        );
        self::assertSame(count($this->matching(['Red', 'Blue'], ['S'])), $products['total_count']);
        $colour = $this->aggregation($products, M1Catalog::COLOR);
        self::assertSame($this->counts('colors', $this->matching([], ['S'])), $this->optionCounts($colour), 'colours under the size only');
        self::assertSame(['Blue', 'Red'], $this->selected($colour));
        self::assertSame('red', $this->urlValue($colour, 'Red'));
        self::assertSame($this->counts('sizes', $this->matching(['Red', 'Blue'], [])), $this->optionCounts($this->aggregation($products, M1Catalog::SIZE)));
    }

    public function testAllOfCountsWithTheFiltersOwnPicks(): void
    {
        $this->configure(M1Catalog::COLOR, static fn (Filter $f) => $f->setMultiselect(true)->setLogic(Filter::LOGIC_AND));

        $products = $this->products([M1Catalog::COLOR => ['in' => [$this->option(M1Catalog::COLOR, 'Red')]]]);

        $colour = $this->aggregation($products, M1Catalog::COLOR);
        self::assertSame('and', $colour['logic']);
        self::assertSame($this->counts('colors', $this->matching(['Red'], [])), $this->optionCounts($colour));
    }

    public function testPriceRangesAreCountedInTheSameRequest(): void
    {
        $this->configure(Filter::BUILTIN_PRICE, static fn (Filter $f) => $f->setDisplayMode(Filter::DISPLAY_RANGES)->setRanges('10-30,30-50,50-'));
        $filter = [
            'price' => ['from' => '10', 'to' => '30'],
            M1Catalog::COLOR => ['eq' => $this->option(M1Catalog::COLOR, 'Red')],
        ];
        $products = null;
        $requests = EngineRequests::during(function () use (&$products, $filter): void {
            $products = $this->products($filter);
        });
        $magento = $this->magentosRequests($filter);

        self::assertSame(0, $requests - $magento, 'the ranges are counted in the same request');
        $price = $this->aggregation($products, 'price');
        $expected = [];
        foreach (['10-30' => [10, 30], '30-50' => [30, 50], '50-' => [50, null]] as $range => [$from, $to]) {
            $count = count(array_filter(
                $this->matching(['Red'], []),
                static fn (array $p): bool => $p['price'] >= $from && ($to === null || $p['price'] < $to)
            ));
            if ($count > 0) {
                $expected[$range] = $count;
            }
        }
        self::assertSame($expected, array_column($price['options'], 'count', 'value'));
        self::assertSame(['10-30'], array_column(array_filter($price['options'], static fn ($o) => $o['selected']), 'value'));
        self::assertEquals(10.0, $price['options'][0]['from']);
        $inRange = array_filter($this->matching(['Red'], []), static fn (array $p): bool => $p['price'] >= 10 && $p['price'] < 30);
        self::assertSame(count($inRange), $products['total_count'], '[10, 30): 30 belongs to the next range');
    }

    public function testTheSliderHasTheBoundsOfTheProductsFoundWithoutItsOwnPick(): void
    {
        $products = $this->products([
            'price' => ['from' => '20', 'to' => '30'],
            M1Catalog::COLOR => ['eq' => $this->option(M1Catalog::COLOR, 'Green')],
        ]);

        $prices = array_column($this->matching(['Green'], []), 'price');
        // JSON writes 14.0 as 14.
        self::assertEquals(
            ['min' => min($prices), 'max' => max($prices), 'from' => 20.0, 'to' => 30.0],
            $this->aggregation($products, 'price')['range']
        );
    }

    public function testGermanUrlNamesAndLabels(): void
    {
        $products = $this->products([M1Catalog::COLOR => ['eq' => $this->option(M1Catalog::COLOR, 'Red')]], M1Catalog::STORE);

        $colour = $this->aggregation($products, M1Catalog::COLOR);
        self::assertSame('rot', $this->urlValue($colour, 'Rot'));
        self::assertSame(['Rot'], $this->selected($colour));
    }

    public function testOnlyWhereTheFilterShows(): void
    {
        $outside = (int) Bootstrap::getObjectManager()->get(\Magento\Catalog\Model\ResourceModel\Category\CollectionFactory::class)
            ->create()->addAttributeToFilter('url_key', 'of-m1-outside')->getFirstItem()->getId();
        $this->configure(M1Catalog::COLOR, static fn (Filter $f) => $f->setCategoryMode(Filter::CATEGORIES_ONLY)->setCategoryIds([$outside]));

        $products = $this->products([]);

        self::assertNull($this->aggregation($products, M1Catalog::COLOR, false));
        self::assertNotNull($this->aggregation($products, M1Catalog::SIZE, false));
    }

    public function testAQueryWithoutTheFieldKeepsMagentosCounts(): void
    {
        $response = $this->graphQl(
            'query($filter: ProductAttributeFilterInput) { products(filter: $filter, pageSize: 100) {
                aggregations { attribute_code options { label count } } } }',
            ['filter' => [
                'category_id' => ['eq' => (string) self::$catalog['category']],
                M1Catalog::COLOR => ['eq' => $this->option(M1Catalog::COLOR, 'Red')],
            ]],
            'default'
        );

        $colour = array_values(array_filter(
            $response['data']['products']['aggregations'],
            static fn (array $a): bool => $a['attribute_code'] === M1Catalog::COLOR
        ))[0];
        self::assertSame(
            $this->counts('colors', $this->matching(['Red'], [])),
            $this->sorted(array_column($colour['options'], 'count', 'label')),
            'Magento counts the colours of the red products'
        );
    }

    /**
     * @param array<string, mixed> $filter besides the category
     * @param string $store store view code
     * @return array<string, mixed> the products field
     */
    private function products(array $filter, string $store = 'default'): array
    {
        $response = $this->graphQl(
            'query($filter: ProductAttributeFilterInput) { products(filter: $filter, pageSize: 100) { ' . self::FIELDS . ' } }',
            ['filter' => ['category_id' => ['eq' => (string) self::$catalog['category']]] + $filter],
            $store
        );

        return $response['data']['products'];
    }

    /**
     * Engine requests of the same search asking only for Magento's aggregations (its automatic price intervals run
     * requests of their own).
     *
     * @param array<string, mixed> $filter besides the category
     * @return int
     */
    private function magentosRequests(array $filter): int
    {
        return EngineRequests::during(function () use ($filter): void {
            $this->graphQl(
                'query($filter: ProductAttributeFilterInput) { products(filter: $filter, pageSize: 100) {
                    total_count aggregations { attribute_code } } }',
                ['filter' => ['category_id' => ['eq' => (string) self::$catalog['category']]] + $filter],
                'default'
            );
        });
    }

    /**
     * @param string $query
     * @param array<string, mixed> $variables
     * @param string $store
     * @return array<string, mixed>
     */
    private function graphQl(string $query, array $variables, string $store): array
    {
        $om = Bootstrap::getObjectManager();
        /** @var Http $request */
        $request = $om->get(Http::class);
        $request->setPathInfo('/graphql')->setMethod('POST');
        $request->setContent((string) json_encode(['query' => $query, 'variables' => $variables]));
        $request->setHeaders($om->create(\Laminas\Http\Headers::class)->addHeaders(['Content-Type' => 'application/json', 'Store' => $store]));
        $response = json_decode((string) $om->get(GraphQl::class)->dispatch($request)->getContent(), true);
        self::assertIsArray($response);
        self::assertArrayNotHasKey('errors', $response, (string) json_encode($response['errors'] ?? null));

        return $response;
    }

    /**
     * @param array<string, mixed> $products
     * @param string $code
     * @param bool $required
     * @return array<string, mixed>|null
     */
    private function aggregation(array $products, string $code, bool $required = true): ?array
    {
        foreach ($products['openfilter_aggregations'] as $aggregation) {
            if ($aggregation['code'] === $code) {
                return $aggregation;
            }
        }
        if ($required) {
            self::fail("no aggregation $code");
        }

        return null;
    }

    /**
     * @param array<string, mixed> $aggregation
     * @return array<string, int> label => count
     */
    private function optionCounts(array $aggregation): array
    {
        return $this->sorted(array_column($aggregation['options'], 'count', 'label'));
    }

    /**
     * @param array<string, mixed> $aggregation
     * @return string[]
     */
    private function selected(array $aggregation): array
    {
        $labels = array_column(array_filter($aggregation['options'], static fn (array $o): bool => $o['selected']), 'label');
        sort($labels);

        return $labels;
    }

    /**
     * @param array<string, mixed> $aggregation
     */
    private function urlValue(array $aggregation, string $label): ?string
    {
        return array_column($aggregation['options'], 'url_value', 'label')[$label] ?? null;
    }

    /**
     * Products of the category with one of the colours and one of the sizes ([] = any), by SKU.
     *
     * @param string[] $colours
     * @param string[] $sizes
     * @return array<string, array<string, mixed>>
     */
    private function matching(array $colours, array $sizes): array
    {
        return array_filter(M1Catalog::products(), static fn (array $p): bool => $p['category']
            && ($colours === [] || array_intersect($p['colors'], $colours) !== [])
            && ($sizes === [] || array_intersect($p['sizes'], $sizes) !== []));
    }

    /**
     * @param string $key colors or sizes
     * @param array<string, array<string, mixed>> $products
     * @return array<string, int>
     */
    private function counts(string $key, array $products): array
    {
        $counts = [];
        foreach ($products as $product) {
            foreach ($product[$key] as $value) {
                $counts[$value] = ($counts[$value] ?? 0) + 1;
            }
        }

        return $this->sorted($counts);
    }

    /**
     * @param array<string, int> $counts
     * @return array<string, int>
     */
    private function sorted(array $counts): array
    {
        ksort($counts);

        return $counts;
    }

    private function option(string $code, string $label): string
    {
        return (string) self::$catalog['options'][$code][$label];
    }

    private function configure(string $code, callable $change): void
    {
        $om = Bootstrap::getObjectManager();
        $repository = $om->get(FilterConfigRepositoryInterface::class);
        $repository->save($change($repository->getByCode($code)));
        $om->get(CacheType::class)->clean();
        $om->get(FilterConfigProvider::class)->reset();
        $om->get(UrlCodec::class)->reset();
    }
}
