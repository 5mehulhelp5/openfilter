<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Url;

use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Iranimij\OpenFilter\Model\Url\OptionSlugBatch;
use Iranimij\OpenFilter\Model\Url\SlugGenerator;
use Iranimij\OpenFilter\Model\Url\SlugProvider;
use Iranimij\OpenFilter\Model\Url\Transliterator;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class OptionSlugBatchTest extends TestCase
{
    private const ATTRIBUTE = 93;
    private const DE = 2;

    private SlugStorage&MockObject $storage;

    private FrontendInterface&MockObject $cache;

    /** @var array<int, array{array<int, string>, int[]}> store id => [written, removed] */
    private array $writes = [];

    protected function setUp(): void
    {
        $this->storage = $this->createMock(SlugStorage::class);
        $this->storage->method('optionLabels')->with(self::ATTRIBUTE)->willReturn([
            'admin' => [10 => 'Red', 11 => 'Blue', 12 => 'Green'],
            'stores' => [self::DE => [10 => 'Rot', 11 => 'Blau', 12 => 'Grün']],
        ]);
        $this->storage->method('optionSlugRows')->with(self::ATTRIBUTE)->willReturn([
            0 => [10 => 'red', 11 => 'blue', 12 => 'old-green'],
            self::DE => [10 => 'rot', 11 => 'blau'],
        ]);
        $this->storage->method('writeOptionSlugs')->willReturnCallback(
            function (int $attributeId, int $storeId, array $slugs, array $remove = []): void {
                self::assertSame(self::ATTRIBUTE, $attributeId);
                $this->writes[$storeId] = [$slugs, $remove];
            }
        );
        $this->cache = $this->createMock(FrontendInterface::class);
    }

    public function testSwappingTwoNamesIsOneValidSet(): void
    {
        $this->cache->expects(self::once())->method('clean');

        $this->batch()->save(self::ATTRIBUTE, [10 => [0 => 'blue'], 11 => [0 => 'red']]);

        self::assertSame([[10 => 'blue', 11 => 'red'], []], $this->writes[0]);
        self::assertArrayNotHasKey(self::DE, $this->writes, 'German names unchanged');
    }

    public function testACollisionWritesNothing(): void
    {
        $this->storage->expects(self::never())->method('writeOptionSlugs');
        $this->cache->expects(self::never())->method('clean');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Another option of this filter already uses the URL name "blue".');

        $this->batch()->save(self::ATTRIBUTE, [10 => [0 => 'blue']]);
    }

    public function testACollisionInAStoreViewWritesNothing(): void
    {
        $this->storage->expects(self::never())->method('writeOptionSlugs');

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('"rot"');

        $this->batch()->save(self::ATTRIBUTE, [11 => [self::DE => 'rot']]);
    }

    public function testTheShapeIsCheckedAndNamesTheOption(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Blue');

        $this->batch()->save(self::ATTRIBUTE, [11 => [0 => 'Blue Sky']]);
    }

    public function testTheNameForAllStoreViewsIsRequired(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('Red');

        $this->batch()->save(self::ATTRIBUTE, [10 => [0 => ' ']]);
    }

    public function testStoreViewNamesThatEqualTheGeneralNameOrAreEmptyLoseTheirRow(): void
    {
        $this->batch()->save(self::ATTRIBUTE, [
            10 => [0 => 'red', self::DE => ''],
            11 => [0 => 'blue', self::DE => 'blue'],
            12 => [0 => 'old-green', self::DE => 'gruen'],
        ]);

        self::assertArrayNotHasKey(0, $this->writes, 'nothing changed for all store views');
        self::assertSame([[12 => 'gruen'], [10, 11]], $this->writes[self::DE]);
    }

    public function testRegenerateMakesNamesFromTheLabelsAgain(): void
    {
        $this->batch()->save(self::ATTRIBUTE, [12 => [0 => 'ignored', self::DE => 'ignored']], [12]);

        self::assertSame([[12 => 'green'], []], $this->writes[0]);
        self::assertSame([[12 => 'gruen'], []], $this->writes[self::DE]);
    }

    public function testRegenerateInOneStoreViewLeavesTheOtherScopesAlone(): void
    {
        $this->batch()->save(self::ATTRIBUTE, [], [12], self::DE);

        self::assertSame([self::DE => [[12 => 'gruen'], []]], $this->writes);
    }

    public function testRegenerateForAllStoreViewsOnlyKeepsStoreViewsThatFollowIt(): void
    {
        $this->batch()->save(self::ATTRIBUTE, [], [12], 0);

        self::assertSame([0 => [[12 => 'green'], []]], $this->writes);
    }

    public function testNothingPostedNothingWritten(): void
    {
        $this->storage->expects(self::never())->method('writeOptionSlugs');
        $this->cache->expects(self::never())->method('clean');

        $this->batch()->save(self::ATTRIBUTE, [10 => [0 => 'red', self::DE => 'rot']]);
    }

    private function batch(): OptionSlugBatch
    {
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(self::DE);
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStores')->willReturn([self::DE => $store]);
        $typeList = $this->createStub(TypeListInterface::class);

        return new OptionSlugBatch(
            $this->storage,
            new SlugGenerator(new Transliterator()),
            $this->createStub(SlugProvider::class),
            $stores,
            $this->cache,
            $typeList
        );
    }
}
