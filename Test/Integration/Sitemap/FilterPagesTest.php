<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Sitemap;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\Config;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Layer\CategoryTree;
use Iranimij\OpenFilter\Model\Sitemap\FilterPages;
use Iranimij\OpenFilter\Test\Integration\Catalog\M1Catalog;
use Iranimij\OpenFilter\Test\Integration\Catalog\M5Categories;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\MutableScopeConfigInterface;
use Magento\Sitemap\Model\ItemProvider\ItemProviderInterface;
use Magento\Sitemap\Model\ResourceModel\Catalog\CategoryFactory as SitemapCategoryFactory;
use Magento\Sitemap\Model\SitemapItemInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\ScopeInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * M5 S7 filter pages in Magento's XML sitemap: one value of an indexable filter per category page, counted as
 * shoppers see them (checked against the catalog's PHP model), with the minimum, the cap and "Where it shows";
 * registered in Magento's item provider list.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class FilterPagesTest extends TestCase
{
    /** @var array{category: int, store: int, options: array<string, array<string, int>>}|null */
    private static ?array $catalog = null;

    /** @var array<string, int>|null category name => id */
    private static ?array $tree = null;

    private ?bool $colourWasIndexable = false;

    public static function setUpBeforeClass(): void
    {
        self::$catalog = M1Catalog::create();
        self::$tree = M5Categories::create(self::$catalog['category']);
    }

    protected function setUp(): void
    {
        $colour = $this->repository()->getByCode(M1Catalog::COLOR);
        $this->colourWasIndexable = $colour->isSeoIndexable();
        $this->repository()->save($colour->setSeoIndexable(true));
        $this->settings(true, 2, 0);
        $this->fresh();
    }

    protected function tearDown(): void
    {
        $colour = $this->repository()->getByCode(M1Catalog::COLOR);
        $this->repository()->save($colour->setSeoIndexable($this->colourWasIndexable)
            ->setCategoryMode(Filter::CATEGORIES_ALL)->setCategoryIds([]));
        $this->fresh();
    }

    public function testOneValuePerCategoryPageWithEnoughProductsMostProductsFirst(): void
    {
        $expected = $this->expected(2, null);

        self::assertCount(14, $expected, 'four colours on M1, Tops and Bottoms, two on Tees; the outside category has one of each');
        self::assertSame($expected, $this->urls(Bootstrap::getObjectManager()->get(FilterPages::class)));
    }

    public function testTheCapKeepsThePagesWithTheMostProducts(): void
    {
        $this->settings(true, 2, 3);

        self::assertSame(array_slice($this->expected(2, null), 0, 3), $this->urls(Bootstrap::getObjectManager()->get(FilterPages::class)));
    }

    public function testOnlyWhereTheFilterShows(): void
    {
        $this->repository()->save($this->repository()->getByCode(M1Catalog::COLOR)
            ->setCategoryMode(Filter::CATEGORIES_ONLY)->setCategoryIds([self::$tree[M5Categories::TOPS]]));
        $this->fresh();

        $urls = $this->urls(Bootstrap::getObjectManager()->get(FilterPages::class));

        self::assertNotSame([], $urls);
        self::assertSame($this->expected(2, [M5Categories::TOPS, M5Categories::TEES]), $urls, 'Tops and Tees below it');
    }

    public function testOffMeansNoPages(): void
    {
        $this->settings(false, 1, 0);

        self::assertSame([], $this->urls(Bootstrap::getObjectManager()->get(FilterPages::class)));
    }

    public function testMagentosSitemapListsThem(): void
    {
        $provider = Bootstrap::getObjectManager()->get(ItemProviderInterface::class);
        $categoryPriority = Bootstrap::getObjectManager()->get(\Magento\Sitemap\Helper\Data::class)->getCategoryPriority(1);

        $items = $this->items($provider);
        $ours = array_values(array_filter($items, static fn (SitemapItemInterface $item): bool => str_contains((string) $item->getUrl(), '?')));

        self::assertSame($this->expected(2, null), array_map(static fn ($item): string => (string) $item->getUrl(), $ours));
        self::assertSame((string) $categoryPriority, (string) $ours[0]->getPriority());
    }

    /**
     * The filter pages the catalog model expects: colour values with at least $min products per category page.
     *
     * @param int $min
     * @param string[]|null $only names of M5 categories, null = every category
     * @return string[]
     */
    private function expected(int $min, ?array $only): array
    {
        $urls = $this->categoryUrls();
        $outside = (int) Bootstrap::getObjectManager()->get(\Magento\Catalog\Model\ResourceModel\Category\CollectionFactory::class)
            ->create()->addAttributeToFilter('url_key', 'of-m1-outside')->getFirstItem()->getId();
        $pages = [
            self::$catalog['category'] => static fn (string $sku, array $product): bool => $product['category'],
            $outside => static fn (string $sku, array $product): bool => !$product['category'],
        ];
        foreach ([M5Categories::TOPS, M5Categories::TEES, M5Categories::BOTTOMS] as $name) {
            $pages[self::$tree[$name]] = static fn (string $sku): bool => M5Categories::inTree($sku, $name);
        }
        if ($only !== null) {
            $pages = array_intersect_key($pages, array_flip(array_map(static fn (string $name): int => self::$tree[$name], $only)));
        }
        $slugs = ['Red' => 'red', 'Blue' => 'blue', 'Green' => 'green', 'Black' => 'black'];
        $expected = [];
        foreach ($pages as $categoryId => $inPage) {
            $counts = [];
            foreach (M1Catalog::products() as $sku => $product) {
                if ($inPage($sku, $product)) {
                    foreach ($product['colors'] as $colour) {
                        $counts[$colour] = ($counts[$colour] ?? 0) + 1;
                    }
                }
            }
            foreach ($counts as $colour => $count) {
                if ($count >= $min) {
                    $expected[] = [$count, $urls[$categoryId] . '?' . M1Catalog::COLOR . '=' . $slugs[$colour]];
                }
            }
        }
        usort($expected, static fn (array $a, array $b): int => [$b[0], $a[1]] <=> [$a[0], $b[1]]);

        return array_column($expected, 1);
    }

    /**
     * @return array<int, string> category id => URL path of the sitemap
     */
    private function categoryUrls(): array
    {
        $urls = [];
        foreach ((array) Bootstrap::getObjectManager()->get(SitemapCategoryFactory::class)->create()->getCollection(1) as $category) {
            $urls[(int) $category->getId()] = (string) $category->getUrl();
        }

        return $urls;
    }

    /**
     * @return string[]
     */
    private function urls(ItemProviderInterface $provider): array
    {
        return array_map(static fn (SitemapItemInterface $item): string => (string) $item->getUrl(), $this->items($provider));
    }

    /**
     * Items as Magento's sitemap cron builds them: in the store's frontend environment.
     *
     * @return SitemapItemInterface[]
     */
    private function items(ItemProviderInterface $provider): array
    {
        $emulation = Bootstrap::getObjectManager()->get(Emulation::class);
        $emulation->startEnvironmentEmulation(1, Area::AREA_FRONTEND, true);
        try {
            return array_values($provider->getItems(1));
        } finally {
            $emulation->stopEnvironmentEmulation();
        }
    }

    private function settings(bool $enabled, int $min, int $max): void
    {
        $config = Bootstrap::getObjectManager()->get(MutableScopeConfigInterface::class);
        $config->setValue(Config::XML_PATH_SITEMAP_ENABLED, $enabled ? '1' : '0', ScopeInterface::SCOPE_STORE, 'default');
        $config->setValue(Config::XML_PATH_SITEMAP_MIN_PRODUCTS, (string) $min, ScopeInterface::SCOPE_STORE, 'default');
        $config->setValue(Config::XML_PATH_SITEMAP_MAX_URLS, (string) $max, ScopeInterface::SCOPE_STORE, 'default');
    }

    private function fresh(): void
    {
        $om = Bootstrap::getObjectManager();
        $om->get(CacheType::class)->clean();
        $om->get(FilterConfigProvider::class)->reset();
        $om->get(CategoryTree::class)->reset();
    }

    private function repository(): FilterConfigRepositoryInterface
    {
        return Bootstrap::getObjectManager()->get(FilterConfigRepositoryInterface::class);
    }
}
