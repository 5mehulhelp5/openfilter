<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Doctor;

use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Doctor\Check;
use Iranimij\OpenFilter\Model\Doctor\FilterDiagnosis;
use Iranimij\OpenFilter\Model\Doctor\Report;
use Iranimij\OpenFilter\Model\Doctor\SystemChecks;
use PHPUnit\Framework\TestCase;

class ReportTest extends TestCase
{
    public function testStoreChecksOnceThenOneSectionPerFilter(): void
    {
        $system = $this->createMock(SystemChecks::class);
        $system->expects(self::once())->method('run')->with(1, [93 => 'color'])
            ->willReturn([new Check('cache', 'Cache', true, '')]);
        $report = new Report($this->diagnosis(), $system, $this->provider());

        $sections = $report->run(1);

        self::assertSame(['OpenFilter', 'color', 'rating'], array_keys($sections));
        self::assertSame(['enabled', 'coexistence', 'cache'], $this->codes($sections['OpenFilter']));
        self::assertSame(['shown', 'index_field'], $this->codes($sections['color']), 'URL names are a store check');
        self::assertTrue($report->passed($sections));
    }

    public function testOneFilterOnly(): void
    {
        $system = $this->createMock(SystemChecks::class);
        $system->expects(self::once())->method('run')->with(1, [])->willReturn([]);

        $sections = (new Report($this->diagnosis(), $system, $this->provider()))->run(1, 12, 'rating');

        self::assertSame(['OpenFilter', 'rating'], array_keys($sections));
    }

    public function testWarningsDoNotFailTheReport(): void
    {
        $report = new Report($this->diagnosis(), $this->createStub(SystemChecks::class), $this->provider());
        $warn = new Check('search_pages', 'Search', false, '');
        $fail = new Check('index_field', 'Field', false, '');

        self::assertSame(Report::WARN, $report->level($warn));
        self::assertSame(Report::FAIL, $report->level($fail));
        self::assertSame(Report::OK, $report->level(new Check('shown', 'Shown', true, '')));
        self::assertTrue($report->passed(['color' => [$warn]]));
        self::assertFalse($report->passed(['color' => [$warn, $fail]]));
    }

    private function diagnosis(): FilterDiagnosis
    {
        $diagnosis = $this->createStub(FilterDiagnosis::class);
        $diagnosis->method('diagnose')->willReturnCallback(static fn (string $key): array => [
            new Check('enabled', 'On', true, ''),
            new Check('coexistence', 'Alone', true, ''),
            new Check('shown', 'Shown', true, ''),
            new Check('url_names', 'Names', true, ''),
            new Check('index_field', 'Field', true, $key),
        ]);

        return $diagnosis;
    }

    private function provider(): FilterConfigProvider
    {
        $defaults = new Defaults();
        $provider = $this->createStub(FilterConfigProvider::class);
        $provider->method('getForStore')->willReturn([
            'color' => $defaults->forAttribute('color', 93, 'select', 'visual', 10),
            'rating' => $defaults->forBuiltin('rating'),
        ]);

        return $provider;
    }

    /**
     * @param Check[] $checks
     * @return string[]
     */
    private function codes(array $checks): array
    {
        return array_map(static fn (Check $check): string => $check->toArray()['code'], $checks);
    }
}
