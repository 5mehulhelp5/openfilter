<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Observer\Adminhtml;

use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Api\SlugRepositoryInterface;
use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Config\FilterConfigEditor;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Config\Source\DisplayMode;
use Iranimij\OpenFilter\Model\Config\Validator;
use Iranimij\OpenFilter\Model\ResourceModel\FilterConfigStorage;
use Iranimij\OpenFilter\Observer\Adminhtml\AddAttributeFieldset;
use Iranimij\OpenFilter\Observer\Adminhtml\SaveAttributeFieldset;
use Iranimij\OpenFilter\Model\Url\Range;
use Iranimij\OpenFilter\Model\Url\RangeList;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Framework\Data\Form;
use Magento\Framework\Data\Form\Element\Fieldset;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class AttributeFieldsetTest extends TestCase
{
    public function testTheFieldsetShowsTheFilterSettingsOfAFilterableAttribute(): void
    {
        $config = (new Defaults())->forAttribute('color', 93, 'select', 'visual', 10);
        $fields = [];
        $fieldset = $this->createMock(Fieldset::class);
        $fieldset->method('addField')->willReturnCallback(
            function (string $id, string $type, array $settings) use (&$fields, $fieldset) {
                $fields[$id] = [$type, $settings];

                return $fieldset;
            }
        );
        $form = $this->createMock(Form::class);
        $form->expects(self::once())->method('addFieldset')->with('openfilter_fieldset')->willReturn($fieldset);

        $this->adder($config)->execute($this->observer(['form' => $form, 'attribute' => $this->attribute(93)]));

        self::assertSame('openfilter[display_mode]', $fields['openfilter_display_mode'][1]['name']);
        self::assertSame('swatch', $fields['openfilter_display_mode'][1]['value']);
        self::assertSame(['list', 'swatch', 'dropdown'], array_column($fields['openfilter_display_mode'][1]['values'], 'value'));
        self::assertSame('1', $fields['openfilter_multiselect'][1]['value']);
        self::assertSame('12', $fields['openfilter_search_threshold'][1]['value']);
        self::assertSame('color', $fields['openfilter_url_name'][1]['value']);
        self::assertStringContainsString('openfilter/filter/edit/code/color', $fields['openfilter_more'][1]['text']);
    }

    public function testNoFieldsetForANewOrNonFilterableAttribute(): void
    {
        $form = $this->createMock(Form::class);
        $form->expects(self::never())->method('addFieldset');
        $repository = $this->createStub(FilterConfigRepositoryInterface::class);
        $repository->method('getByAttributeId')->willThrowException(new NoSuchEntityException());
        $adder = $this->adder(null, $repository);

        $adder->execute($this->observer(['form' => $form, 'attribute' => $this->attribute(null)]));
        $adder->execute($this->observer(['form' => $form, 'attribute' => $this->attribute(500)]));
    }

    public function testPostedSettingsAreSavedAndTheCacheIsCleaned(): void
    {
        $config = (new Defaults())->forAttribute('color', 93, 'select', 'visual', 10);
        $repository = $this->createMock(FilterConfigRepositoryInterface::class);
        $repository->method('getByAttributeId')->with(93)->willReturn($config);
        $repository->expects(self::once())->method('save')->with(self::callback(
            static fn (FilterConfig $c): bool => $c->getDisplayMode() === 'list' && $c->getSearchThreshold() === 8
                && !$c->isMultiselect()
        ));
        $slugs = $this->createMock(SlugRepositoryInterface::class);
        $slugs->method('getAttributeSlug')->willReturn('color');
        $slugs->expects(self::once())->method('saveAttributeSlug')->with(93, 0, 'farbe');
        $cache = $this->createMock(CacheType::class);
        $cache->expects(self::once())->method('clean');

        $attribute = $this->attribute(93, ['openfilter' => [
            'display_mode' => 'list', 'multiselect' => '0', 'search_threshold' => '8', 'url_name' => 'farbe',
        ]]);
        $this->saver($repository, $slugs, $cache)->execute($this->observer(['attribute' => $attribute]));
    }

    public function testWithoutTheFieldsetOnlyTheCacheIsCleaned(): void
    {
        $repository = $this->createMock(FilterConfigRepositoryInterface::class);
        $repository->expects(self::never())->method('save');
        $slugs = $this->createMock(SlugRepositoryInterface::class);
        $slugs->expects(self::never())->method('saveAttributeSlug');
        $cache = $this->createMock(CacheType::class);
        $cache->expects(self::once())->method('clean');

        $this->saver($repository, $slugs, $cache)->execute($this->observer(['attribute' => $this->attribute(93)]));
    }

    public function testAnAttributeThatIsNoFilterIsLeftAlone(): void
    {
        $repository = $this->createMock(FilterConfigRepositoryInterface::class);
        $repository->method('getByAttributeId')->willThrowException(new NoSuchEntityException());
        $repository->expects(self::never())->method('save');

        $attribute = $this->attribute(93, ['openfilter' => ['display_mode' => 'list']]);
        $this->saver($repository, $this->createStub(SlugRepositoryInterface::class), $this->createStub(CacheType::class))
            ->execute($this->observer(['attribute' => $attribute]));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function observer(array $data): Observer
    {
        return new Observer(['event' => new Event($data)]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function attribute(?int $id, array $data = []): Attribute
    {
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getId')->willReturn($id);
        $attribute->method('getAttributeId')->willReturn($id);
        $attribute->method('getData')->willReturnCallback(static fn ($key = '') => $data[$key] ?? null);

        return $attribute;
    }

    private function adder(?FilterConfig $config, ?FilterConfigRepositoryInterface $repository = null): AddAttributeFieldset
    {
        if ($repository === null) {
            $repository = $this->createStub(FilterConfigRepositoryInterface::class);
            $repository->method('getByAttributeId')->willReturn($config);
        }
        $slugs = $this->createStub(SlugRepositoryInterface::class);
        $slugs->method('getAttributeSlug')->willReturn('color');
        $storage = $this->createStub(FilterConfigStorage::class);
        $storage->method('optionCount')->willReturn(12);
        $provider = $this->createStub(FilterConfigProvider::class);
        $provider->method('keyOf')->willReturn('color');
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params = []): string => $route . '/code/' . ($params['code'] ?? '')
        );

        return new AddAttributeFieldset($repository, $slugs, $storage, $provider, new Validator(new RangeList(new Range())), new DisplayMode(), $url);
    }

    private function saver(
        FilterConfigRepositoryInterface $repository,
        SlugRepositoryInterface $slugs,
        CacheType $cache
    ): SaveAttributeFieldset {
        return new SaveAttributeFieldset(
            $repository,
            $slugs,
            new FilterConfigEditor(),
            $cache,
            $this->createStub(FilterConfigProvider::class)
        );
    }
}
