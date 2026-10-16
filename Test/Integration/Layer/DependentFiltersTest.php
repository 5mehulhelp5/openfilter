<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Layer;

use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Url\FilterStateProvider;
use Iranimij\OpenFilter\Model\Url\UrlCodec;
use Iranimij\OpenFilter\Test\Integration\Catalog\M1Catalog;
use Magento\Catalog\Model\Layer\Resolver;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * M6 S1 dependent filters: size waits for red in colour. It shows only after that pick, its picks leave with it
 * (old links redirect), and the products of a page are those of its canonical state.
 *
 * @magentoAppArea frontend
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 * @magentoCache openfilter enabled
 */
class DependentFiltersTest extends AbstractController
{
    /** @var array{category: int, store: int, options: array<string, array<string, int>>}|null */
    private static ?array $catalog = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$catalog = M1Catalog::create();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->wait(M1Catalog::COLOR, [(string) self::$catalog['options'][M1Catalog::COLOR]['Red']]);
    }

    protected function tearDown(): void
    {
        $this->wait(null, []);
        parent::tearDown();
    }

    /**
     * @return array<string, array{array<string, string>, bool}>
     */
    public static function pages(): array
    {
        return [
            'no pick' => [[], false],
            'another colour' => [[M1Catalog::COLOR => 'blue'], false],
            'the colour it waits for' => [[M1Catalog::COLOR => 'red'], true],
            'among other colours' => [[M1Catalog::COLOR => 'red,blue'], true],
        ];
    }

    /**
     * @dataProvider pages
     * @param array<string, string> $query
     */
    #[DataProvider('pages')]
    public function testSizeShowsOnlyAfterRed(array $query, bool $shown): void
    {
        self::assertSame($shown, in_array(M1Catalog::SIZE, $this->filterCodes($query), true));
        self::assertContains(M1Catalog::COLOR, $this->filterCodes($query), 'the parent always shows');
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function oldLinks(): array
    {
        return [
            'size alone' => [[M1Catalog::SIZE => 'm'], ''],
            'size after another colour' => [[M1Catalog::COLOR => 'blue', M1Catalog::SIZE => 'm', 'p' => '2'], '?' . M1Catalog::COLOR . '=blue'],
        ];
    }

    /**
     * @dataProvider oldLinks
     * @param array<string, string> $query
     */
    #[DataProvider('oldLinks')]
    public function testPicksOfAFilterThatDoesNotShowRedirectAway(array $query, string $expected): void
    {
        $this->getRequest()->setMethod('GET')->setQueryValue($query);
        $this->dispatch('catalog/category/view/id/' . self::$catalog['category']);

        self::assertSame(301, $this->getResponse()->getHttpResponseCode());
        $location = (string) $this->getResponse()->getHeader('Location')->getFieldValue();
        self::assertStringEndsWith('catalog/category/view/id/' . self::$catalog['category'] . '/' . $expected, $location, 'the page resets');
    }

    public function testAPageWithTheParentsPickStays(): void
    {
        $this->getRequest()->setMethod('GET')->setQueryValue([M1Catalog::COLOR => 'red', M1Catalog::SIZE => 'm']);
        $this->dispatch('catalog/category/view/id/' . self::$catalog['category']);

        self::assertSame(200, $this->getResponse()->getHttpResponseCode());
    }

    /**
     * @param string|null $parent
     * @param string[] $values
     */
    private function wait(?string $parent, array $values): void
    {
        $om = Bootstrap::getObjectManager();
        $repository = $om->get(FilterConfigRepositoryInterface::class);
        $repository->save($repository->getByCode(M1Catalog::SIZE)->setDependsOn($parent)->setDependsValues($values));
        $om->get(CacheType::class)->clean();
        $om->get(FilterConfigProvider::class)->reset();
        $om->get(UrlCodec::class)->reset();
    }

    /**
     * @param array<string, string> $query
     * @return string[] attribute codes of the filters on the category page with this query
     */
    private function filterCodes(array $query): array
    {
        $om = Bootstrap::getObjectManager();
        $om->removeSharedInstance(SearchCriteriaBuilder::class);
        $om->removeSharedInstance(Resolver::class);
        $om->removeSharedInstance(FilterStateProvider::class);
        $request = $this->getRequest();
        $request->setParams($query);
        $request->setRouteName('catalog')->setControllerName('category')->setActionName('view');
        $resolver = $om->get(Resolver::class);
        $resolver->create(Resolver::CATALOG_LAYER_CATEGORY);
        $layer = $resolver->get();
        $layer->setCurrentCategory(self::$catalog['category']);
        $codes = [];
        foreach ($om->create('categoryFilterList')->getFilters($layer) as $filter) {
            if ($filter->hasData('attribute_model')) {
                $codes[] = (string) $filter->getAttributeModel()->getAttributeCode();
            }
        }

        return $codes;
    }
}
