<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model;

use Iranimij\Base\Model\Config\TypedReader;
use Iranimij\OpenFilter\Model\AllProducts;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AllProductsTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function keys(): array
    {
        return [
            'plain' => ['all-products', 'all-products'],
            'with a suffix and a folder' => ['/Shop/All.html/', 'shop/all.html'],
            'spaces trimmed' => ['  everything ', 'everything'],
            'empty' => ['', 'all-products'],
            'a query' => ['all?x=1', 'all-products'],
            'two slashes' => ['shop//all', 'all-products'],
        ];
    }

    /**
     * @dataProvider keys
     */
    #[DataProvider('keys')]
    public function testUrlKeysAreCleanedOrFallBack(string $setting, string $key): void
    {
        self::assertSame($key, $this->page(['openfilter/all_products/url_key' => $setting])->urlKey(1));
    }

    public function testTitleUrlAndSwitch(): void
    {
        $page = $this->page(['openfilter/all_products/title' => ' Shop everything ', 'openfilter/all_products/url_key' => 'all']);

        self::assertSame('Shop everything', $page->title(1));
        self::assertSame('All products', $this->page([])->title(1), 'empty: the default');
        self::assertSame('https://shop.test/de/all', $page->url(1));
        self::assertFalse($page->isEnabled(1));
        self::assertTrue($this->page(['openfilter/all_products/enabled' => true])->isEnabled(1));
    }

    /**
     * @param array<string, string|bool> $values
     */
    private function page(array $values): AllProducts
    {
        $reader = $this->createStub(TypedReader::class);
        $reader->method('getString')->willReturnCallback(static fn (string $path): string => (string) ($values[$path] ?? ''));
        $reader->method('getBool')->willReturnCallback(static fn (string $path): bool => (bool) ($values[$path] ?? false));
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getBaseUrl')->willReturn('https://shop.test/de/');
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);

        return new AllProducts($reader, $stores);
    }
}
