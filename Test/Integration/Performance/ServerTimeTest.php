<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Performance;

use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Test\Integration\Catalog\M1Catalog;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Hard gate on server time (07 §6, D14): the same filtered category page rendered with OpenFilter and with core's
 * layered navigation (OpenFilter switched off, option ids in the URL), seven times each, alternating; the median
 * with OpenFilter may exceed core's median by at most 15 ms. Runs alone (CI job "Performance", locally
 * `--group performance`): timing next to other jobs is noise.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 * @magentoCache openfilter enabled
 * @magentoConfigFixture default/openfilter/search/count_strategy single_query
 */
#[Group('performance')]
class ServerTimeTest extends AbstractController
{
    private const RUNS = 7;

    /** Budget for OpenFilter's added server time in milliseconds (02 · Architecture §10). */
    private const BUDGET_MS = 15.0;

    /** @var array{category: int, store: int, options: array<string, array<string, int>>} */
    private static ?array $catalog = null;

    /** @var array<string, float[]> milliseconds by side */
    private static ?array $times = ['openfilter' => [], 'core' => []];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$catalog = M1Catalog::create();
    }

    public function testWarmUpOpenFilter(): void
    {
        $this->page(true);

        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
    }

    public function testWarmUpCore(): void
    {
        $this->page(false);

        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function runs(): array
    {
        $runs = [];
        for ($i = 1; $i <= self::RUNS; $i++) {
            $runs["openfilter $i"] = [true];
            $runs["core $i"] = [false];
        }

        return $runs;
    }

    /**
     * @dataProvider runs
     */
    #[DataProvider('runs')]
    public function testRun(bool $openFilter): void
    {
        $start = microtime(true);
        $this->page($openFilter);
        self::$times[$openFilter ? 'openfilter' : 'core'][] = (microtime(true) - $start) * 1000;

        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
    }

    public function testMedianAddedServerTimeIsWithinBudget(): void
    {
        if (count(self::$times['openfilter']) < self::RUNS || count(self::$times['core']) < self::RUNS) {
            self::markTestSkipped('Needs the runs of this class (run the whole class).');
        }
        $openFilter = $this->median(self::$times['openfilter']);
        $core = $this->median(self::$times['core']);
        fwrite(STDERR, sprintf(
            "\n[server time] median of %d: OpenFilter %.1f ms, core %.1f ms, added %.1f ms (budget %.0f ms)\n",
            self::RUNS,
            $openFilter,
            $core,
            $openFilter - $core,
            self::BUDGET_MS
        ));

        self::assertLessThanOrEqual(self::BUDGET_MS, $openFilter - $core, 'server time added by OpenFilter');
    }

    /**
     * @param float[] $values
     */
    private function median(array $values): float
    {
        sort($values);

        return $values[intdiv(count($values), 2)];
    }

    /**
     * Category page with three active values (two filters) and a price range.
     */
    private function page(bool $openFilter): void
    {
        if ($openFilter) {
            $query = [M1Catalog::COLOR => 'red', M1Catalog::SIZE => 'm', 'price' => '10-40'];
        } else {
            Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class)
                ->setValue(Config::XML_PATH_ENABLED, '0', 'store', 'default');
            $options = self::$catalog['options'];
            $query = [
                M1Catalog::COLOR => (string) $options[M1Catalog::COLOR]['Red'],
                M1Catalog::SIZE => (string) $options[M1Catalog::SIZE]['M'],
                'price' => '10-40',
            ];
        }
        $this->getRequest()->setMethod('GET')->setQueryValue($query);
        $this->dispatch('catalog/category/view/id/' . self::$catalog['category']);
    }
}
