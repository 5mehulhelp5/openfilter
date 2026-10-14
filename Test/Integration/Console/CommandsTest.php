<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Integration\Console;

use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Console\Command\ConfigExport;
use Iranimij\OpenFilter\Console\Command\ConfigImport;
use Iranimij\OpenFilter\Console\Command\Doctor;
use Iranimij\OpenFilter\Console\Command\SlugsGenerate;
use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Iranimij\OpenFilter\Test\Integration\Catalog\M1Catalog;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Console\Cli;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * openfilter:config:export / import (round trip, all or nothing, dry run), openfilter:slugs:generate (missing only,
 * overwrite, dry run) and openfilter:doctor (exit codes, JSON) on the M1 test catalog.
 *
 * @magentoAppArea crontab
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 * @magentoCache openfilter enabled
 */
class CommandsTest extends TestCase
{
    /** @var array{category: int, store: int, options: array<string, array<string, int>>} */
    private static ?array $catalog = null;

    private ?string $file = '';

    /** @var array<string, mixed> */
    private ?array $original = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$catalog = M1Catalog::create();
    }

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'of-export');
        $this->original = $this->export();
    }

    protected function tearDown(): void
    {
        // Back to the state before the test (the import under test is the restore tool).
        file_put_contents($this->file, json_encode($this->original));
        $this->command(ConfigImport::class, ['file' => $this->file]);
        unlink($this->file);
    }

    public function testExportIsPortableAndImportsBackWithoutChanges(): void
    {
        $color = $this->original['filters'][M1Catalog::COLOR];

        self::assertSame(2, $this->original['schema']);
        self::assertSame(['admin' => 'red', M1Catalog::STORE => 'rot'], $color['options']['Red'], 'by label and store code');
        self::assertIsBool($color['settings']['multiselect']);

        file_put_contents($this->file, json_encode($this->original));
        [$code, $out] = $this->command(ConfigImport::class, ['file' => $this->file]);

        self::assertSame(Cli::RETURN_SUCCESS, $code, $out);
        self::assertStringContainsString('0 change(s) imported', $out);
    }

    public function testImportAppliesSettingsLabelsAndUrlNames(): void
    {
        $file = $this->original;
        $file['filters'][M1Catalog::COLOR]['settings']['visible_options'] = 9;
        $file['filters'][M1Catalog::COLOR]['labels'][M1Catalog::STORE] = 'Farbton';
        $file['filters'][M1Catalog::COLOR]['options']['Red']['admin'] = 'crimson';
        file_put_contents($this->file, json_encode($file));

        [$code, $out] = $this->command(ConfigImport::class, ['file' => $this->file]);

        self::assertSame(Cli::RETURN_SUCCESS, $code, $out);
        self::assertStringContainsString('3 change(s) imported', $out);
        $after = $this->export()['filters'][M1Catalog::COLOR];
        self::assertSame(9, $after['settings']['visible_options']);
        self::assertSame('Farbton', $after['labels'][M1Catalog::STORE]);
        self::assertSame('crimson', $after['options']['Red']['admin']);
    }

    public function testDryRunListsChangesAndWritesNothing(): void
    {
        $file = $this->original;
        $file['filters'][M1Catalog::COLOR]['settings']['visible_options'] = 9;
        file_put_contents($this->file, json_encode($file));

        [$code, $out] = $this->command(ConfigImport::class, ['file' => $this->file, '--dry-run' => true]);

        self::assertSame(Cli::RETURN_SUCCESS, $code, $out);
        self::assertStringContainsString(M1Catalog::COLOR . '.settings.visible_options: 6 → 9', $out);
        self::assertStringContainsString('dry run, nothing written', $out);
        self::assertSame($this->original, $this->export(), 'cache and database unchanged');
        self::assertSame(6, $this->fromDatabase(M1Catalog::COLOR));
    }

    public function testARefusedValueRollsBackFiltersAlreadyWritten(): void
    {
        $file = $this->original;
        $file['filters'][M1Catalog::COLOR]['settings']['visible_options'] = 9;
        $file['filters'][M1Catalog::SIZE]['options']['M']['admin'] = 'l'; // taken by L
        file_put_contents($this->file, json_encode($file));

        [$code, $out] = $this->command(ConfigImport::class, ['file' => $this->file]);

        self::assertSame(Cli::RETURN_FAILURE, $code);
        self::assertStringContainsString('Nothing was imported', $out);
        self::assertSame(6, $this->fromDatabase(M1Catalog::COLOR), 'colour (written first) rolled back');
        self::assertSame($this->original, $this->export());
    }

    public function testAnInvalidFileIsRefusedBeforeAnyWrite(): void
    {
        $file = $this->original;
        $file['filters'][M1Catalog::COLOR]['settings']['visible_options'] = 9;
        $file['filters'][M1Catalog::COLOR]['options']['Purple'] = ['admin' => 'purple'];
        $file['filters']['ghost'] = [];
        file_put_contents($this->file, json_encode($file));

        [$code, $out] = $this->command(ConfigImport::class, ['file' => $this->file]);

        self::assertSame(Cli::RETURN_FAILURE, $code);
        self::assertStringContainsString('no option "Purple"', $out);
        self::assertStringContainsString('ghost: no such filter', $out);
        self::assertSame(6, $this->fromDatabase(M1Catalog::COLOR));
    }

    public function testSlugsGenerateFillsOnlyMissingNamesUnlessOverwrite(): void
    {
        $om = Bootstrap::getObjectManager();
        $attributeId = (int) $om->get(FilterConfigRepositoryInterface::class)->getByCode(M1Catalog::COLOR)->getAttributeId();
        $connection = $om->get(ResourceConnection::class)->getConnection();
        $table = $connection->getTableName('openfilter_option_slug');
        $green = self::$catalog['options'][M1Catalog::COLOR]['Green'];
        $red = self::$catalog['options'][M1Catalog::COLOR]['Red'];
        $connection->delete($table, ['option_id = ?' => $green, 'store_id = ?' => 0]);
        $connection->update($table, ['slug' => 'scarlet'], ['option_id = ?' => $red, 'store_id = ?' => 0]);

        [, $dry] = $this->command(SlugsGenerate::class, ['--attribute' => M1Catalog::COLOR, '--dry-run' => true]);
        self::assertStringContainsString(M1Catalog::COLOR . ': 1 URL name(s) would change', $dry);
        self::assertArrayNotHasKey($green, $this->slugs($attributeId));

        [$code, $out] = $this->command(SlugsGenerate::class, ['--attribute' => M1Catalog::COLOR]);
        self::assertSame(Cli::RETURN_SUCCESS, $code, $out);
        self::assertSame('green', $this->slugs($attributeId)[$green]);
        self::assertSame('scarlet', $this->slugs($attributeId)[$red], 'existing names never change silently');

        $this->command(SlugsGenerate::class, ['--attribute' => M1Catalog::COLOR, '--overwrite' => true]);
        self::assertSame('red', $this->slugs($attributeId)[$red]);
    }

    public function testDoctorReportsAsJsonAndFailsWhenTheCacheIsOff(): void
    {
        [$code, $out] = $this->command(Doctor::class, ['--filter' => M1Catalog::COLOR, '--format' => 'json']);
        $report = json_decode($out, true);

        self::assertIsArray($report);
        self::assertSame(['OpenFilter', M1Catalog::COLOR], array_keys($report['sections']));
        self::assertSame($report['passed'] ? Cli::RETURN_SUCCESS : Cli::RETURN_FAILURE, $code);

        $state = Bootstrap::getObjectManager()->get(StateInterface::class);
        $state->setEnabled(CacheType::TYPE_IDENTIFIER, false);
        try {
            [$code, $out] = $this->command(Doctor::class, ['--filter' => M1Catalog::COLOR]);
        } finally {
            $state->setEnabled(CacheType::TYPE_IDENTIFIER, true);
        }
        self::assertSame(Cli::RETURN_FAILURE, $code);
        self::assertMatchesRegularExpression('/FAIL\s+The OpenFilter cache is enabled/', $out);
        self::assertStringContainsString('Fix: Run bin/magento cache:enable openfilter.', $out);
    }

    /**
     * @param class-string $command
     * @param array<string, mixed> $input
     * @return array{int, string}
     */
    private function command(string $command, array $input): array
    {
        $tester = new CommandTester(Bootstrap::getObjectManager()->create($command));
        $code = $tester->execute($input);

        return [$code, $tester->getDisplay()];
    }

    /**
     * @return array<string, mixed>
     */
    private function export(): array
    {
        [, $out] = $this->command(ConfigExport::class, []);

        return (array) json_decode($out, true);
    }

    private function fromDatabase(string $code): int
    {
        $om = Bootstrap::getObjectManager();
        $om->get(CacheType::class)->clean();
        $om->get(FilterConfigProvider::class)->reset();

        return $om->get(FilterConfigRepositoryInterface::class)->getByCode($code)->getVisibleOptions();
    }

    /**
     * @param int $attributeId
     * @return array<int, string>
     */
    private function slugs(int $attributeId): array
    {
        return Bootstrap::getObjectManager()->get(SlugStorage::class)->optionSlugRows($attributeId)[0] ?? [];
    }
}
