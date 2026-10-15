<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\GraphQl;

use Iranimij\OpenFilter\Api\Data\FilterConfigInterface as Filter;
use Iranimij\OpenFilter\Model\Config\CategoryRule;
use Iranimij\OpenFilter\Model\Config\Defaults;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\GraphQl\AggregationsBuilder;
use Iranimij\OpenFilter\Model\GraphQl\RangeFilters;
use Iranimij\OpenFilter\Model\Layer\CategoryTree;
use Iranimij\OpenFilter\Model\Url\SlugProvider;
use Iranimij\OpenFilter\ViewModel\Swatch;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;
use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\AggregationValueInterface;
use Magento\Framework\Api\Search\BucketInterface;
use Magento\Framework\GraphQl\Query\Uid;
use PHPUnit\Framework\TestCase;

class AggregationsBuilderTest extends TestCase
{
    /** @var array<string, Filter> */
    private array $filters = [];

    protected function setUp(): void
    {
        $defaults = new Defaults();
        $this->filters = [
            'category' => $defaults->forBuiltin('category')->with(['position' => 5]),
            'rating' => $defaults->forBuiltin('rating')->with(['position' => 1, 'active' => true]),
            'color' => $defaults->forAttribute('color', 93, 'select', 'visual', 30)->with(['tooltip' => 'Pick a colour']),
            'size' => $defaults->forAttribute('size', 144, 'select', 'text', 20)->setSortBy(Filter::SORT_COUNT),
            'price' => $defaults->forBuiltin('price')->with(['position' => 40]),
        ];
    }

    public function testFiltersInTheMerchantsOrderWithOptionsCountsAndPicks(): void
    {
        $result = $this->builder()->build($this->aggregation(), $this->engine(), [
            'category_id' => ['eq' => '20'],
            'color' => ['in' => ['2']],
        ], 1);

        self::assertSame(['category', 'size', 'color', 'price'], array_column($result, 'code'), 'rating (session) left out');
        self::assertSame([0, 1, 2, 3], array_column($result, 'position'));
        [$category, $size, $colour, $price] = $result;
        self::assertSame('category_id', $category['filter_key']);
        self::assertSame('cat', $category['url_param']);
        self::assertSame([['21', 'Tops', 1, 8], ['23', 'Tees', 2, 3]], array_map(
            static fn (array $o): array => [$o['value'], $o['label'], $o['depth'], $o['count']],
            $category['options']
        ), 'tree of two levels, categories without products left out');
        self::assertSame(['L', 'S'], array_column($size['options'], 'label'), 'by count');
        self::assertSame('Colour', $colour['label']);
        self::assertSame('farbe', $colour['url_param']);
        self::assertSame('Pick a colour', $colour['tooltip']);
        self::assertSame([
            ['1', 'Red ®', 'red', 4, false, ['type' => 'color', 'value' => '#f00']],
            ['2', 'Blue', '2', 0, true, ['type' => 'color', 'value' => '#00f']],
        ], array_map(static fn (array $o): array => [$o['value'], $o['label'], $o['url_value'], $o['count'], $o['selected'], $o['swatch']], $colour['options']), 'zero counts only when picked; no URL name = the value');
        self::assertSame(['min' => 10.0, 'max' => 56.0, 'from' => null, 'to' => null], $price['range']);
        self::assertSame('Price', $price['label']);
    }

    public function testWhereItShowsAndHiddenSingleOptions(): void
    {
        $this->filters['color']->setCategoryMode(Filter::CATEGORIES_EXCEPT)->setCategoryIds([20]);
        $this->filters['size']->setHideSingleOption(true);
        $this->filters['category']->setActive(false);

        $result = $this->builder()->build($this->aggregation(['167' => 1]), [], ['category_id' => ['eq' => '21']], 1);

        self::assertSame([], array_column($result, 'code'), 'colour hidden below Women, one size only, no price stats');
    }

    public function testRangesListAndSliderPicks(): void
    {
        $this->filters['price']->setDisplayMode(Filter::DISPLAY_RANGES);
        $ranges = $this->createStub(RangeFilters::class);
        $ranges->method('options')->willReturn([['from' => 10.0, 'to' => 30.0, 'value' => '10-30', 'count' => 3, 'selected' => true]]);

        $result = $this->builder(ranges: $ranges)->build($this->aggregation(), $this->engine(), ['price' => ['from' => '10', 'to' => '30']], 1);
        $price = $result[array_search('price', array_column($result, 'code'), true)];

        self::assertSame([['value' => '10-30', 'label' => '10-30', 'url_value' => '10-30', 'count' => 3, 'selected' => true, 'from' => 10.0, 'to' => 30.0]], $price['options']);
    }

    public function testSliderRangeReportsThePick(): void
    {
        $result = $this->builder()->build($this->aggregation(), $this->engine(), ['price' => ['from' => '20', 'to' => 'x']], 1);

        self::assertSame(['min' => 10.0, 'max' => 56.0, 'from' => 20.0, 'to' => null], end($result)['range']);
    }

    public function testNothingWithoutAnAggregation(): void
    {
        self::assertSame([], $this->builder()->build(null, [], [], 1));
    }

