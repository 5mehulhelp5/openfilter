<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Config;

use Iranimij\OpenFilter\Api\Data\FilterConfigSearchResultsInterfaceFactory;
use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Config\FilterConfigList;
use Iranimij\OpenFilter\Model\Config\FilterConfigSearchResults;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Config\FilterConfigRepository;
use Iranimij\OpenFilter\Model\Config\Validator;
use Iranimij\OpenFilter\Model\ResourceModel\FilterConfigStorage;
use Magento\Framework\Api\Filter;
use Magento\Framework\Api\Search\FilterGroup;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class FilterConfigRepositoryTest extends TestCase
{
    private FilterConfigStorage&MockObject $storage;

    private FilterConfigProvider&MockObject $provider;

    private FrontendInterface&MockObject $cache;

    private TypeListInterface&MockObject $typeList;

    private FilterConfigRepository $repository;

    protected function setUp(): void
    {
        $defaults = new Defaults();
        $this->storage = $this->createMock(FilterConfigStorage::class);
        $this->provider = $this->createMock(FilterConfigProvider::class);
        $this->provider->method('getForStore')->willReturn([
            'color' => $defaults->forAttribute('color', 93, 'select', 'visual', 10),
            'attribute:new' => $defaults->forAttribute('new', 150, 'boolean', null, 20),
            'stock' => $defaults->forBuiltin('stock'),
        ]);
        $this->provider->method('keyOf')->willReturnCallback(
            static fn ($c): string => !$c->isBuiltin() && $c->getCode() === 'new' ? 'attribute:new' : $c->getCode()
        );
        $this->cache = $this->createMock(FrontendInterface::class);
        $this->typeList = $this->createMock(TypeListInterface::class);
        $this->repository = new FilterConfigRepository(
            $this->provider,
            $this->storage,
            new Validator(),
            $defaults,
            $this->cache,
            $this->typeList,
            new FilterConfigList(),
            $this->resultsFactory()
        );
    }

    public function testReads(): void
    {
        self::assertSame('color', $this->repository->getByCode('color')->getCode());
        self::assertSame('new', $this->repository->getByAttributeId(150)->getCode());
        self::assertCount(3, $this->repository->getAll());

        $this->expectException(NoSuchEntityException::class);
        $this->repository->getByAttributeId(999);
    }

    public function testUnknownCode(): void
    {
        $this->expectException(NoSuchEntityException::class);
        $this->repository->getByCode('brand');
    }

    public function testSaveAttributeValidatesWritesAndCleans(): void
    {
        $this->storage->method('optionCount')->with(93)->willReturn(4);
        $this->storage->expects(self::once())->method('saveAttribute')
            ->with(93, self::callback(static fn (array $row): bool => $row['visible_options'] === 3 && $row['is_active'] === 1));
        $this->cache->expects(self::once())->method('clean');
        $this->provider->expects(self::once())->method('reset');
        $this->typeList->expects(self::once())->method('invalidate')->with('full_page');

        $config = (new FilterConfig(['code' => 'color']))->setDisplayMode('swatch')->setVisibleOptions(3);
        $this->repository->save($config);
    }

    public function testHidingAnAttributeFilterKeepsCoreAttributeFlagsAlone(): void
    {
        $this->storage->expects(self::once())->method('saveAttribute')
            ->with(93, self::callback(static fn (array $row): bool => $row['is_active'] === 0));

        $this->repository->save((new FilterConfig(['code' => 'color']))->setDisplayMode('swatch')->setActive(false));
    }

    public function testGetListFiltersSortsAndPagesTheAdminList(): void
    {
        $criteria = (new SearchCriteria())
            ->setFilterGroups([new FilterGroup(['filters' => [new Filter(['field' => 'builtin', 'value' => '0'])]])])
            ->setSortOrders([new SortOrder(['field' => 'code', 'direction' => SortOrder::SORT_DESC])])
            ->setPageSize(1)
            ->setCurrentPage(2);

        $results = $this->repository->getList($criteria);

        self::assertSame(2, $results->getTotalCount());
        self::assertSame(['color'], array_map(static fn ($c) => $c->getCode(), $results->getItems()));
        self::assertSame($criteria, $results->getSearchCriteria());

        $stock = $this->repository->getList(
            (new SearchCriteria())->setFilterGroups([new FilterGroup(['filters' => [
                new Filter(['field' => 'label', 'value' => 'in stock', 'condition_type' => 'like']),
            ]])])
        )->getItems();
        self::assertSame(['In stock'], array_map(static fn ($c) => $c->getLabel(), $stock), 'built-in labels resolved');
    }

    public function testSaveRejectsWhatDoesNotFit(): void
    {
        $this->storage->expects(self::never())->method('saveAttribute');

        $this->expectException(InputException::class);
        $this->repository->save((new FilterConfig(['code' => 'color']))->setDisplayMode('slider'));
    }

    public function testSaveBuiltinKeepsItsActiveFlag(): void
    {
        $this->storage->expects(self::once())->method('saveBuiltin')->with('stock', true, self::isArray());

        $this->repository->save((new FilterConfig(['code' => 'stock', 'builtin' => true]))->setDisplayMode('toggle')->setActive(true));
    }

    public function testAttributeNamedLikeABuiltinIsSavedAsTheAttribute(): void
    {
        $this->storage->expects(self::once())->method('saveAttribute')->with(150, self::isArray());
        $this->storage->expects(self::never())->method('saveBuiltin');

        $this->repository->save((new FilterConfig(['code' => 'new']))->setDisplayMode('toggle'));
    }

    public function testLabelsAndReset(): void
    {
        $this->storage->expects(self::once())->method('saveAttributeLabel')->with(93, 2, 'Farbe');
        $this->storage->expects(self::once())->method('saveBuiltinLabel')->with('stock', 2, 'Lieferbar');
        $this->storage->expects(self::once())->method('deleteAttribute')->with(93);
        $this->storage->expects(self::exactly(2))->method('saveBuiltin');

        $this->repository->saveLabel('color', 2, 'Farbe');
        $this->repository->saveLabel('stock', 2, 'Lieferbar');
        $this->repository->resetToDefault('color');
        $this->repository->resetToDefault('stock');
    }

    private function resultsFactory(): FilterConfigSearchResultsInterfaceFactory
    {
        $factory = $this->createMock(FilterConfigSearchResultsInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(static fn () => new FilterConfigSearchResults());

        return $factory;
    }
}
