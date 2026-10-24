<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Doctor;

use Iranimij\OpenFilter\Model\Doctor\Check;
use Iranimij\OpenFilter\Model\Doctor\EngineProbe;
use Iranimij\OpenFilter\Model\Doctor\SystemChecks;
use Iranimij\OpenFilter\Model\Migration\VendorModules;
use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Iranimij\OpenFilter\Model\Search\StrategyResolver;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use PHPUnit\Framework\TestCase;

class SystemChecksTest extends TestCase
{
    public function testHealthyInstallation(): void
    {
        $checks = $this->rows($this->checks()->run(1, [93 => 'color']));

        self::assertSame(
            ['engine', 'strategy', 'cache', 'url_names', 'search_page_filters', 'replacements', 'other_filter_modules'],
            array_keys($checks)
        );
        self::assertSame([true, true, true, true, true, true, true], array_column($checks, 'ok'));
        self::assertStringContainsString('single_query (engine opensearch)', $checks['strategy']['detail']);
        self::assertStringContainsString('2 fields', $checks['engine']['detail']);
    }

    public function testProblemsComeWithAFix(): void
    {
        $di = [
            'global' => ['preferences' => ['Magento\Catalog\Model\Layer\FilterList' => 'Acme\Nav\FilterList']],
            'frontend' => [
                'Magento\LayeredNavigation\Block\Navigation' => ['plugins' => [
                    'acme' => ['instance' => 'Acme\Nav\Plugin'],
                    'off' => ['instance' => 'Acme\Nav\Other', 'disabled' => true],
                    'core' => ['instance' => 'Magento\Swatches\Plugin'],
                    'ours' => ['instance' => 'Iranimij\OpenFilter\Plugin\X'],
                ]],
            ],
        ];
        $checks = $this->rows($this->checks(null, false, [93 => [1 => 'red']], $di, 0, ['Amasty_ShopbySeo', 'Magento_Catalog'])->run(1, [93 => 'color']));

        foreach (['engine', 'cache', 'url_names', 'search_page_filters', 'replacements', 'other_filter_modules'] as $code) {
            self::assertFalse($checks[$code]['ok'], $code);
            self::assertNotNull($checks[$code]['fix'], $code);
        }
        self::assertSame('Options without a URL name: color (1).', $checks['url_names']['detail']);
        self::assertSame(
            'Acme\Nav\FilterList replaces Magento\Catalog\Model\Layer\FilterList; '
            . 'Acme\Nav\Plugin wraps Magento\LayeredNavigation\Block\Navigation',
            $checks['replacements']['detail']
        );
        self::assertSame('Amasty_ShopbySeo (Amasty Improved Layered Navigation)', $checks['other_filter_modules']['detail']);
    }

    /**
     * @param Check[] $checks
     * @return array<string, array<string, mixed>>
     */
    private function rows(array $checks): array
    {
        $rows = [];
        foreach ($checks as $check) {
            $row = $check->toArray();
            $rows[$row['code']] = $row;
        }

        return $rows;
    }

    /**
     * @param string[]|null $fields
     * @param array<int, array<int, string>> $unnamed attribute id => option id => label without a URL name
     * @param array<string, array<string, mixed>> $di
     * @param string[] $modules enabled module names
     */
    private function checks(
        ?array $fields = ['color', 'price_0_1'],
        bool $cache = true,
        array $unnamed = [],
        array $di = [],
        int $searchAttributes = 3,
        array $modules = ['Magento_Catalog']
    ): SystemChecks {
        $engine = $this->createStub(EngineProbe::class);
        $engine->method('fields')->willReturn($fields);
        $strategies = $this->createStub(StrategyResolver::class);
        $strategies->method('code')->willReturn('single_query');
        $strategies->method('engine')->willReturn('opensearch');
        $state = $this->createStub(StateInterface::class);
        $state->method('isEnabled')->willReturn($cache);
        $slugs = $this->createStub(SlugStorage::class);
        $slugs->method('optionLabels')->willReturnCallback(static fn (int $id): array => [
            'admin' => [1 => 'Red', 2 => 'Blue'],
            'stores' => [],
        ]);
        $slugs->method('optionSlugRows')->willReturnCallback(static fn (int $id): array => [
            0 => array_diff_key([1 => 'red', 2 => 'blue'], $unnamed[$id] ?? []),
        ]);
        $loader = $this->createStub(ConfigLoaderInterface::class);
        $loader->method('load')->willReturnCallback(static fn (string $area): array => $di[$area] ?? []);

        $collection = $this->createStub(\Magento\Catalog\Model\ResourceModel\Product\Attribute\Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getSize')->willReturn($searchAttributes);
        $attributes = $this->createStub(\Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory::class);
        $attributes->method('create')->willReturn($collection);

        $moduleList = $this->createStub(ModuleListInterface::class);
        $moduleList->method('getNames')->willReturn($modules);

        return new SystemChecks($engine, $strategies, $state, $slugs, $loader, $attributes, new VendorModules($moduleList));
    }
}
