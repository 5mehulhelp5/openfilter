<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Url;

use Iranimij\OpenFilter\Model\Url\Transliterator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

class TransliteratorTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function labels(): array
    {
        return [
            'umlaut' => ['Grün', 'gruen'],
            'sharp s' => ['Weiß', 'weiss'],
            'capital umlauts and sharp s' => ['ÄÖÜ ẞ', 'aeoeue-ss'],
            'accents' => ['Crème Brûlée', 'creme-brulee'],
            'ligature' => ['Œuvre', 'oeuvre'],
            'polish' => ['Łódź', 'lodz'],
            'separators collapse' => ['  Red / Blue  ', 'red-blue'],
            'digits stay' => ['Size 42.5', 'size-42-5'],
            'symbols only' => ['!!! ???', ''],
            'html entities and marks' => ['Cocona&reg; performance fabric', 'cocona-performance-fabric'],
            'trademark' => ['LumaTech™', 'lumatech'],
            'fraction entity' => ['&frac14; zip', '1-4-zip'],
            'empty' => ['', ''],
        ];
    }

    /**
     * @dataProvider labels
     */
    #[DataProvider('labels')]
    public function testSlugWithIntl(string $label, string $expected): void
    {
        self::assertSame($expected, (new Transliterator())->slug($label));
    }

    /**
     * @dataProvider labels
     */
    #[DataProvider('labels')]
    public function testSlugWithoutIntl(string $label, string $expected): void
    {
        self::assertSame($expected, (new Transliterator(false))->slug($label));
    }

    #[RequiresPhpExtension('intl')]
    public function testOtherScriptsBecomeAscii(): void
    {
        self::assertSame('zhong-wen', (new Transliterator(true))->slug('中文'));
        self::assertSame('krasnyj', (new Transliterator(true))->slug('Красный'));
        self::assertSame('', (new Transliterator(false))->slug('中文'), 'no intl: caller falls back to the option id');
    }

    public function testLongLabelsAreCutWithoutATrailingDash(): void
    {
        $slug = (new Transliterator())->slug(str_repeat('abc ', 30));

        self::assertLessThanOrEqual(Transliterator::MAX_LENGTH, strlen($slug));
        self::assertStringEndsNotWith('-', $slug);
    }
}
