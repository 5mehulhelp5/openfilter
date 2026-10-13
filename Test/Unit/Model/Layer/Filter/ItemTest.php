<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Layer\Filter;

use Iranimij\OpenFilter\Api\FilterStateInterface;
use Iranimij\OpenFilter\Model\Layer\Filter\Item;
use Iranimij\OpenFilter\Model\Layer\Filter\OpenFilterInterface;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Url\FilterState;
use Magento\Framework\DataObject;
use Magento\Framework\UrlInterface;
use Magento\Theme\Block\Html\Pager;
use PHPUnit\Framework\TestCase;

class ItemTest extends TestCase
{
    public function testOpenFilterItemsUseTheContextLinks(): void
    {
        $item = $this->item(true, new FilterState(['color' => ['50']]));
        $item->setData('value', '50');

        self::assertSame('toggle:color:50', $item->getUrl());
        self::assertSame('remove:color:50', $item->getRemoveUrl());
        self::assertSame('state:', $item->getClearLinkUrl());
        self::assertTrue($item->isSelected());
        $item->setData('value', '49');
        self::assertFalse($item->isSelected());
    }

    public function testCoreFiltersGetTheirParameterOnTheCanonicalState(): void
    {
        $context = $this->createStub(LayerContext::class);
        $context->method('isActive')->willReturn(true);
        $context->method('state')->willReturn(new FilterState(['color' => ['50']]));
        $context->method('urlFor')->willReturnCallback(
            static fn (FilterStateInterface $s, array $override = []): string =>
                'color=' . implode(',', $s->get('color')) . '|' . json_encode($override)
        );
        $item = new Item($this->createStub(UrlInterface::class), $this->createStub(Pager::class), $context);
        $item->setData('filter', new DataObject(['request_var' => 'cat', 'reset_value' => '3']))->setData('value', '12');

        self::assertSame('color=50|{"cat":"12"}', $item->getUrl());
        self::assertSame('color=50|{"cat":"3"}', $item->getRemoveUrl());
        $item->setData('value', [10, 20]);
        self::assertSame('color=50|{"cat":"10-20"}', $item->getUrl());
    }

    public function testCoreLinksWhenOpenFilterIsOff(): void
    {
        $url = $this->createMock(UrlInterface::class);
        $url->expects(self::exactly(2))->method('getUrl')->willReturn('core');
        $item = new Item($url, $this->createStub(Pager::class), $this->createStub(LayerContext::class));
        $item->setData('filter', new DataObject(['request_var' => 'color']))->setData('value', '5');

        self::assertSame('core', $item->getUrl());
        self::assertSame('core', $item->getRemoveUrl());
        self::assertFalse($item->getClearLinkUrl(), 'no clear link text on the core filter');
        self::assertFalse($item->isSelected());
    }

    private function item(bool $multiselect, FilterStateInterface $state): Item
    {
        $context = $this->createStub(LayerContext::class);
        $context->method('state')->willReturn($state);
        $context->method('urlFor')->willReturnCallback(
            static fn (FilterStateInterface $s): string => 'state:' . implode(',', $s->get('color'))
        );
        $context->method('toggleUrl')->willReturnCallback(static fn (string $k, string $v): string => "toggle:$k:$v");
        $context->method('removeUrl')->willReturnCallback(static fn (string $k, string $v): string => "remove:$k:$v");
        $filter = new class ($multiselect) extends DataObject implements OpenFilterInterface {
            public function __construct(private readonly bool $multi)
            {
                parent::__construct();
            }

            public function getOpenFilterKey(): ?string
            {
                return 'color';
            }

            public function isMultiselect(): bool
            {
                return $this->multi;
            }
        };
        $item = new Item($this->createStub(UrlInterface::class), $this->createStub(Pager::class), $context);
        $item->setData('filter', $filter);

        return $item;
    }
}
