<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Url;

use Iranimij\OpenFilter\Api\UrlCodecInterface;
use Iranimij\OpenFilter\Model\Url\FilterState;
use Iranimij\OpenFilter\Model\Url\FilterStateProvider;
use Iranimij\OpenFilter\Model\Url\Range;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FilterStateAndRangeTest extends TestCase
{
    public function testStateIsImmutableAndDeduplicated(): void
    {
        $state = new FilterState(['color' => ['50', '50', '49'], 'size' => []]);
        $more = $state->with('size', ['168']);
        $less = $more->without('color');

        self::assertSame(['color' => ['50', '49']], $state->getValues());
        self::assertSame(3, $more->count());
        self::assertTrue($more->has('size'));
        self::assertFalse($state->has('size'));
        self::assertSame([], $less->get('color'));
        self::assertSame(['168'], $less->get('size'));
        self::assertFalse($more->with('size', [])->has('size'));
    }

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function ranges(): array
    {
        return [
            'closed' => ['20-80', '20-80'],
            'swapped' => ['80-20', '20-80'],
            'open start' => ['-80', '-80'],
            'open end' => ['20-', '20-'],
            'decimals' => ['19.990-20.5', '19.99-20.5'],
            'spaces' => [' 20 - 80 ', '20-80'],
            'clamped' => ['5-99999999999', '5-1000000000'],
            'both open' => ['-', null],
            'comma decimals' => ['19,99-20', null],
            'negative' => ['--5-10', null],
            'text' => ['cheap', null],
        ];
    }

    /**
     * @dataProvider ranges
     */
    #[DataProvider('ranges')]
    public function testRangeNormalise(string $value, ?string $expected): void
    {
        self::assertSame($expected, (new Range())->normalise($value));
    }

    public function testProviderDecodesTheRequestOnce(): void
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParams')->willReturn(['color' => 'red']);
        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(3);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $codec = $this->createMock(UrlCodecInterface::class);
        $codec->expects(self::exactly(2))->method('decode')->with(['color' => 'red'], 3)
            ->willReturn(new FilterState(['color' => ['50']]));

        $provider = new FilterStateProvider($request, $codec, $storeManager);
        self::assertSame(['50'], $provider->get()->get('color'));
        self::assertSame($provider->get(), $provider->get());
        $provider->set(new FilterState(['size' => ['1']]));
        self::assertSame(['1'], $provider->get()->get('size'));
        $provider->set(null);
        self::assertSame(['50'], $provider->get()->get('color'));
    }
}
