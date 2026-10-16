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

        self::assertSame(3, $export['schema']);
        self::assertSame(['de' => 'Farbe'], $export['filters']['color']['labels']);
        self::assertSame(['admin' => 'Pick one or more'], $export['filters']['color']['tooltips']);
        self::assertArrayNotHasKey('tooltips', $export['filters']['rating']);
        self::assertSame(['logic' => 'or', 'ranges' => null, 'tree_depth' => 3, 'category_mode' => 'all', 'category_ids' => []], array_intersect_key(
            $export['filters']['color']['settings'],
            ['logic' => 1, 'ranges' => 1, 'tree_depth' => 1, 'category_mode' => 1, 'category_ids' => 1]
        ));
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
        $this->repository->expects(self::once())->method('saveTooltip')->with('color', 0, null);
        $this->slugs->expects(self::once())->method('saveAttributeSlug')->with(93, 2, 'farbton');
        $this->batch->expects(self::once())->method('save')->with(93, [12 => [0 => 'wool-b']]);

        $result = $importer->import($this->file(), false);

        self::assertTrue($result['applied']);
        self::assertSame([], $result['errors']);
        self::assertContains('color.settings.visible_options: 12 → 9', $result['changes']);
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

    public function testInvalidFilesNeverOpenATransaction(): void
    {
        $importer = $this->importer();
        $this->connection->expects(self::never())->method('beginTransaction');

        self::assertStringContainsString('Unsupported schema "9"', implode('', $importer->import(['schema' => 9], false)['errors']));
    }

    public function testASchemaOneFileStillImportsAndMatchAllAndWhereItShowsAreImported(): void
    {
        $importer = $this->importer();
        $saved = [];
        $this->repository->method('save')->willReturnCallback(static function ($config) use (&$saved) {
            $saved[$config->getCode()] = $config;

            return $config;
        });

        $file = $this->file();
        $file['schema'] = 1;
        unset($file['filters']['color']['tooltips']);
        $file['filters']['color']['settings'] = ['logic' => 'and'];
        $file['filters']['rating']['settings'] = ['visible_options' => 2];

        self::assertSame([], $importer->import($file, false)['errors']);
        self::assertSame('and', $saved['color']->getLogic());

        $file['schema'] = 2;
        $file['filters']['color']['settings'] = ['category_mode' => 'except', 'category_ids' => [21, 4]];
        self::assertSame([], $importer->import($file, false)['errors']);
        self::assertSame(['except', [4, 21]], [$saved['color']->getCategoryMode(), $saved['color']->getCategoryIds()], 'where it shows');
    }

    public function testAFilterWaitingForOptionsTravelsByOptionKeys(): void
    {
        $this->configs['size'] = (new Defaults())->forAttribute('size', 94, 'select', null, 20)
            ->with(['depends_on' => 'color', 'depends_values' => ['10', '12', '77'], 'quick_bar' => true]);

        $settings = $this->exporter()->export()['filters']['size']['settings'];
        self::assertSame(['color', ['Red', 'Wool #2'], true], [$settings['depends_on'], $settings['depends_values'], $settings['quick_bar']], 'ids become keys, unknown ids drop');

        $importer = $this->importer();
        $saved = [];
        $this->repository->method('save')->willReturnCallback(static function ($config) use (&$saved) {
            $saved[$config->getCode()] = $config;

            return $config;
        });
        $file = ['schema' => 3, 'filters' => ['size' => ['settings' => ['depends_on' => 'color', 'depends_values' => ['Wool #2']]]]];
        self::assertSame([], $importer->import($file, false)['errors']);
        self::assertSame(['12'], $saved['size']->getDependsValues(), 'keys become this installation\'s ids');

        $file['filters']['size']['settings']['depends_values'] = ['Purple'];
        self::assertStringContainsString('not options of "color"', implode(' ', $importer->import($file, false)['errors']));
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
        $file['filters']['color']['tooltips'] = ['admin' => ''];
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
        $configStorage->method('tooltipOverrides')->willReturnCallback(
            static fn (?int $id): array => $id === 93 ? [0 => 'Pick one or more'] : []
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
