<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Performance;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface;
use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Test\Integration\Catalog\M1Catalog;
use Iranimij\OpenFilter\Test\Integration\Search\EngineRequests;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * M1 "done when" budgets on full category page renders (02 · Architecture §10): three active filters cost one
 * search request with SingleQuery, and OpenFilter adds no MySQL queries (settings and URL names come from the
 * cache). One dispatch per test (fresh application each), caches warmed by the first test. Server time is
 * recorded here; the hard time gate is ServerTimeTest (group "performance", its own CI job).
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 * @magentoCache openfilter enabled
 * @magentoConfigFixture default/openfilter/search/count_strategy single_query
 */
class BudgetTest extends AbstractController
{
    /** @var array{category: int, store: int, options: array<string, array<string, int>>} */
    private static ?array $catalog = null;

    /** @var array<string, array{int, float}> SQL queries and milliseconds by run */
    private static ?array $runs = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$catalog = M1Catalog::create();
    }

    public function testWarmUp(): void
    {
        $this->page([M1Catalog::COLOR => 'red', 'price' => '10-40']);

        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
    }

    public function testThreeActiveFiltersCostOneSearchRequest(): void
    {
        $query = [M1Catalog::COLOR => 'red,blue', M1Catalog::MATERIAL => 'cotton', M1Catalog::SIZE => 'm'];
        $requests = EngineRequests::during(fn () => $this->page($query));

        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
        self::assertSame(1, $requests, 'products, disjunctive counts, price bounds: one request');
    }

    public function testOpenFilterPage(): void
    {
        self::$runs['openfilter'] = $this->measure([M1Catalog::COLOR => 'red', 'price' => '10-40']);

        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
    }

    public function testCorePage(): void
    {
        Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class)
            ->setValue(Config::XML_PATH_ENABLED, '0', 'store', 'default');
        $red = (string) self::$catalog['options'][M1Catalog::COLOR]['Red'];
        self::$runs['core'] = $this->measure([M1Catalog::COLOR => $red, 'price' => '10-40']);

        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
    }

    /**
     * @depends testOpenFilterPage
     * @depends testCorePage
     */
    public function testNoExtraSqlQueriesAgainstCore(): void
    {
        [$ofQueries, $ofTime] = self::$runs['openfilter'];
        [$coreQueries, $coreTime] = self::$runs['core'];
        fwrite(STDERR, sprintf(
            "\n[budget] SQL queries per page: OpenFilter %d, core %d; server time: OpenFilter %.1f ms, core %.1f ms\n",
            $ofQueries,
            $coreQueries,
            $ofTime,
            $coreTime
        ));

        self::assertLessThanOrEqual($coreQueries, $ofQueries, 'OpenFilter adds no SQL queries');
    }

    /**
     * Last: saving a filter cleans OpenFilter's cache, which the SQL comparison above needs warm.
     */
    public function testAllOfFiltersStillCostOneSearchRequest(): void
    {
        $repository = Bootstrap::getObjectManager()->get(FilterConfigRepositoryInterface::class);
        $repository->save($repository->getByCode(M1Catalog::COLOR)->setLogic(FilterConfigInterface::LOGIC_AND));
        try {
            $query = [M1Catalog::COLOR => 'red,blue', M1Catalog::MATERIAL => 'cotton', M1Catalog::SIZE => 'm'];
            $requests = EngineRequests::during(fn () => $this->page($query));
        } finally {
            $repository->save($repository->getByCode(M1Catalog::COLOR)->setLogic(FilterConfigInterface::LOGIC_OR));
        }

        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
        self::assertSame(1, $requests, '"all of" adds clauses, not requests');
    }

    /**
     * @param array<string, string> $query
     * @return array{int, float} SQL queries and milliseconds of one page dispatch
     */
    private function measure(array $query): array
    {
        $profiler = Bootstrap::getObjectManager()->get(ResourceConnection::class)->getConnection()->getProfiler();
        $profiler->clear();
        $profiler->setEnabled(true);
        $start = microtime(true);
        $this->page($query);
        $time = (microtime(true) - $start) * 1000;
        $queries = (int) $profiler->getTotalNumQueries();
        $profiler->setEnabled(false);

        return [$queries, $time];
    }

    /**
     * @param array<string, string> $query
     */
    private function page(array $query): void
    {
        $this->getRequest()->setMethod('GET')->setQueryValue($query);
        $this->dispatch('catalog/category/view/id/' . self::$catalog['category']);
    }
}
