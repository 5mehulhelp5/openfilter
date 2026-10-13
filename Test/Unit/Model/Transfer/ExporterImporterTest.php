<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Transfer;

use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Api\SlugRepositoryInterface;
use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Config\FilterConfigEditor;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\ResourceModel\FilterConfigStorage;
use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Iranimij\OpenFilter\Model\Transfer\Exporter;
use Iranimij\OpenFilter\Model\Transfer\Importer;
use Iranimij\OpenFilter\Model\Transfer\Schema;
use Iranimij\OpenFilter\Model\Url\OptionSlugBatch;
use Iranimij\OpenFilter\Model\Url\UrlCodec;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[AllowMockObjectsWithoutExpectations]
class ExporterImporterTest extends TestCase
{
    /** @var array<string, FilterConfig> */
    private array $configs = [];

    /** @var AdapterInterface&MockObject */
    private AdapterInterface $connection;

    /** @var FilterConfigRepositoryInterface&MockObject */
    private FilterConfigRepositoryInterface $repository;

    /** @var OptionSlugBatch&MockObject */
    private OptionSlugBatch $batch;

    /** @var SlugRepositoryInterface&MockObject */
    private SlugRepositoryInterface $slugs;

    protected function setUp(): void
    {
        $defaults = new Defaults();
        $this->configs = [
            'color' => $defaults->forAttribute('color', 93, 'select', 'visual', 10),
            'rating' => $defaults->forBuiltin('rating'),
        ];
    }

    public function testExportByCodesAndLabels(): void
    {
        $export = $this->exporter()->export();

        self::assertSame(1, $export['schema']);
        self::assertSame(['de' => 'Farbe'], $export['filters']['color']['labels']);
        self::assertSame(['admin' => 'color', 'de' => 'farbe'], $export['filters']['color']['url_name']);
        self::assertSame(
            ['Red' => ['admin' => 'red', 'de' => 'rot'], 'Wool #1' => ['admin' => 'wool'], 'Wool #2' => ['admin' => 'wool-2']],
            $export['filters']['color']['options']
        );
        self::assertArrayNotHasKey('options', $export['filters']['rating']);
        self::assertSame(
            ['color' => ['builtin' => false, 'options' => ['Red', 'Wool #1', 'Wool #2']], 'rating' => ['builtin' => true, 'options' => []]],
            $this->exporter()->knownFilters()
        );
    }

    public function testImportWritesEverythingInOneTransaction(): void
    {
        $importer = $this->importer();
        $this->connection->expects(self::once())->method('beginTransaction');
        $this->connection->expects(self::once())->method('commit');
        $this->connection->expects(self::never())->method('rollBack');
        $this->repository->expects(self::exactly(2))->method('save')->willReturnArgument(0);
        $this->repository->expects(self::once())->method('saveLabel')->with('color', 2, 'Farbton');
        $this->slugs->expects(self::once())->method('saveAttributeSlug')->with(93, 2, 'farbton');
        $this->batch->expects(self::once())->method('save')->with(93, [12 => [0 => 'wool-b']]);

        $result = $importer->import($this->file(), false);

        self::assertTrue($result['applied']);
        self::assertSame([], $result['errors']);
        self::assertContains('color.settings.visible_options: 6 → 9', $result['changes']);
    }

    public function testDryRunAndRefusedValuesRollBack(): void
    {
        $importer = $this->importer();
        $this->connection->expects(self::exactly(2))->method('rollBack');
        $this->connection->expects(self::never())->method('commit');
        $this->repository->method('save')->willReturnArgument(0);

        self::assertFalse($importer->import($this->file(), true)['applied']);

        $this->batch->method('save')->willThrowException(new LocalizedException(__('taken')));
        $result = $importer->import($this->file(), false);
        self::assertSame(['color: taken'], $result['errors']);
    }

    public function testInvalidFilesAndUnavailableLogicNeverOpenATransaction(): void
    {
        $importer = $this->importer();
        $this->connection->expects(self::once())->method('beginTransaction');

        self::assertNotEmpty($importer->import(['schema' => 9], false)['errors']);
        $file = $this->file();
        $file['filters']['color']['settings']['logic'] = 'and';
        self::assertStringContainsString('logic "and"', implode('', $importer->import($file, false)['errors']));
    }

    /**
     * @return array<string, mixed>
     */
    private function file(): array
    {
        $file = $this->exporter()->export();
        $file['filters']['color']['settings']['visible_options'] = 9;
        $file['filters']['color']['settings']['expanded'] = true;
        $file['filters']['color']['labels'] = ['de' => 'Farbton'];
        $file['filters']['color']['url_name'] = ['de' => 'farbton'];
        $file['filters']['color']['options'] = ['Wool #2' => ['admin' => 'wool-b']];

        return $file;
    }

    private function exporter(): Exporter
    {
        $provider = $this->createStub(FilterConfigProvider::class);
        $provider->method('getForStore')->willReturn($this->configs);
        $configStorage = $this->createStub(FilterConfigStorage::class);
        $configStorage->method('labelOverrides')->willReturnCallback(
            static fn (?int $id): array => $id === 93 ? [2 => 'Farbe', 99 => 'gone'] : []
        );
        $slugStorage = $this->createStub(SlugStorage::class);
        $slugStorage->method('attributeSlugsForStore')->willReturnCallback(
            static fn (int $store): array => [0 => [93 => 'color'], 2 => [93 => 'farbe']][$store] ?? []
        );
        $slugStorage->method('optionLabels')->willReturn(['admin' => [10 => 'Red', 11 => 'Wool', 12 => 'Wool'], 'stores' => []]);
        $slugStorage->method('optionSlugRows')->willReturn([0 => [10 => 'red', 11 => 'wool', 12 => 'wool-2'], 2 => [10 => 'rot']]);
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(2);
        $store->method('getCode')->willReturn('de');
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([$store]);

        return new Exporter($provider, $configStorage, $slugStorage, $storeManager, new Schema());
    }

    private function importer(): Importer
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->connection);
        $this->repository = $this->createMock(FilterConfigRepositoryInterface::class);
        $this->repository->method('getByCode')->willReturnCallback(fn (string $code) => $this->configs[$code]);
        $this->slugs = $this->createMock(SlugRepositoryInterface::class);
        $this->slugs->method('getAttributeSlug')->willReturnCallback(
            static fn (int $id, int $store): string => $store === 2 ? 'farbe' : 'color'
        );
        $this->batch = $this->createMock(OptionSlugBatch::class);

        return new Importer(
            $this->exporter(),
            new Schema(),
            $this->repository,
            new FilterConfigEditor(),
            $this->slugs,
            $this->batch,
            $this->createStub(FilterConfigProvider::class),
            $this->createStub(UrlCodec::class),
            $resource,
            $this->createStub(CacheType::class),
            $this->createStub(LoggerInterface::class)
        );
    }
}
