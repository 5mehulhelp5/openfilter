<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Index;

use Iranimij\OpenFilter\Model\ResourceModel\BuiltinDataSource;
use Iranimij\OpenFilter\Model\Index\FieldMappingProvider;
use Iranimij\OpenFilter\Model\Index\Fields;
use Iranimij\OpenFilter\Model\Index\Provider\NewsFieldsProvider;
use Iranimij\OpenFilter\Model\Index\Provider\RatingFieldsProvider;
use Iranimij\OpenFilter\Model\Index\Provider\SaleFieldsProvider;
use Iranimij\OpenFilter\Model\Index\Provider\StockFieldsProvider;
use PHPUnit\Framework\TestCase;

class FieldsProvidersTest extends TestCase
{
    public function testRatingOnlyForReviewedProducts(): void
    {
        $source = $this->createStub(BuiltinDataSource::class);
        $source->method('ratings')->willReturn([1 => 80, 2 => 0]);

        self::assertSame([1 => ['of_rating' => 80]], (new RatingFieldsProvider($source))->getFields([1, 2, 3], 1));
    }

    public function testStockForEveryProduct(): void
    {
        $source = $this->createStub(BuiltinDataSource::class);
        $source->method('salable')->willReturn([1 => 1, 2 => 0]);

        self::assertSame(
            [1 => ['of_in_stock' => 1], 2 => ['of_in_stock' => 0], 3 => ['of_in_stock' => 0]],
            (new StockFieldsProvider($source))->getFields([1, 2, '3'], 1)
        );
    }

    public function testSaleFlagPerGroupWhereTheFinalPriceIsLower(): void
    {
        $source = $this->createStub(BuiltinDataSource::class);
        $source->method('websiteId')->willReturn(1);
        $source->method('prices')->willReturn([
            1 => [0 => ['price' => 10.0, 'final_price' => 7.0], 2 => ['price' => 10.0, 'final_price' => 7.0]],
            2 => [0 => ['price' => 10.0, 'final_price' => 10.0], 2 => ['price' => 10.0, 'final_price' => 8.0]],
            3 => [0 => ['price' => 10.0, 'final_price' => 9.99995]],
        ]);

        self::assertSame(
            [1 => ['of_on_sale_0_1' => 1, 'of_on_sale_2_1' => 1], 2 => ['of_on_sale_2_1' => 1]],
            (new SaleFieldsProvider($source, new Fields()))->getFields([1, 2, 3], 1)
        );
    }

    public function testNewsWindowWithOpenEnds(): void
    {
        $source = $this->createStub(BuiltinDataSource::class);
        $source->method('newsDates')->willReturn([
            1 => ['from' => '2026-10-12 00:00:00', 'to' => '2026-10-14 00:00:00'],
            2 => ['from' => '2026-10-01', 'to' => null],
            3 => ['from' => null, 'to' => '2026-12-31 00:00:00'],
            4 => ['from' => null, 'to' => null],
            5 => ['from' => 'not a date', 'to' => ''],
        ]);

        self::assertSame([
            1 => [Fields::NEWS_FROM => '2026-10-12 00:00:00', Fields::NEWS_TO => '2026-10-14 00:00:00'],
            2 => [Fields::NEWS_FROM => '2026-10-01 00:00:00', Fields::NEWS_TO => Fields::OPEN_END],
            3 => [Fields::NEWS_FROM => Fields::OPEN_START, Fields::NEWS_TO => '2026-12-31 00:00:00'],
            5 => [Fields::NEWS_FROM => Fields::OPEN_START, Fields::NEWS_TO => Fields::OPEN_END],
        ], (new NewsFieldsProvider($source))->getFields([1, 2, 3, 4, 5], 1));
    }

    public function testMappingCoversEveryGroupOfTheWebsite(): void
    {
        $source = $this->createStub(BuiltinDataSource::class);
        $source->method('groupIds')->willReturn([0, 1, 2, 3]);
        $source->method('websiteIds')->willReturn([1, 2]);
        $provider = new FieldMappingProvider($source, new Fields());

        $forWebsite = $provider->getFields(['websiteId' => 1]);
        self::assertSame(['type' => 'integer'], $forWebsite['of_rating']);
        self::assertSame(['type' => 'date', 'format' => Fields::ENGINE_DATE_FORMAT], $forWebsite['of_news_to']);
        self::assertArrayHasKey('of_on_sale_3_1', $forWebsite);
        self::assertArrayNotHasKey('of_on_sale_3_2', $forWebsite);
        self::assertArrayHasKey('of_on_sale_3_2', $provider->getFields());
        self::assertCount(4 + 8, $provider->getFields());
    }
}
