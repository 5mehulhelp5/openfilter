<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Ui\DataProvider;

use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Api\SlugRepositoryInterface;
use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Config\Source\DisplayMode;
use Iranimij\OpenFilter\Model\Config\Validator;
use Iranimij\OpenFilter\Model\ResourceModel\FilterConfigStorage;
use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Iranimij\OpenFilter\Ui\DataProvider\FilterForm;
use Magento\Framework\Api\Filter;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class FilterFormTest extends TestCase
{
    public function testAnAttributeGetsFittingTypesStoreFieldsAndItsOptionNames(): void
    {
        $form = $this->form('color', (new Defaults())->forAttribute('color', 93, 'select', 'visual', 10));
        $form->addFilter(new Filter(['field' => 'key', 'value' => 'color']));

        $meta = $form->getMeta();
        $data = $form->getData()['color'];

        self::assertSame(
            ['list', 'swatch', 'dropdown'],
            array_column($meta['looks']['children']['display_mode']['arguments']['data']['config']['options'], 'value')
        );
        self::assertArrayHasKey('label_2', $meta['labels']['children']);
        self::assertSame('label_2', $meta['labels']['children']['label_2']['arguments']['data']['config']['dataScope']);
        self::assertArrayHasKey('url_name_2', $meta['seo']['children']['option_urls']['children']['record']['children']);
        self::assertSame('1', $data['multiselect']);
        self::assertSame('auto', $data['expanded']);
        self::assertSame('Farbe', $data['label_2']);
        self::assertSame('color', $data['url_name']);
        self::assertSame(
            [['option_id' => 49, 'option_label' => 'Black', 'url_name_0' => 'black', 'regenerate' => '0', 'url_name_2' => 'schwarz']],
            $data['option_urls']
        );
    }

    public function testABuiltinHasNoUrlNamesAndTheMetaWorksBeforeTheFormAddsItsFilter(): void
    {
        $form = $this->form('price', (new Defaults())->forBuiltin('price')->with(['attribute_id' => 77]));

        $meta = $form->getMeta();

        self::assertFalse($meta['seo']['children']['option_urls']['arguments']['data']['config']['visible']);
        self::assertFalse($meta['seo']['children']['url_name']['arguments']['data']['config']['visible']);
        self::assertSame(
            ['slider', 'from_to'],
            array_column($meta['looks']['children']['display_mode']['arguments']['data']['config']['options'], 'value')
        );
        self::assertArrayNotHasKey('url_name', $form->getData()['price']);
    }

    private function form(string $key, \Iranimij\OpenFilter\Model\Config\FilterConfig $config): FilterForm
    {
        $repository = $this->createStub(FilterConfigRepositoryInterface::class);
        $repository->method('getByCode')->willReturn($config);
        $provider = $this->createStub(FilterConfigProvider::class);
        $provider->method('keyOf')->willReturn($key);
        $slugs = $this->createStub(SlugRepositoryInterface::class);
        $slugs->method('getAttributeSlug')->willReturn('color');
        $configStorage = $this->createStub(FilterConfigStorage::class);
        $configStorage->method('labelOverrides')->willReturn([2 => 'Farbe']);
        $configStorage->method('optionCount')->willReturn(12);
        $slugStorage = $this->createStub(SlugStorage::class);
        $slugStorage->method('optionLabels')->willReturn(['admin' => [49 => 'Black'], 'stores' => []]);
        $slugStorage->method('optionSlugRows')->willReturn([0 => [49 => 'black'], 2 => [49 => 'schwarz']]);
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(2);
        $store->method('getName')->willReturn('Deutsch');
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStores')->willReturn([2 => $store]);
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn($key);

        return new FilterForm(
            'openfilter_filter_form_data_source',
            'key',
            'code',
            $repository,
            $provider,
            $slugs,
            $configStorage,
            $slugStorage,
            new Validator(),
            new DisplayMode(),
            $stores,
            $request
        );
    }
}