    public function testTheStoreRootWithoutACategoryAndByUid(): void
    {
        $byUid = $this->builder()->build($this->aggregation(), [], ['category_uid' => ['eq' => base64_encode('20')]], 1);
        $root = $this->builder()->build($this->aggregation(), [], [], 1);

        self::assertSame('Tops', $byUid[0]['options'][0]['label']);
        self::assertSame(['Women'], array_column($root[0]['options'], 'label'), 'store root, one level');
    }

    /**
     * @return array<string, mixed>
     */
    private function engine(): array
    {
        return ['price_bucket' => ['count' => 28, 'min' => 10, 'max' => 56]];
    }

    /**
     * @param array<int|string, int>|null $sizes
     */
    private function aggregation(?array $sizes = null): AggregationInterface
    {
        $buckets = [
            'color_bucket' => ['1' => 4, '3' => 0],
            'size_bucket' => $sizes ?? ['167' => 2, '169' => 5],
            'category_bucket' => ['20' => 11, '21' => 8, '23' => 3, '22' => 0],
        ];
        $aggregation = $this->createStub(AggregationInterface::class);
        $aggregation->method('getBucket')->willReturnCallback(function (string $name) use ($buckets): ?BucketInterface {
            if (!isset($buckets[$name])) {
                return null;
            }
            $values = [];
            foreach ($buckets[$name] as $value => $count) {
                $item = $this->createStub(AggregationValueInterface::class);
                $item->method('getValue')->willReturn((string) $value);
                $item->method('getMetrics')->willReturn(['value' => (string) $value, 'count' => $count]);
                $values[] = $item;
            }
            $bucket = $this->createStub(BucketInterface::class);
            $bucket->method('getValues')->willReturn($values);

            return $bucket;
        });

        return $aggregation;
    }

    private function builder(?RangeFilters $ranges = null): AggregationsBuilder
    {
        $provider = $this->createStub(FilterConfigProvider::class);
        $provider->method('getActive')->willReturnCallback(fn (): array => array_filter($this->filters, static fn (Filter $f): bool => $f->isActive()));
        $tree = $this->createStub(CategoryTree::class);
        $nodes = [2 => ['name' => '', 'parent' => 1], 20 => ['name' => 'Women', 'parent' => 2], 21 => ['name' => 'Tops', 'parent' => 20], 22 => ['name' => 'Bottoms', 'parent' => 20], 23 => ['name' => 'Tees', 'parent' => 21]];
        $tree->method('forStore')->willReturn($nodes);
        $tree->method('pathIds')->willReturnCallback(static fn (int $id): array => match ($id) {
            20 => [2, 20], 21 => [2, 20, 21], default => [2],
        });
        $tree->method('below')->willReturnCallback(static fn (int $root, int $depth): array => match ($root) {
            20 => $depth > 1
                ? [['id' => 21, 'name' => 'Tops', 'depth' => 1], ['id' => 23, 'name' => 'Tees', 'depth' => 2], ['id' => 22, 'name' => 'Bottoms', 'depth' => 1]]
                : [['id' => 21, 'name' => 'Tops', 'depth' => 1], ['id' => 22, 'name' => 'Bottoms', 'depth' => 1]],
            21 => [['id' => 23, 'name' => 'Tees', 'depth' => 1]],
            default => [['id' => 20, 'name' => 'Women', 'depth' => 1]],
        });
        $this->filters['category']->setDisplayMode(Filter::DISPLAY_TREE)->setTreeDepth(2);
        $slugs = $this->createStub(SlugProvider::class);
        $slugs->method('forStore')->willReturn(['attributes' => [93 => 'farbe'], 'options' => [93 => [1 => 'red'], 144 => [167 => 's', 169 => 'l']]]);
        $eav = $this->createStub(EavConfig::class);
        $eav->method('getAttribute')->willReturnCallback(function (string $entity, string $code): Attribute {
            $options = match ($code) {
                'color' => [['value' => '1', 'label' => 'Red &reg;'], ['value' => '2', 'label' => 'Blue'], ['value' => '3', 'label' => 'Green']],
                'size' => [['value' => '167', 'label' => 'S'], ['value' => '169', 'label' => 'L']],
                default => [],
            };
            $source = $this->createStub(AbstractSource::class);
            $options = array_merge([['value' => '', 'label' => ' ']], $options);
            $source->method('getAllOptions')->willReturn($options);
            $attribute = $this->createStub(Attribute::class);
            $attribute->method('getId')->willReturn($options === [] ? null : 1);
            $attribute->method('getSource')->willReturn($source);
            $attribute->method('getStoreLabel')->willReturn($code === 'color' ? 'Colour' : 'Size');

            return $attribute;
        });
        $swatches = $this->createStub(Swatch::class);
        $swatches->method('forOptions')->willReturn(['1' => ['type' => 'color', 'value' => '#f00'], '2' => ['type' => 'color', 'value' => '#00f']]);

        return new AggregationsBuilder(
            $provider,
            new CategoryRule(),
            $tree,
            $slugs,
            $eav,
            $ranges ?? $this->createStub(RangeFilters::class),
            $swatches,
            new Uid()
        );
    }
}
