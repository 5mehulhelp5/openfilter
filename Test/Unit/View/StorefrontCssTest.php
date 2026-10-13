<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit\View;

use PHPUnit\Framework\TestCase;

/**
 * The storefront stylesheet contract (02 §5, 10 FE6/FE10): of-* classes only, logical properties so right-to-left
 * store views mirror, no !important (themes restyle through custom properties), at most 4 KB gzipped.
 */
class StorefrontCssTest extends TestCase
{
    private const FILE = __DIR__ . '/../../../view/base/web/css/openfilter.css';

    private function css(): string
    {
        self::assertFileExists(self::FILE);

        return (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(self::FILE));
    }

    public function testUsesLogicalPropertiesOnly(): void
    {
        $physical = '/(?<![\w-])(?:(?:margin|padding|border|inset)-(?:left|right|top|bottom)|left|right|top|bottom)\s*:'
            . '|text-align\s*:\s*(?:left|right)|float\s*:\s*(?:left|right)|clear\s*:\s*(?:left|right)/';

        self::assertSame(0, preg_match_all($physical, $this->css(), $matches), implode(', ', $matches[0]));
    }

    public function testHasNoImportantAndOnlyOpenFilterSelectors(): void
    {
        $css = $this->css();
        self::assertStringNotContainsString('!important', $css);
        $css = (string) preg_replace('/@(?:media|supports)[^{]*\{/', '}', $css); // at-rule preludes are not selectors
        preg_match_all('/(?:^|})\s*([^{}]+)\{/', $css, $rules);
        foreach ($rules[1] as $selectorList) {
            foreach (explode(',', $selectorList) as $selector) {
                self::assertMatchesRegularExpression(
                    '/^\s*(?:\[dir=rtl\]\s+)?(?:html\.of-[\w-]+|\.of-|dialog\.of-)/',
                    $selector,
                    "selector outside the of-* contract: $selector"
                );
            }
        }
    }

    public function testStaysWithinTheBudget(): void
    {
        self::assertLessThanOrEqual(4096, strlen((string) gzencode((string) file_get_contents(self::FILE), 9)));
    }
}
