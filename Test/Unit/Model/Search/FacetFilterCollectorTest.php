<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Search;

use Iranimij\OpenFilter\Model\GraphQl\RequestState;
use Iranimij\OpenFilter\Model\Search\FacetFilterCollector;
use Magento\Elasticsearch\SearchAdapter\Filter\Builder as FilterBuilder;
use Magento\Framework\Search\Request\Aggregation\DynamicBucket;
use Magento\Framework\Search\Request\Aggregation\TermBucket;
use Magento\Framework\Search\Request\Filter\Range;
use Magento\Framework\Search\Request\Filter\Term;
use Magento\Framework\Search\Request\Query\BoolExpression;
use Magento\Framework\Search\Request\Query\Filter as FilterQuery;
use Magento\Framework\Search\Request\Query\MatchQuery;
use Magento\Framework\Search\RequestInterface;
use PHPUnit\Framework\TestCase;

class FacetFilterCollectorTest extends TestCase
{
    public function testCollectsActiveTermAndSliderFiltersOfAggregatedFieldsInMustOnly(): void
    {
        $collector = new FacetFilterCollector($this->builder(), new RequestState());
        $query = new BoolExpression('catalog_view_container', 1, [
            'category' => $this->filter(new Term('category_filter', '3', 'category_ids')),
            'color' => $this->filter(new Term('color_filter', ['1', '2'], 'color')),
            'nested' => $this->reference('nested', FilterQuery::REFERENCE_QUERY, new BoolExpression('inner', 1, [
                'size' => $this->filter(new Term('size_filter', '7', 'size')),
            ])),
            'price' => $this->filter(new Range('price_filter', 'price', 10, 20)),
            'search' => new MatchQuery('search', 'shirt', 1, []),
            'no_bucket' => $this->filter(new Term('sku_filter', 'x', 'sku')),
            'range_without_dynamic_bucket' => $this->filter(new Range('width_filter', 'width', 1, 2)),
        ], [
            'should_color' => $this->filter(new Term('should_filter', '4', 'material')),
        ]);

        $filters = $collector->collect($this->request('catalog_view_container', $query));

        self::assertSame(['color', 'size', 'price'], array_map(static fn ($f) => $f->getField(), $filters));
        self::assertSame('color_bucket', $filters[0]->getBucketName());
        self::assertSame([['terms' => ['color' => ['1', '2']]]], $filters[0]->getClauses());
        self::assertSame('price_bucket', $filters[2]->getBucketName());
        self::assertSame([['range' => ['price' => ['gte' => 10, 'lte' => 20]]]], $filters[2]->getClauses());
    }

    public function testOtherRequestsAreLeftAlone(): void
    {
        $query = new BoolExpression('graphql', 1, ['color' => $this->filter(new Term('color_filter', '1', 'color'))]);
        $collector = new FacetFilterCollector($this->builder(), new RequestState());

        self::assertSame([], $collector->collect($this->request('graphql_product_search', $query)));
        self::assertFalse($collector->isLayeredRequest($this->request('graphql_product_search', $query)));
        self::assertTrue($collector->isLayeredRequest($this->request('quick_search_container', $query)));
    }

    public function testGraphQlSearchesAreLayeredOnlyWhileOpenFiltersFieldIsAsked(): void
    {
        $query = new BoolExpression('graphql', 1, ['color' => $this->filter(new Term('color_filter', '1', 'color'))]);
        $state = new RequestState();
        $collector = new FacetFilterCollector($this->builder(), $state);
        $request = $this->request('graphql_product_search_with_aggregation', $query);

        self::assertFalse($collector->isLayeredRequest($request));
        $state->start();
        self::assertTrue($collector->isLayeredRequest($request));
        self::assertTrue($collector->isGraphQlRequest($request));
        self::assertFalse($collector->isGraphQlRequest($this->request('catalog_view_container', $query)));
        self::assertSame(['color'], array_map(static fn ($f) => $f->getField(), $collector->collect($request)));
        $state->keep(['price_bucket' => ['min' => 1]]);
        self::assertSame(['price_bucket' => ['min' => 1]], $state->finish());
        self::assertFalse($collector->isLayeredRequest($request));
        self::assertSame([], $state->finish());
    }

    private function filter(Term|Range $filter): FilterQuery
    {
        return $this->reference($filter->getName() . '_query', FilterQuery::REFERENCE_FILTER, $filter);
    }

    /**
     * Magento documents the reference as a string; at runtime it is the filter or query object.
     *
     * @param string $name
     * @param string $type
     * @param object $reference
     * @return FilterQuery
     */
    private function reference(string $name, string $type, object $reference): FilterQuery
    {
        /** @var string $reference */
        return new FilterQuery($name, 1, $type, $reference);
    }

    private function request(string $name, BoolExpression $query): RequestInterface
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getName')->willReturn($name);
        $request->method('getQuery')->willReturn($query);
        $request->method('getAggregation')->willReturn([
            new TermBucket('color_bucket', 'color', []),
            new TermBucket('size_bucket', 'size', []),
            new TermBucket('material_bucket', 'material', []),
            new TermBucket('category_bucket', 'category_ids', []),
            new DynamicBucket('price_bucket', 'price', 'manual'),
        ]);

        return $request;
    }

    private function builder(): FilterBuilder
    {
        $builder = $this->createStub(FilterBuilder::class);
        $builder->method('build')->willReturnCallback(static function (Term|Range $filter, string $condition): array {
            if ($filter instanceof Range) {
                $range = ['gte' => $filter->getFrom(), 'lte' => $filter->getTo()];

                return ['bool' => [$condition => [['range' => [$filter->getField() => $range]]]]];
            }
            $operator = is_array($filter->getValue()) ? 'terms' : 'term';

            return ['bool' => [$condition => [[$operator => [$filter->getField() => $filter->getValue()]]]]];
        });

        return $builder;
    }
}
