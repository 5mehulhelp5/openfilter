<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Ui\DataProvider;

use Iranimij\OpenFilter\Model\Admin\OptionImageForm;
use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Model\Admin\FormScope;
use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Config\Source\DisplayMode;
use Iranimij\OpenFilter\Model\Config\Validator;
use Iranimij\OpenFilter\Model\ResourceModel\FilterConfigStorage;
use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Iranimij\OpenFilter\Model\Url\AttributeSlugScope;
use Iranimij\OpenFilter\Ui\DataProvider\FilterForm;
use Iranimij\OpenFilter\Model\Url\Range;
use Iranimij\OpenFilter\Model\Url\RangeList;
use Magento\Framework\Api\Filter;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FilterFormTest extends TestCase
{
    private const DE = 2;

    public function testAllStoreViewsEditTheSettingsAndTheSharedNamesOnly(): void
    {
        $form = $this->form('color', $this->colour(), null, [self::DE => 'Deutsch', 3 => 'Hyvä 1.5.2']);
        $form->addFilter(new Filter(['field' => 'key', 'value' => 'color']));

        $meta = $form->getMeta();
        $data = $form->getData()['color'];

        self::assertSame(
            ['list', 'swatch', 'dropdown'],
            array_column($meta['looks']['children']['display_mode']['arguments']['data']['config']['options'], 'value')
        );
        self::assertSame('[GLOBAL]', $this->config($meta, 'behaves', 'visible_options')['scopeLabel']);
        self::assertArrayNotHasKey('disabled', $this->config($meta, 'behaves', 'visible_options'));
        self::assertSame(['tooltip_0', 'label_0'], array_keys($meta['labels']['children']));
        self::assertSame([], $this->recordFields($meta));
        self::assertArrayNotHasKey('url_name_store', $meta['seo']['children']);
        self::assertSame('0', $data['store']);
        self::assertSame('1', $data['multiselect']);
        self::assertSame('auto', $data['expanded']);
        self::assertSame(['label_0' => 'Colour'], array_intersect_key($data, ['label_0' => 1, 'label_2' => 1]));
        self::assertSame('color', $data['url_name']);
        self::assertSame(
            [['option_id' => 49, 'option_label' => 'Black', 'url_name_0' => 'black', 'regenerate' => '0']],
            $data['option_urls']
        );
    }

    public function testAStoreViewEditsItsOwnLabelAndNamesNextToTheSharedOnes(): void
    {
        $form = $this->form('color', $this->colour(), (string) self::DE, [self::DE => 'Deutsch', 3 => 'Hyvä 1.5.2']);

        $meta = $form->getMeta();
        $data = $form->getData()['color'];

        foreach (FilterForm::GLOBAL_FIELDS as $fieldset => $fields) {
            foreach ($fields as $field) {
                self::assertTrue($this->config($meta, $fieldset, $field)['disabled'], $field . ' is read only');
            }
        }
        self::assertTrue($this->config($meta, 'labels', 'label_0')['disabled']);
        self::assertSame('Label in "Deutsch"', $this->config($meta, 'labels', 'label_2')['label']);
        self::assertSame('Same as for all store views', $this->config($meta, 'labels', 'label_2')['placeholder']);
        self::assertTrue($this->config($meta, 'seo', 'url_name')['disabled']);
        self::assertSame('URL name in "Deutsch"', $this->config($meta, 'seo', 'url_name_store')['label']);
        self::assertSame(
            ['option_store_label', 'url_name_0', 'url_name_2', 'regenerate'],
            $this->recordFields($meta)
        );
        self::assertSame('2', $data['store']);
        self::assertSame('Colour', $data['label_0']);
        self::assertSame('Farbe', $data['label_2']);
        self::assertSame('color', $data['url_name']);
        self::assertSame('farbe', $data['url_name_store']);
        self::assertSame(
            [[
                'option_id' => 49,
                'option_label' => 'Black',
                'url_name_0' => 'black',
                'regenerate' => '0',
                'option_store_label' => 'Schwarz',
                'url_name_2' => 'schwarz',
            ]],
            $data['option_urls']
        );
    }

    /**
     * @dataProvider storeCounts
     */
    #[DataProvider('storeCounts')]
    public function testTheFormKeepsItsWidthForAnyNumberOfStoreViews(int $count): void
    {
        $stores = [];
        for ($id = 1; $id <= $count; $id++) {
            $stores[$id] = 'Store ' . $id;
        }

        foreach (['0' => [], '1' => ['option_store_label', 'url_name_0', 'url_name_1', 'regenerate']] as $scope => $fields) {
            $meta = $this->form('color', $this->colour(), (string) $scope, $stores)->getMeta();
            self::assertSame($fields, $this->recordFields($meta), $count . ' store views, scope ' . $scope);
            self::assertCount($scope === 0 ? 2 : 4, $meta['labels']['children']);
        }
    }

    /**
     * @return array<string, array{int}>
     */
    public static function storeCounts(): array
    {
        return ['one' => [1], 'four' => [4], 'twenty' => [20]];
    }

    public function testMatchRangesAndTreeLevelsShowOnlyWhereTheyFit(): void
    {
        $colour = $this->form('color', $this->colour(), null, [self::DE => 'Deutsch'])->getMeta();
        $price = $this->form('price', (new Defaults())->forBuiltin('price'), null, [self::DE => 'Deutsch'])->getMeta();
        $category = $this->form('category', (new Defaults())->forBuiltin('category'), null, [self::DE => 'Deutsch'])->getMeta();
        $weight = $this->form('weight', (new Defaults())->forAttribute('weight', 80, 'decimal', null, 10), null, [self::DE => 'Deutsch'])->getMeta();

        $shown = static fn (array $meta, string $fieldset, string $field): bool =>
            ($meta[$fieldset]['children'][$field]['arguments']['data']['config']['visible'] ?? true) !== false;
        self::assertTrue($shown($colour, 'behaves', 'logic'));
        self::assertFalse($shown($colour, 'looks', 'ranges'));
        self::assertFalse($shown($colour, 'looks', 'tree_depth'));
        self::assertTrue($shown($price, 'looks', 'ranges'));
        self::assertFalse($shown($price, 'behaves', 'logic'));
        self::assertTrue($shown($weight, 'looks', 'ranges'));
        self::assertFalse($shown($weight, 'behaves', 'logic'));
        self::assertTrue($shown($category, 'looks', 'tree_depth'));
        self::assertFalse($shown($category, 'looks', 'ranges'));
    }

    public function testAStoreViewEditsItsOwnTooltip(): void
    {
        $form = $this->form('color', $this->colour(), (string) self::DE, [self::DE => 'Deutsch']);

        $meta = $form->getMeta();
        $data = $form->getData()['color'];

        self::assertTrue($this->config($meta, 'labels', 'tooltip_0')['disabled']);
        self::assertSame('textarea', $this->config($meta, 'labels', 'tooltip_2')['formElement']);
        self::assertSame('Tooltip in "Deutsch"', $this->config($meta, 'labels', 'tooltip_2')['label']);
        self::assertSame('Pick one or more', $data['tooltip_0']);
        self::assertNull($data['tooltip_2']);
        self::assertSame(['logic' => 'or', 'ranges' => '', 'tree_depth' => '3'], array_intersect_key(
            $data,
            ['logic' => 1, 'ranges' => 1, 'tree_depth' => 1]
        ));
    }

    public function testAnUnknownStoreViewShowsAllStoreViews(): void
    {
        $meta = $this->form('color', $this->colour(), '99', [self::DE => 'Deutsch'])->getMeta();

        self::assertSame(['tooltip_0', 'label_0'], array_keys($meta['labels']['children']));
        self::assertArrayNotHasKey('disabled', $this->config($meta, 'looks', 'display_mode'));
    }

    public function testSingleStoreModeShowsNoScopeLabels(): void
    {
        $meta = $this->form('color', $this->colour(), null, [1 => 'Default'], true)->getMeta();

        self::assertArrayNotHasKey('scopeLabel', $this->config($meta, 'behaves', 'visible_options'));
        self::assertArrayNotHasKey('scopeLabel', $this->config($meta, 'labels', 'label_0'));
    }

    public function testABuiltinHasNoUrlNamesAndTheMetaWorksBeforeTheFormAddsItsFilter(): void
    {
        $form = $this->form('price', (new Defaults())->forBuiltin('price')->with(['attribute_id' => 77]), null, [self::DE => 'Deutsch']);

        $meta = $form->getMeta();

        self::assertFalse($meta['seo']['children']['option_urls']['arguments']['data']['config']['visible']);
        self::assertFalse($meta['seo']['children']['url_name']['arguments']['data']['config']['visible']);
        self::assertSame(
            ['slider', 'from_to', 'ranges'],
            array_column($meta['looks']['children']['display_mode']['arguments']['data']['config']['options'], 'value')
        );
        self::assertArrayNotHasKey('url_name', $form->getData()['price']);
    }

    public function testAFilterCanWaitForAnyOtherFilterAndItsValues(): void
    {
        $defaults = new Defaults();
        $all = [
            'color' => $this->colour()->setDependsOn('rating')->setDependsValues(['4']),
            'size' => $defaults->forAttribute('size', 144, 'select', 'text', 20),
            'rating' => $defaults->forBuiltin('rating'),
            'weight' => $defaults->forAttribute('weight', 80, 'decimal', null, 30),
            'price' => $defaults->forBuiltin('price')->setDisplayMode('ranges')->setRanges('0-50,50-'),
        ];
        $form = $this->form('color', $all['color'], null, [self::DE => 'Deutsch'], false, $all);
        $form->addFilter((new \Magento\Framework\Api\Filter())->setField('code')->setValue('color'));

        $meta = $form->getMeta();
        $parents = array_column($this->config($meta, 'where', 'depends_on')['options'], 'value');
        self::assertSame(['', 'size', 'rating', 'weight', 'price'], $parents, 'every other filter, never itself');
        $values = $this->config($meta, 'where', 'depends_values')['optionsByParent'];
        self::assertSame([['value' => '49', 'label' => 'Black']], $values['size']);
        self::assertSame(['4', '3', '2', '1'], array_column($values['rating'], 'value'));
        self::assertSame([], $values['weight'], 'a slider: any pick');
        self::assertSame(['0-50', '50-'], array_column($values['price'], 'value'));
        self::assertSame('[GLOBAL]', $this->config($meta, 'where', 'depends_on')['scopeLabel']);
        $data = $form->getData()['color'];
        self::assertSame(['rating', ['4']], [$data['depends_on'], $data['depends_values']]);
    }

    private function colour(): FilterConfig
    {
        return (new Defaults())->forAttribute('color', 93, 'select', 'visual', 10);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function config(array $meta, string $fieldset, string $field): array
    {
        return $meta[$fieldset]['children'][$field]['arguments']['data']['config'] ?? [];
    }

    /**
     * Option table columns added by the meta, in their order.
     *
     * @param array<string, mixed> $meta
     * @return string[]
     */
    private function recordFields(array $meta): array
    {
        $children = $meta['seo']['children']['option_urls']['children']['record']['children'] ?? [];

        return array_keys($children);
    }

    /**
     * @param array<int, string> $storeNames
     * @param array<string, FilterConfig> $all every filter by key (the filters to wait for)
     */
    private function form(string $key, FilterConfig $config, ?string $store, array $storeNames, bool $single = false, array $all = []): FilterForm
    {
        $repository = $this->createStub(FilterConfigRepositoryInterface::class);
        $repository->method('getByCode')->willReturn($config);
        $provider = $this->createStub(FilterConfigProvider::class);
        $provider->method('keyOf')->willReturn($key);
        $provider->method('getForStore')->willReturn($all);
        $attributeSlugs = $this->createStub(AttributeSlugScope::class);
        $attributeSlugs->method('own')->willReturnMap([[93, 0, 'color'], [93, self::DE, 'farbe']]);
        $configStorage = $this->createStub(FilterConfigStorage::class);
        $configStorage->method('labelOverrides')->willReturn([0 => 'Colour', self::DE => 'Farbe']);
        $configStorage->method('optionCount')->willReturn(12);
        $configStorage->method('tooltipOverrides')->willReturn([0 => 'Pick one or more']);
        $slugStorage = $this->createStub(SlugStorage::class);
        $slugStorage->method('optionLabels')->willReturn(['admin' => [49 => 'Black'], 'stores' => [self::DE => [49 => 'Schwarz']]]);
        $slugStorage->method('optionSlugRows')->willReturn([0 => [49 => 'black'], self::DE => [49 => 'schwarz']]);
        $stores = [];
        foreach ($storeNames as $id => $name) {
            $model = $this->createStub(StoreInterface::class);
            $model->method('getId')->willReturn($id);
            $model->method('getName')->willReturn($name);
            $stores[$id] = $model;
        }
        $manager = $this->createStub(StoreManagerInterface::class);
        $manager->method('getStores')->willReturn($stores);
        $manager->method('isSingleStoreMode')->willReturn($single);
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(
            static fn (string $name) => match ($name) {
                'store' => $store,
                'code' => $key,
                default => null,
            }
        );

        return new FilterForm(
            'openfilter_filter_form_data_source',
            'key',
            'code',
            $repository,
            $provider,
            $attributeSlugs,
            $configStorage,
            $slugStorage,
            new Validator(new RangeList(new Range())),
            new DisplayMode(),
            new FormScope($manager),
            $request,
            new Defaults(),
            $this->createStub(OptionImageForm::class)
        );
    }
}
