<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Landing;

use Iranimij\OpenFilter\Model\Landing\LandingPage;
use PHPUnit\Framework\TestCase;

class LandingPageTest extends TestCase
{
    public function testADatabaseRowIsReadIntoTypedValues(): void
    {
        $page = new LandingPage([
            'page_id' => '7',
            'is_active' => '0',
            'category_id' => '21',
            'filters' => '{"color":["49",""],"size":[],"price":"10-50"}',
            'url_key' => ' /Black-Tops/ ',
            'title' => '  Black tops ',
            'h1' => ' ',
            'meta_title' => '',
            'robots' => 'NOINDEX,FOLLOW',
            'store_ids' => ['2', '1', '2'],
            'unknown' => 'ignored',
        ]);

        self::assertSame(7, $page->getId());
        self::assertFalse($page->isActive());
        self::assertSame(21, $page->getCategoryId());
        self::assertSame(['color' => ['49'], 'price' => ['10-50']], $page->getFilters(), 'empty values and filters go');
        self::assertSame('black-tops', $page->getUrlKey());
        self::assertSame('Black tops', $page->getTitle());
        self::assertSame('Black tops', $page->getHeading(), 'an empty heading is the title');
        self::assertSame('Black tops', $page->getMetaTitle());
        self::assertNull($page->getMetaDescription());
        self::assertNull($page->getDescription());
        self::assertSame('NOINDEX,FOLLOW', $page->getRobots());
        self::assertSame([1, 2], $page->getStoreIds());
        self::assertArrayNotHasKey('unknown', $page->toArray());
    }

    public function testDefaultsAndStoreViews(): void
    {
        $page = new LandingPage(['robots' => 'FOLLOW', 'filters' => 'not json', 'h1' => 'Heading']);

        self::assertNull($page->getId());
        self::assertTrue($page->isActive());
        self::assertSame([], $page->getFilters());
        self::assertSame('INDEX,FOLLOW', $page->getRobots(), 'unknown robots values fall back');
        self::assertSame('Heading', $page->getHeading());
        self::assertTrue($page->isInStore(3), 'store 0 is every store view');
        self::assertTrue((new LandingPage(['store_ids' => [2]]))->isInStore(2));
        self::assertFalse((new LandingPage(['store_ids' => [2]]))->isInStore(1));
    }
}
