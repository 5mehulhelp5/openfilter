<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Admin;

use Iranimij\OpenFilter\Model\Doctor\Check;
use Iranimij\OpenFilter\Model\Doctor\FilterDiagnosis;
use Iranimij\OpenFilter\Test\Integration\Catalog\M1Catalog;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * "Why is this filter not showing?" against the real search engine and the M1 catalog.
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 * @magentoCache openfilter enabled
 */
class FilterDiagnosisTest extends TestCase
{
    private const SHOW_OUT_OF_STOCK = 'cataloginventory/options/show_out_of_stock';
    private const PLAIN = 'of_m2_why_plain';

    /** @var array{category: int, store: int, options: array<string, array<string, int>>} */
    private static array $catalog;

    public static function setUpBeforeClass(): void
    {
        self::$catalog = M1Catalog::create();
        AdminFixture::clean();
    }

    protected function tearDown(): void
    {
        AdminFixture::deleteAttribute(self::PLAIN);
        $category = $this->categories()->get(self::$catalog['category'], 0);
        if (!$category->getIsAnchor()) {
            $category->setIsAnchor(true)->setStoreId(0);
            $this->categories()->save($category);
        }
    }

    public function testAFilterWithProductsInTheCategoryPassesEveryCheck(): void
    {
        $checks = $this->diagnose(M1Catalog::COLOR, self::$catalog['category']);

        self::assertSame([], $this->failing($checks));
        $codes = array_map(static fn (Check $c): string => $c->code, $checks);
        self::assertContains('index_field', $codes, 'mapping read from the real engine');
        self::assertContains('products', $codes);
        self::assertContains('single_option', $codes);
        $products = array_values(array_filter($checks, static fn (Check $c): bool => $c->code === 'products'))[0];
        self::assertStringStartsWith('28 ', $products->detail, '24 simple + 4 configurable products in the category');
    }

    public function testAnAttributeThatIsNoFilterIsNamed(): void
    {
        AdminFixture::selectAttribute(self::PLAIN, ['A', 'B'], ['is_filterable' => 0, 'is_filterable_in_search' => 0]);

        self::assertSame(['filter'], $this->failing($this->diagnose(self::PLAIN)));
    }

    public function testANewAttributeWithoutProductValuesIsFoundOutOnTheCategory(): void
    {
        AdminFixture::selectAttribute(self::PLAIN, ['A', 'B']);

        $failing = $this->failing($this->diagnose(self::PLAIN, self::$catalog['category']));

        self::assertContains('products', $failing);
    }

    public function testTheStockFilterNeedsOutOfStockProductsShown(): void
    {
        $config = Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class);
        $config->setValue(self::SHOW_OUT_OF_STOCK, '0');
        $config->setValue(self::SHOW_OUT_OF_STOCK, '0', 'store', 'default');

        self::assertContains('out_of_stock_shown', $this->failing($this->diagnose('stock')));
    }

    public function testANonAnchorCategoryIsNamed(): void
    {
        $category = $this->categories()->get(self::$catalog['category'], 0);
        $category->setIsAnchor(false)->setStoreId(0);
        $this->categories()->save($category);

        self::assertContains('category_anchor', $this->failing($this->diagnose(M1Catalog::COLOR, self::$catalog['category'])));
    }

    /**
     * @return Check[]
     */
    private function diagnose(string $key, ?int $categoryId = null): array
    {
        AdminFixture::clean();
        $store = (int) Bootstrap::getObjectManager()->get(StoreManagerInterface::class)->getStore('default')->getId();

        return Bootstrap::getObjectManager()->create(FilterDiagnosis::class)->diagnose($key, $store, $categoryId);
    }

    /**
     * @param Check[] $checks
     * @return string[]
     */
    private function failing(array $checks): array
    {
        return array_values(array_map(
            static fn (Check $c): string => $c->code,
            array_filter($checks, static fn (Check $c): bool => !$c->ok)
        ));
    }

    private function categories(): CategoryRepositoryInterface
    {
        return Bootstrap::getObjectManager()->get(CategoryRepositoryInterface::class);
    }
}
