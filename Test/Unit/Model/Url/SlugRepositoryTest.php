<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Url;

use Iranimij\OpenFilter\Model\Url\SlugGenerator;
use Iranimij\OpenFilter\Model\Url\SlugProvider;
use Iranimij\OpenFilter\Model\Url\SlugRepository;
use Iranimij\OpenFilter\Model\Url\SlugStorage;
use Iranimij\OpenFilter\Model\Url\Transliterator;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Cache\FrontendInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class SlugRepositoryTest extends TestCase
{
    private const ATTRIBUTE = 93;
    private const GERMAN = 2;

    private SlugStorage&MockObject $storage;

    private SlugProvider&MockObject $provider;

    /** @var array<int, array{int, array<int, string>, int[]}> */
    private array $writes = [];

    private SlugRepository $repository;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(SlugStorage::class);
        $this->storage->method('writeOptionSlugs')->willReturnCallback(
            function (int $attributeId, int $storeId, array $slugs, array $remove = []): void {
                $this->writes[] = [$storeId, $slugs, $remove];
            }
        );
        $this->provider = $this->createMock(SlugProvider::class);
        $german = $this->createStub(StoreInterface::class);
        $german->method('getId')->willReturn(self::GERMAN);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([$german]);
        $this->repository = new SlugRepository(
            $this->storage,
            new SlugGenerator(new Transliterator()),
            $this->provider,
            $storeManager,
            $this->createStub(FrontendInterface::class),
            $this->createStub(TypeListInterface::class)
        );
    }

    public function testFirstGenerationWritesAdminNamesAndStoreRowsOnlyWhereTheyDiffer(): void
    {
        $this->storage->method('optionLabels')->willReturn([
            'admin' => [1 => 'Green', 2 => 'White', 3 => 'Black'],
            'stores' => [self::GERMAN => [1 => 'Grün', 2 => 'Weiß']],
        ]);
        $this->storage->method('optionSlugRows')->willReturn([]);

        $this->repository->regenerate(self::ATTRIBUTE);

        self::assertSame([
            [0, [1 => 'green', 2 => 'white', 3 => 'black'], []],
            [self::GERMAN, [1 => 'gruen', 2 => 'weiss'], [3]],
        ], $this->writes);
    }

    public function testExistingNamesNeverChangeWithoutOverwrite(): void
    {
        $this->storage->method('optionLabels')->willReturn([
            'admin' => [1 => 'Olive', 2 => 'White', 4 => 'Green'],
            'stores' => [self::GERMAN => [4 => 'Grün']],
        ]);
        $this->storage->method('optionSlugRows')->willReturn([
            0 => [1 => 'green', 2 => 'white'],
            self::GERMAN => [2 => 'weiss'],
        ]);

        $this->repository->regenerate(self::ATTRIBUTE);

        self::assertSame([
            [0, [4 => 'green-2'], []],
            [self::GERMAN, [4 => 'gruen'], []],
        ], $this->writes, 'option 1 keeps "green" although its label is now "Olive"');
    }

    public function testNothingToDoWritesNothing(): void
    {
        $this->storage->method('optionLabels')->willReturn(['admin' => [1 => 'Red'], 'stores' => []]);
        $this->storage->method('optionSlugRows')->willReturn([0 => [1 => 'red']]);

        $this->repository->regenerate(self::ATTRIBUTE);
        $this->storage->method('optionLabels')->willReturn(['admin' => [], 'stores' => []]);

        self::assertSame([], $this->writes);
    }

    public function testOverwriteRenamesEverythingForOneStore(): void
    {
        $this->storage->method('optionLabels')->willReturn([
            'admin' => [1 => 'Olive'],
            'stores' => [self::GERMAN => [1 => 'Oliv']],
        ]);
        $this->storage->method('optionSlugRows')->willReturn([0 => [1 => 'green'], self::GERMAN => [1 => 'gruen']]);

        $this->repository->regenerate(self::ATTRIBUTE, self::GERMAN, true);

        self::assertSame([[self::GERMAN, [1 => 'oliv'], []]], $this->writes, 'admin row untouched for one store');
    }

    public function testSaveOptionSlugChecksShapeAndUniqueness(): void
    {
        $this->storage->method('attributeOfOption')->willReturnMap([[1, self::ATTRIBUTE], [9, null]]);
        $this->storage->method('optionSlugRows')->willReturn([0 => [1 => 'green', 2 => 'white']]);

        $this->repository->saveOptionSlug(1, 0, 'forest');
        $this->repository->saveOptionSlug(1, self::GERMAN, 'green');
        self::assertSame([[0, [1 => 'forest'], []], [self::GERMAN, [], [1]]], $this->writes);

        foreach ([[1, 'White'], [1, 'white'], [9, 'nine']] as [$optionId, $slug]) {
            try {
                $this->repository->saveOptionSlug($optionId, 0, $slug);
                self::fail('expected an exception for ' . $slug);
            } catch (LocalizedException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
    }

    public function testAttributeSlugs(): void
    {
        $this->provider->method('forStore')->willReturn([
            'attributes' => [93 => 'color', 94 => 'size'],
            'options' => [93 => [1 => 'green']],
        ]);
        $this->storage->expects(self::once())->method('writeAttributeSlug')->with(93, self::GERMAN, 'farbe');

        self::assertSame('color', $this->repository->getAttributeSlug(93, 0));
        self::assertSame([1 => 'green'], $this->repository->getOptionSlugs(93, 0));
        self::assertSame([], $this->repository->getOptionSlugs(94, 0));
        $this->repository->saveAttributeSlug(93, self::GERMAN, 'farbe');

        foreach (['size', 'price', 'Farbe', '-x'] as $slug) {
            try {
                $this->repository->saveAttributeSlug(93, self::GERMAN, $slug);
                self::fail('expected an exception for ' . $slug);
            } catch (LocalizedException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        }
        $this->expectException(NoSuchEntityException::class);
        $this->repository->getAttributeSlug(7, 0);
    }
}
