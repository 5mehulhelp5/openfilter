<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Api;

use Iranimij\OpenFilter\Api\Data\ImportResultInterfaceFactory;
use Iranimij\OpenFilter\Model\Api\ConfigTransfer;
use Iranimij\OpenFilter\Model\Api\ImportResult;
use Iranimij\OpenFilter\Model\Transfer\Exporter;
use Iranimij\OpenFilter\Model\Transfer\Importer;
use PHPUnit\Framework\TestCase;

class ConfigTransferTest extends TestCase
{
    public function testExportIsTheCommandsDocument(): void
    {
        $exporter = $this->createStub(Exporter::class);
        $exporter->method('export')->willReturn(['schema' => 2, 'filters' => ['color' => ['labels' => ['de' => 'Farbe – ü']]]]);

        self::assertSame(
            '{"schema":2,"filters":{"color":{"labels":{"de":"Farbe – ü"}}}}',
            $this->transfer($exporter, $this->createStub(Importer::class))->export()
        );
    }

    public function testImportPassesTheDocumentAndTheDryRunOn(): void
    {
        $importer = $this->createMock(Importer::class);
        $importer->expects(self::once())->method('import')->with(['schema' => 2, 'filters' => []], false)
            ->willReturn(['applied' => true, 'changes' => ['color: sort by name'], 'errors' => []]);

        $result = $this->transfer($this->createStub(Exporter::class), $importer)->import('{"schema":2,"filters":[]}', false);

        self::assertSame([true, ['color: sort by name'], []], [$result->isApplied(), $result->getChanges(), $result->getErrors()]);
    }

    public function testInvalidJsonChangesNothing(): void
    {
        $importer = $this->createMock(Importer::class);
        $importer->expects(self::never())->method('import');

        $result = $this->transfer($this->createStub(Exporter::class), $importer)->import('{nope');

        self::assertFalse($result->isApplied());
        self::assertSame(['The configuration is not valid JSON: Syntax error'], $result->getErrors());
    }

    private function transfer(Exporter $exporter, Importer $importer): ConfigTransfer
    {
        $factory = $this->createStub(ImportResultInterfaceFactory::class);
        $factory->method('create')->willReturnCallback(static fn (array $data = []) => new ImportResult(
            $data['applied'] ?? false,
            $data['changes'] ?? [],
            $data['errors'] ?? []
        ));

        return new ConfigTransfer($exporter, $importer, $factory);
    }
}
