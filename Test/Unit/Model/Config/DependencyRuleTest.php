<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Config;

use Iranimij\OpenFilter\Model\Config\DependencyRule;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Url\FilterState;
use PHPUnit\Framework\TestCase;

class DependencyRuleTest extends TestCase
{
    /** @var array<string, FilterConfig> */
    private array $configs;

    protected function setUp(): void
    {
        $this->configs = [
            'color' => new FilterConfig(['code' => 'color']),
            'size' => new FilterConfig(['code' => 'size', 'depends_on' => 'color']),
            'fit' => new FilterConfig(['code' => 'fit', 'depends_on' => 'size', 'depends_values' => ['167', '168']]),
            'stock' => new FilterConfig(['code' => 'stock', 'builtin' => true, 'depends_on' => 'rating', 'depends_values' => ['4']]),
            'rating' => new FilterConfig(['code' => 'rating', 'builtin' => true]),
        ];
    }

    public function testAFilterShowsAfterAnyPickOrAChosenValue(): void
    {
        $rule = new DependencyRule();

        self::assertTrue($rule->allows($this->configs['color'], new FilterState([])), 'waits for nothing');
        self::assertFalse($rule->allows($this->configs['size'], new FilterState([])));
        self::assertTrue($rule->allows($this->configs['size'], new FilterState(['color' => ['49']])), 'any value');
        self::assertFalse($rule->allows($this->configs['fit'], new FilterState(['size' => ['166']])), 'not a chosen value');
        self::assertTrue($rule->allows($this->configs['fit'], new FilterState(['size' => ['166', '168']])), 'one chosen value is enough');
        self::assertTrue($rule->allows($this->configs['stock'], new FilterState(['rating' => ['4']])), 'built-in values');
    }

    public function testPicksLeaveWithTheirParentDownTheWholeChain(): void
    {
        $rule = new DependencyRule();
        $state = new FilterState(['color' => ['49'], 'size' => ['167'], 'fit' => ['x'], 'other' => ['1']]);

        self::assertSame($state, $rule->prune($state, $this->configs), 'nothing to drop: the same state');
        self::assertSame(
            ['other' => ['1']],
            $rule->prune($state->without('color'), $this->configs)->getValues(),
            'color gone: size goes, then fit; unknown keys stay'
        );
        self::assertSame(
            ['color' => ['49'], 'size' => ['166']],
            $rule->prune(new FilterState(['color' => ['49'], 'size' => ['166'], 'fit' => ['x']]), $this->configs)->getValues()
        );
    }

    public function testUnknownFiltersAndLoopsAreProblems(): void
    {
        $rule = new DependencyRule();

        self::assertNull($rule->problem('size', 'color', $this->configs));
        self::assertNull($rule->problem('size', null, $this->configs));
        self::assertStringContainsString('no filter "ghost"', (string) $rule->problem('size', 'ghost', $this->configs));
        self::assertStringContainsString(
            '"color" and "fit" would wait for each other',
            (string) $rule->problem('color', 'fit', $this->configs),
            'color → fit → size → color'
        );
        self::assertStringContainsString('would wait for each other', (string) $rule->problem('size', 'size', $this->configs));
    }
}
