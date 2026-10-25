<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Landing;

use Iranimij\OpenFilter\Api\UrlCodecInterface;
use Iranimij\OpenFilter\Model\Landing\UrlParser;
use Iranimij\OpenFilter\Model\Url\FilterState;
use Iranimij\OpenFilter\Model\Url\PathUrl;
use Magento\Framework\Exception\InputException;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class UrlParserTest extends TestCase
{
    public function testAQueryAddressGivesItsCategoryAndFilters(): void
    {
        self::assertSame(
            ['category_id' => 21, 'filters' => ['color' => ['49']]],
            $this->parser()->parse(' https://shop.test/de/women/tops-women.html?color=black&p=2 ', 1)
        );
        self::assertSame(21, $this->parser()->parse('/women/tops-women.html?color=black', 1)['category_id'], 'no host');
        self::assertSame(12, $this->parser()->parse('/index.php/catalog/category/view/id/12?color=black', 1)['category_id']);
    }

    public function testAPathAddressIsReadWhenPathUrlsAreOn(): void
    {
        self::assertSame(
            ['category_id' => 21, 'filters' => ['color' => ['49']]],
            $this->parser(true)->parse('https://shop.test/de/women/tops-women/color-black.html', 1)
        );
    }

    public function testAddressesWithoutACategoryOrFiltersAreRefused(): void
    {
        foreach (['https://shop.test/de/nothing.html?color=black' => 'not the address of a category', 'https://shop.test/de/women/tops-women.html' => 'has no filters'] as $url => $message) {
            try {
                $this->parser()->parse($url, 1);
                self::fail($url);
            } catch (InputException $e) {
                self::assertStringContainsString($message, $e->getMessage());
            }
        }
    }

    private function parser(bool $paths = false): UrlParser
    {
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.test/de/');
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);
        $rewrite = $this->createStub(UrlRewrite::class);
        $rewrite->method('getEntityId')->willReturn(21);
        $finder = $this->createStub(UrlFinderInterface::class);
        $finder->method('findOneByData')->willReturnCallback(
            static fn (array $data): ?UrlRewrite => $data[UrlRewrite::REQUEST_PATH] === 'women/tops-women.html' ? $rewrite : null
        );
        $path = $this->createStub(PathUrl::class);
        $path->method('isActive')->willReturn($paths);
        $path->method('parse')->willReturnCallback(
            static fn (string $url): ?array => $url === 'women/tops-women/color-black.html' ? ['women/tops-women.html', ['color' => 'black']] : null
        );
        $codec = $this->createStub(UrlCodecInterface::class);
        $codec->method('decode')->willReturnCallback(
            static fn (array $query): FilterState => new FilterState(($query['color'] ?? '') === 'black' ? ['color' => ['49']] : [])
        );

        return new UrlParser($stores, $finder, $path, $codec);
    }
}
