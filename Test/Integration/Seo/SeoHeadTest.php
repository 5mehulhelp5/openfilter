<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Seo;

use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Seo\CategoryUrl;
use Iranimij\OpenFilter\Model\Url\UrlCodec;
use Iranimij\OpenFilter\Test\Integration\Catalog\M1Catalog;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;

/**
 * Robots and canonical tags of filtered pages and the max active values guard, through the front controller.
 * One dispatch per test (core keeps layout state per process).
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class SeoHeadTest extends AbstractController
{
    /** @var array{category: int, store: int, options: array<string, array<string, int>>} */
    private static ?array $catalog = null;

    private ?bool $colorWasIndexable = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$catalog = M1Catalog::create();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->repository()->getByCode(M1Catalog::COLOR);
        $this->colorWasIndexable = $config->isSeoIndexable();
        $this->repository()->save($config->setSeoIndexable(true));
        $this->freshConfig();
    }

    protected function tearDown(): void
    {
        $config = $this->repository()->getByCode(M1Catalog::COLOR);
        $this->repository()->save($config->setSeoIndexable($this->colorWasIndexable));
        parent::tearDown();
    }

    public function testOneValueOfAnIndexableFilterKeepsTheStoreRobotsAndIsItsOwnCanonical(): void
    {
        $this->open([M1Catalog::COLOR => 'red']);

        self::assertSame('INDEX,FOLLOW', $this->robots());
        self::assertSame([$this->categoryUrl() . '?' . M1Catalog::COLOR . '=red'], $this->canonicals());
    }

    public function testCanonicalKeepsThePageAndDropsSortingAndLimit(): void
    {
        $this->open([M1Catalog::COLOR => 'red', 'product_list_order' => 'name', 'product_list_limit' => '24', 'p' => '2']);

        self::assertSame([$this->categoryUrl() . '?' . M1Catalog::COLOR . '=red&p=2'], $this->canonicals());
    }

    public function testTwoValuesAreNotIndexableAndPointToTheCategory(): void
    {
        $this->open([M1Catalog::COLOR => 'red,blue']);

        self::assertSame('NOINDEX,FOLLOW', $this->robots());
        self::assertSame([$this->categoryUrl()], $this->canonicals());
    }

    public function testOneValueOfAFilterNotMarkedIndexableIsNotIndexable(): void
    {
        $this->open([M1Catalog::SIZE => 'm']);

        self::assertSame('NOINDEX,FOLLOW', $this->robots());
        self::assertSame([$this->categoryUrl()], $this->canonicals());
    }

    public function testCoreCategoryFilterCountsAsAValue(): void
    {
        $this->open([M1Catalog::COLOR => 'red', 'cat' => (string) self::$catalog['category']]);

        self::assertSame('NOINDEX,FOLLOW', $this->robots());
    }

    /**
     * @magentoConfigFixture current_store catalog/seo/category_canonical_tag 1
     */
    public function testCoreCanonicalIsReplacedNotDuplicated(): void
    {
        $this->open([M1Catalog::COLOR => 'red,blue']);

        self::assertSame([$this->categoryUrl()], $this->canonicals());
    }

    /**
     * @magentoConfigFixture current_store catalog/seo/category_canonical_tag 0
     */
    public function testFilteredPagesGetACanonicalEvenWhenCoreCanonicalIsOff(): void
    {
        $this->open([M1Catalog::COLOR => 'red,blue']);

        self::assertSame([$this->categoryUrl()], $this->canonicals());
    }

    /**
     * @magentoConfigFixture current_store catalog/seo/category_canonical_tag 0
     */
    public function testPagesWithoutFiltersKeepTheStoreTags(): void
    {
        $this->open([]);

        self::assertSame('INDEX,FOLLOW', $this->robots());
        self::assertSame([], $this->canonicals());
    }

    /**
     * @magentoCache all enabled
     */
    public function testANewCategoryUrlKeyReachesTheCachedCanonical(): void
    {
        $om = Bootstrap::getObjectManager();
        $om->get(CategoryUrl::class)->get($this->category(null), 1); // cached under the old URL
        $repository = $om->get(CategoryRepositoryInterface::class);
        $category = $this->category();
        $old = (string) $category->getUrlKey();
        $category->setStoreId(0)->setUrlKey('of-m1-renamed')->setData('save_rewrites_history', false);
        $repository->save($category);
        try {
            $this->open([M1Catalog::COLOR => 'red,blue']);
            self::assertStringEndsWith('of-m1-renamed.html', $this->canonicals()[0] ?? '');
        } finally {
            $category = $this->category();
            $category->setStoreId(0)->setUrlKey($old)->setData('save_rewrites_history', false);
            $repository->save($category);
        }
    }

    public function testGermanUrlNamesInTheCanonical(): void
    {
        Bootstrap::getObjectManager()->get(StoreManagerInterface::class)->setCurrentStore(M1Catalog::STORE);
        $this->open([M1Catalog::COLOR => 'rot']);

        self::assertSame('INDEX,FOLLOW', $this->robots());
        self::assertStringEndsWith('?' . M1Catalog::COLOR . '=rot', $this->canonicals()[0] ?? '');
    }

    /**
     * @magentoConfigFixture current_store openfilter/seo/max_active_values 5
     */
    public function testTooManyValuesRedirectTemporarilyToTheFirstFive(): void
    {
        $this->getRequest()->setMethod('GET')->setQueryValue([
            M1Catalog::SIZE => 'l,m,s',
            M1Catalog::COLOR => 'black,green,blue,red',
            'p' => '4',
        ]);
        $this->dispatch('catalog/category/view/id/' . self::$catalog['category']);

        self::assertSame(302, $this->getResponse()->getHttpResponseCode());
        self::assertSame('noindex', $this->getResponse()->getHeader('X-Robots-Tag')->getFieldValue());
        self::assertStringEndsWith(
            '?' . M1Catalog::COLOR . '=red,blue,green,black&' . M1Catalog::SIZE . '=s',
            (string) $this->getResponse()->getHeader('Location')->getFieldValue()
        );
    }

    /**
     * @magentoConfigFixture current_store openfilter/seo/max_active_values 0
     */
    public function testGuardSwitchedOff(): void
    {
        $this->open([M1Catalog::COLOR => 'red,blue,green,black', M1Catalog::SIZE => 's,m,l']);

        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
        self::assertSame('NOINDEX,FOLLOW', $this->robots());
    }

    public function testFilteredSearchPagesAreNotIndexed(): void
    {
        $this->getRequest()->setMethod('GET')->setQueryValue(['q' => M1Catalog::TOKEN, M1Catalog::COLOR => 'red']);
        $this->dispatch('catalogsearch/result/index');

        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
        self::assertSame('NOINDEX,FOLLOW', $this->robots());
    }

    /**
     * @param array<string, string> $query
     */
    private function open(array $query): void
    {
        $this->getRequest()->setMethod('GET')->setQueryValue($query);
        $this->dispatch('catalog/category/view/id/' . self::$catalog['category']);
        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
    }

    private function robots(): string
    {
        preg_match('#<meta name="robots" content="([^"]*)"#', (string) $this->getResponse()->getBody(), $match);

        return $match[1] ?? '';
    }

    /**
     * @return string[]
     */
    private function canonicals(): array
    {
        preg_match_all('#<link\s+rel="canonical"\s+href="([^"]*)"#', (string) $this->getResponse()->getBody(), $matches);

        return array_map('html_entity_decode', $matches[1]);
    }

    private function categoryUrl(): string
    {
        return (string) $this->category(null)->getUrl();
    }

    /**
     * @param int|null $storeId 0 to save default values, null for the current store view
     */
    private function category(?int $storeId = 0): Category
    {
        /** @var Category $category */
        $category = Bootstrap::getObjectManager()->create(CategoryRepositoryInterface::class)
            ->get(self::$catalog['category'], $storeId);

        return $category;
    }

    private function repository(): FilterConfigRepositoryInterface
    {
        return Bootstrap::getObjectManager()->get(FilterConfigRepositoryInterface::class);
    }

    private function freshConfig(): void
    {
        $om = Bootstrap::getObjectManager();
        $om->removeSharedInstance(FilterConfigProvider::class);
        $om->get(UrlCodec::class)->reset();
    }
}
