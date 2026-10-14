<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Url;

use Iranimij\OpenFilter\Model\Url\SlugGenerator;
use Iranimij\OpenFilter\Model\Url\Transliterator;
use PHPUnit\Framework\TestCase;

class SlugGeneratorTest extends TestCase
{
    private SlugGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new SlugGenerator(new Transliterator());
    }

    public function testCollisionsGetSuffixesInSortOrder(): void
    {
        self::assertSame(
            [10 => 'red', 11 => 'red-2', 12 => 'blue', 13 => 'red-3'],
            $this->generator->forOptions([10 => 'Red', 11 => 'red', 12 => 'Blue', 13 => 'RED!'])
        );
    }

    public function testExistingSlugsStayAndNewOnesAvoidThem(): void
    {
        $slugs = $this->generator->forOptions(
            [10 => 'Red (new label)', 11 => 'Red', 12 => 'Green'],
            [10 => 'red', 99 => 'deleted-option']
        );

        self::assertSame([10 => 'red', 11 => 'red-2', 12 => 'green'], $slugs);
    }

    public function testUnusableLabelsFallBackToTheOptionId(): void
    {
        self::assertSame([7 => '7', 8 => 'xl'], $this->generator->forOptions([7 => '★★★', 8 => 'XL']));
    }

    public function testAttributeNamesAvoidReservedAndTakenNames(): void
    {
        self::assertSame('color', $this->generator->forAttribute('color'));
        self::assertSame('new-2', $this->generator->forAttribute('new'));
        self::assertSame('p-2', $this->generator->forAttribute('p'));
        self::assertSame('size-3', $this->generator->forAttribute('size', ['size', 'size-2']));
    }

    public function testOptionSlugShape(): void
    {
        self::assertTrue($this->generator->isValidOptionSlug('dark-blue-2'));
        self::assertFalse($this->generator->isValidOptionSlug('Dark'));
        self::assertFalse($this->generator->isValidOptionSlug('dark--blue'));
        self::assertFalse($this->generator->isValidOptionSlug('-dark'));
        self::assertFalse($this->generator->isValidOptionSlug(''));
        self::assertFalse($this->generator->isValidOptionSlug(str_repeat('a', 65)));
    }
}
