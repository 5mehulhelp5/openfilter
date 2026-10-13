<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Search;

use Iranimij\OpenFilter\Model\Index\Fields;
use Iranimij\OpenFilter\Model\Search\BuiltinClauses;
use Iranimij\OpenFilter\Model\Url\FilterState;
use PHPUnit\Framework\TestCase;

class BuiltinClausesTest extends TestCase
{
    public function testClausesOnlyForActiveValuesAggregationsForEveryBuiltin(): void
    {
        $built = (new BuiltinClauses(new Fields()))->build(
            ['rating', 'stock', 'sale', 'new', 'price'],
            new FilterState(['rating' => ['4'], 'sale' => ['1'], 'stock' => ['0']]),
            2,
            1,
            '2026-10-13 10:00:00'
        );

        self::assertSame(['rating', 'stock', 'sale', 'new'], array_keys($built), 'price is not a built-in toggle');
        self::assertSame(['range' => ['of_rating' => ['gte' => 80]]], $built['rating']['clause']);
        self::assertSame('of_builtin_rating', $built['rating']['bucket']);
        self::assertSame('of_builtin:rating', $built['rating']['field']);
        self::assertSame(
            [['key' => '4', 'from' => 80], ['key' => '3', 'from' => 60], ['key' => '2', 'from' => 40], ['key' => '1', 'from' => 20]],
            $built['rating']['aggregation']['range']['ranges']
        );
        self::assertNull($built['stock']['clause'], 'only "1" switches a toggle on');
        self::assertSame(['filter' => ['term' => ['of_in_stock' => 1]]], $built['stock']['aggregation']);
        self::assertSame(['term' => ['of_on_sale_2_1' => 1]], $built['sale']['clause']);
        self::assertNull($built['new']['clause']);
        self::assertSame([
            ['range' => [Fields::NEWS_FROM => ['lte' => '2026-10-13 10:00:00', 'format' => Fields::ENGINE_DATE_FORMAT]]],
            ['range' => [Fields::NEWS_TO => ['gte' => '2026-10-13 10:00:00', 'format' => Fields::ENGINE_DATE_FORMAT]]],
        ], $built['new']['aggregation']['filter']['bool']['must']);
    }

    public function testCountsFromTheResponse(): void
    {
        $counts = (new BuiltinClauses(new Fields()))->counts([
            'of_builtin_stock' => ['doc_count' => 10],
            'of_builtin_rating' => ['buckets' => ['4' => ['key' => '4', 'doc_count' => 6], '1' => ['doc_count' => 9]]],
            'color_bucket' => ['buckets' => []],
            'of_builtin_new' => 'garbage',
        ]);

        self::assertSame(['stock' => 10, 'rating' => [4 => 6, 1 => 9]], $counts);
    }
}
