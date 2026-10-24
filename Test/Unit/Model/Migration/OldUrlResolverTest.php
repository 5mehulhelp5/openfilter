<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Migration;

use Iranimij\OpenFilter\Api\FilterStateInterface;
use Iranimij\OpenFilter\Api\UrlCodecInterface;
use Iranimij\OpenFilter\Model\Migration\OldUrlParser;
use Iranimij\OpenFilter\Model\Migration\OldUrlResolver;
use Iranimij\OpenFilter\Model\Migration\Vocabulary;
use Iranimij\OpenFilter\Model\Url\FilterState;
use Iranimij\OpenFilter\Model\Url\PathUrl;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
class OldUrlResolverTest extends TestCase
{
    public function testTheLongestCategoryPathWithOneLookupThenTheFilters(): void
    {
        $finder = $this->createMock(UrlFinderInterface::class);
        $finder->expects(self::once())->method('findAllByData')->with(self::callback(
            static fn (array $data): bool => $data[UrlRewrite::REQUEST_PATH] === ['women/tops/shopby.html', 'women/tops.html', 'women.html']
        ))->willReturn([$this->rewrite('women.html'), $this->rewrite('women/tops.html')]);

        self::assertSame(
            'https://shop/women/tops.html|{"color":["black","blue"]}|{"product_list_order":"price","farbe":"x"}',
            $this->resolver($finder)->resolve('/women/tops/shopby/black-blue.html', ['product_list_order' => 'price', 'farbe' => 'x'], 1),
            'other parameters stay'
        );
    }

    public function testFilterQueryParametersJoinThePathPicks(): void
    {
        $finder = $this->createStub(UrlFinderInterface::class);
        $finder->method('findAllByData')->willReturn([$this->rewrite('women.html')]);

        self::assertSame(
            'https://shop/women.html|{"color":["red"],"price":["10-20"]}|[]',
            $this->resolver($finder)->resolve('women/red.html', ['price' => '10-20'], 1)
        );
        self::assertSame('PATH', $this->resolver($finder, true)->resolve('women/red.html', [], 1), 'path format');
    }

    public function testNotAnOldFilterUrl(): void
    {
        $none = $this->createStub(UrlFinderInterface::class);
        $none->method('findAllByData')->willReturn([]);
        $women = $this->createStub(UrlFinderInterface::class);
        $women->method('findAllByData')->willReturn([$this->rewrite('women.html')]);

        self::assertNull($this->resolver($none)->resolve('nothing/red.html', [], 1), 'no category');
        self::assertNull($this->resolver($women)->resolve('women.html', [], 1), 'one segment');
        self::assertNull($this->resolver($women)->resolve('women/purple.html', [], 1), 'unknown words');
        self::assertNull($this->resolver($women)->resolve(str_repeat('a/', 12) . 'red.html', [], 1), 'too deep');
    }

    private function rewrite(string $path): UrlRewrite
    {
        $rewrite = $this->createStub(UrlRewrite::class);
        $rewrite->method('getRequestPath')->willReturn($path);

        return $rewrite;
    }

    private function resolver(UrlFinderInterface $finder, bool $paths = false): OldUrlResolver
    {
        $scope = $this->createStub(ScopeConfigInterface::class);
        $scope->method('getValue')->willReturnMap([
            [PathUrl::SUFFIX_PATH, 'store', 1, '.html'],
            [OldUrlResolver::XML_PATH_FILTER_KEYS, 'store', 1, ' shopby, '],
        ]);
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop/');
        $stores = $this->createStub(StoreManagerInterface::class);
        $stores->method('getStore')->willReturn($store);
        $vocabulary = $this->createStub(Vocabulary::class);
        $vocabulary->method('forStore')->willReturn([
            'params' => ['color' => 'color'],
            'attributes' => ['color' => 'color'],
            'options' => ['black' => ['color' => 'black'], 'blue' => ['color' => 'blue'], 'red' => ['color' => 'red']],
        ]);
        $codec = $this->createStub(UrlCodecInterface::class);
        $codec->method('getParameterNames')->willReturn(['color' => 'color', 'price' => 'price']);
        $codec->method('decode')->willReturnCallback(static fn (array $params): FilterState => new FilterState(array_map(
            static fn (string $value): array => explode(',', $value),
            $params
        )));
        $codec->method('urlFor')->willReturnCallback(
            static fn (FilterStateInterface $state, string $base, array $keep): string =>
                $base . '|' . json_encode($state->getValues()) . '|' . json_encode($keep)
        );
        $pathUrl = $this->createStub(PathUrl::class);
        $pathUrl->method('isActive')->willReturn($paths);
        $pathUrl->method('build')->willReturn('PATH');

        return new OldUrlResolver($scope, $stores, $finder, $vocabulary, new OldUrlParser(), $codec, $pathUrl);
    }
}
