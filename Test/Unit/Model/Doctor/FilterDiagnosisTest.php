<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Doctor;

use Iranimij\OpenFilter\Model\Coexistence\Detector;
use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Doctor\Check;
use Iranimij\OpenFilter\Model\Doctor\EngineProbe;
use Iranimij\OpenFilter\Model\Doctor\FilterDiagnosis;
use Iranimij\OpenFilter\Model\Index\Fields;
use Iranimij\OpenFilter\Model\ResourceModel\FilterConfigStorage;
use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\Category;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class FilterDiagnosisTest extends TestCase
{
    /** @var array<string, bool> */
    private array $flags = ['openfilter/general/enabled' => true, 'cataloginventory/options/show_out_of_stock' => true];

    private ?string $other = null;

    /** @var array<string, FilterConfig> */
    private array $configs;

    /** @var array<string, mixed>|null */
    private ?array $attribute = ['code' => 'color', 'input' => 'select', 'is_filterable' => 1, 'is_filterable_in_search' => 1];

    /** @var array<int, string> */
    private array $slugs = [49 => 'black', 50 => 'blue'];

    /** @var string[]|null */
    private ?array $indexFields = ['color', 'of_in_stock', 'of_rating', 'of_on_sale_0_1', 'of_news_from', 'price_0_1'];

    private ?int $count = 5;

    private ?int $distinct = 2;

    private ?Category $category = null;

    /** @var array<int, array<string, mixed>> */
    private array $clauses = [];

    protected function setUp(): void
    {
        $defaults = new Defaults();
        $this->configs = [
            'color' => $defaults->forAttribute('color', 93, 'select', 'visual', 10),
            'stock' => $defaults->forBuiltin('stock')->with(['active' => true]),
            'rating' => $defaults->forBuiltin('rating')->with(['active' => true]),
            'sale' => $defaults->forBuiltin('sale')->with(['active' => true]),
            'new' => $defaults->forBuiltin('new')->with(['active' => true]),
            'price' => $defaults->forBuiltin('price')->with(['attribute_id' => 77]),
            'category' => $defaults->forBuiltin('category'),
        ];
        $this->category = $this->category(true, 'PRODUCTS');
    }

    public function testAHealthyFilterPassesEveryCheck(): void
    {
        $checks = $this->diagnose('color', 12);

        self::assertSame(
            ['enabled', 'coexistence', 'shown', 'category_pages', 'search_pages', 'url_names', 'index_field',
                'category_anchor', 'products', 'single_option'],
            array_map(static fn (Check $c): string => $c->code, $checks)
        );
        self::assertSame([], $this->failing($checks));
        self::assertSame(['exists' => ['field' => 'color']], $this->clauses[0]);
        self::assertNull($checks[0]->toArray()['fix'], 'no fix shown for a passing check');
    }

    public function testAnAttributeThatIsNoFilterStopsAfterTheGeneralChecks(): void
    {
        $this->flags['openfilter/general/enabled'] = false;
        $this->other = 'ElasticSuite';

        $checks = $this->diagnose('brand');

        self::assertSame(['enabled', 'coexistence', 'filter'], $this->failing($checks));
        self::assertStringContainsString('ElasticSuite', $checks[1]->detail);
        self::assertSame('catalog/product_attribute/index', $checks[2]->toArray()['url']);
    }

    public function testEachReasonOfAnAttributeFilterIsNamedWithItsFix(): void
    {
        $this->configs['color'] = $this->configs['color']->with(['active' => false]);
        $this->attribute = ['code' => 'color', 'input' => 'select', 'is_filterable' => 0, 'is_filterable_in_search' => 0];
        $this->slugs = [49 => 'black'];
        $this->indexFields = ['sku'];

        $checks = $this->diagnose('color');

        self::assertSame(['shown', 'category_pages', 'search_pages', 'url_names', 'index_field'], $this->failing($checks));
        $byCode = array_column(array_map(static fn (Check $c): array => $c->toArray(), $checks), null, 'code');
        self::assertStringContainsString('Blue', $byCode['url_names']['detail']);
        self::assertSame('catalog/product_attribute/edit/attribute_id/93', $byCode['category_pages']['url']);
        self::assertStringContainsString('indexer:reindex', (string) $byCode['index_field']['fix']);
    }

    public function testTheStockFilterNeedsOutOfStockProductsShownAndAnEngineThatAnswers(): void
    {
        $this->flags['cataloginventory/options/show_out_of_stock'] = false;
        $this->indexFields = null;

        $checks = $this->diagnose('stock');

        self::assertSame(['out_of_stock_shown', 'engine'], $this->failing($checks));
    }

    public function testCategoryPageReasons(): void
    {
        $this->category = $this->category(false, 'PAGE');
        $this->count = 0;

        self::assertSame(['category_anchor', 'products'], $this->failing($this->diagnose('color', 12)));

        $this->category = $this->category(true, 'PRODUCTS');
        $this->count = 4;
        $this->distinct = 1;
        self::assertSame(['single_option'], $this->failing($this->diagnose('color', 12)));

        $this->category = null;
        self::assertSame(['category'], $this->failing($this->diagnose('color', 999)));
    }

    public function testBuiltinsAreProbedThroughTheirOwnFields(): void
    {
        foreach (['rating', 'stock', 'sale', 'new', 'price'] as $code) {
            self::assertSame([], $this->failing($this->diagnose($code, 12)), $code);
        }

        self::assertSame(['range' => [Fields::RATING => ['gt' => 0]]], $this->clauses[0]);
        self::assertSame(['term' => [Fields::IN_STOCK => 1]], $this->clauses[1]);
        self::assertSame(['term' => ['of_on_sale_0_1' => 1]], $this->clauses[2]);
        self::assertSame(['range' => [Fields::NEWS_FROM => ['lte' => 'now']]], $this->clauses[3]['bool']['filter'][0]);
        self::assertSame(['exists' => ['field' => 'price_0_1']], $this->clauses[4]);

        $category = $this->diagnose('category', 12);
        self::assertSame(['enabled', 'coexistence', 'shown', 'category_anchor'], array_map(static fn (Check $c): string => $c->code, $category));
    }

    public function testAnEngineThatCannotCountSkipsTheProductChecks(): void
    {
        $this->count = null;

        $codes = array_map(static fn (Check $c): string => $c->code, $this->diagnose('color', 12));

        self::assertNotContains('products', $codes);
        self::assertContains('category_anchor', $codes);
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

    /**
     * @return Check[]
     */
    private function diagnose(string $key, ?int $categoryId = null): array
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(fn (string $path): bool => $this->flags[$path] ?? false);
        $detector = $this->createStub(Detector::class);
        $detector->method('getActiveReplacement')->willReturn($this->other);
        $provider = $this->createStub(FilterConfigProvider::class);
        $provider->method('getForStore')->willReturn($this->configs);
        $provider->method('keyOf')->willReturnCallback(static fn ($c): string => $c->getCode());
        $configStorage = $this->createStub(FilterConfigStorage::class);
        $configStorage->method('attributeFlags')->willReturn($this->attribute);
        $slugStorage = $this->createStub(SlugStorage::class);
        $slugStorage->method('optionLabels')->willReturn(['admin' => [49 => 'Black', 50 => 'Blue'], 'stores' => []]);
        $slugStorage->method('optionSlugRows')->willReturn([0 => $this->slugs]);
        $engine = $this->createStub(EngineProbe::class);
        $engine->method('fields')->willReturn($this->indexFields);
        $engine->method('count')->willReturnCallback(function (int $s, int $c, array $clause): ?int {
            $this->clauses[] = $clause;

            return $this->count;
        });
        $engine->method('distinct')->willReturn($this->distinct);
        $categories = $this->createStub(CategoryRepositoryInterface::class);
        $categories->method('get')->willReturnCallback(
            fn () => $this->category ?? throw new NoSuchEntityException(__('none'))
        );
        $store = $this->createStub(Store::class);
        $store->method('getWebsiteId')->willReturn(1);
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(static function (string $route, ?array $params = []): string {
            foreach ($params ?? [] as $name => $value) {
                $route .= '/' . $name . '/' . $value;
            }

            return $route;
        });

        return (new FilterDiagnosis(
            $scopeConfig,
            $detector,
            $provider,
            $configStorage,
            $slugStorage,
            $engine,
            new Fields(),
            $categories,
            $stores,
            $url
        ))->diagnose($key, 1, $categoryId);
    }

    private function category(bool $anchor, string $mode): Category
    {
        /** @var Category $category */
        $category = (new ObjectManager($this))->getObject(Category::class);
        $category->setData(['is_anchor' => $anchor ? 1 : 0, 'display_mode' => $mode, 'name' => 'Tops']);

        return $category;
    }
}
