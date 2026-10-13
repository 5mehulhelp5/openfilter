<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\ViewModel;

use Iranimij\OpenFilter\Api\FilterStateInterface;
use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Iranimij\OpenFilter\Model\Url\FilterState;
use Iranimij\OpenFilter\ViewModel\State;
use Magento\Catalog\Model\Layer\Filter\AbstractFilter;
use Magento\Catalog\Model\Layer\Filter\Item;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class StateTest extends TestCase
{
    public function testChipsNameTheFilterAndValueAndLinkToTheirRemoval(): void
    {
        $chips = $this->state(new FilterState([]))->chips([
            $this->item('Colour', 'Blue', '/c?size=m', 'color'),
            $this->item('Price', '€20.00 - €50.00', '/c?color=blue', 'price'),
        ]);

        self::assertSame(['Colour: Blue', 'Price: €20.00 - €50.00'], array_map(static fn ($c) => $c->getText(), $chips));
        self::assertSame('Colour', $chips[0]->getName());
        self::assertSame('Blue', $chips[0]->getValue());
        self::assertSame('/c?size=m', $chips[0]->getRemoveUrl());
    }

    public function testYesNoFiltersWhoseValueIsTheirNameShowTheNameOnce(): void
    {
        $chips = $this->state(new FilterState([]))->chips([$this->item('On sale', 'On sale', '/c', 'sale')]);

        self::assertSame('On sale', $chips[0]->getText());
    }

    public function testValuesLoseMarkup(): void
    {
        $chips = $this->state(new FilterState([]))->chips([$this->item('Category', '<b>Jackets</b>', '/c', 'cat')]);

        self::assertSame('Category: Jackets', $chips[0]->getText());
    }

    public function testClearAllDropsEveryOpenFilterValueAndTheActiveCoreFilters(): void
    {
        $context = $this->createMock(LayerContext::class);
        $context->method('state')->willReturn(new FilterState(['color' => ['49'], 'price' => ['20-50']]));
        $context->expects(self::once())->method('urlFor')
            ->with(self::callback(static fn (FilterStateInterface $s): bool => $s->count() === 0), ['cat' => null])
            ->willReturn('/women/tops-women.html?product_list_order=name');

        $url = (new State($context))->clearUrl([$this->item('Category', 'Jackets', '/c', 'cat')]);

        self::assertSame('/women/tops-women.html?product_list_order=name', $url);
    }

    private function state(FilterStateInterface $state): State
    {
        $context = $this->createStub(LayerContext::class);
        $context->method('state')->willReturn($state);

        return new State($context);
    }

    private function item(string $name, string $label, string $removeUrl, string $requestVar): Item
    {
        $filter = $this->createStub(AbstractFilter::class);
        $filter->method('getName')->willReturn($name);
        $filter->method('getRequestVar')->willReturn($requestVar);
        $item = $this->getMockBuilder(Item::class)->disableOriginalConstructor()
            ->onlyMethods(['getRemoveUrl', 'getFilter'])->getMock();
        $item->setData(['label' => $label]);
        $item->method('getRemoveUrl')->willReturn($removeUrl);
        $item->method('getFilter')->willReturn($filter);

        return $item;
    }
}
