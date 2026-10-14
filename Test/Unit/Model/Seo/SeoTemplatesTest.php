<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\Model\Seo;

use Iranimij\OpenFilter\Model\Seo\SeoTemplates;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SeoTemplatesTest extends TestCase
{
    /**
     * @return array<string, array{string, string[], string}>
     */
    public static function templates(): array
    {
        return [
            'title' => ['{filters} {category} | {store}', ['Red', 'Blue'], 'Red, Blue Tops | Luma'],
            'H1' => ['{filters} {category}', ['Hooded'], 'Hooded Tops'],
            'description' => ['Shop {filters} {category} at {store}.', ['Red'], 'Shop Red Tops at Luma.'],
            'no values left' => ['{filters} {category} | {store}', [], 'Tops | Luma'],
            'unknown placeholder kept' => ['{brand} {category}', ['Red'], '{brand} Tops'],
            'empty template' => ['  ', ['Red'], ''],
            'stray separators trimmed' => ['| {filters} -', [], ''],
        ];
    }

    /**
     * @dataProvider templates
     * @param string[] $filters
     */
    #[DataProvider('templates')]
    public function testRender(string $template, array $filters, string $expected): void
    {
        self::assertSame($expected, (new SeoTemplates())->render($template, [
            'category' => 'Tops', 'filters' => $filters, 'store' => 'Luma',
        ]));
    }
}
