<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Test\Unit;

use PHPUnit\Framework\TestCase;

/**
 * en_US.csv lists every translatable string of the module (code and admin XML); de_DE.csv translates all of them.
 */
class I18nTest extends TestCase
{
    public function testEveryPhraseInTheCodeIsInEnUs(): void
    {
        $missing = array_values(array_diff($this->phrasesInCode(), array_keys($this->csv('en_US'))));

        self::assertSame([], $missing, 'add these to i18n/en_US.csv');
    }

    public function testGermanTranslatesEveryPhrase(): void
    {
        $german = $this->csv('de_DE');
        $english = array_keys($this->csv('en_US'));

        self::assertSame([], array_values(array_diff($english, array_keys($german))), 'missing in de_DE.csv');
        self::assertSame([], array_values(array_diff(array_keys($german), $english)), 'unknown phrases in de_DE.csv');
        foreach ($german as $phrase => $translation) {
            self::assertNotSame('', trim($translation), $phrase);
        }
    }

    /**
     * @return string[]
     */
    private function phrasesInCode(): array
    {
        $root = dirname(__DIR__, 2);
        $phrases = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = $file->getPathname();
            if (preg_match('#/(Test|vendor)/#', substr($path, strlen($root)))) {
                continue;
            }
            $content = (string) file_get_contents($path);
            if (preg_match('/\.(php|phtml)$/', $path)) {
                preg_match_all("/__\(\s*'((?:[^'\\\\]|\\\\.)*)'/", $content, $matches);
                foreach ($matches[1] as $phrase) {
                    $phrases[stripslashes($phrase)] = true;
                }
            } elseif (preg_match('#/etc/.*\.xml$#', $path)) {
                foreach ($this->xmlPhrases($content) as $phrase) {
                    $phrases[$phrase] = true;
                }
            }
        }
        $phrases = array_keys($phrases);
        sort($phrases);

        return $phrases;
    }

    /**
     * Texts Magento translates in admin XML: title attributes and label/comment elements of translatable nodes.
     *
     * @param string $xml
     * @return string[]
     */
    private function xmlPhrases(string $xml): array
    {
        $document = simplexml_load_string($xml);
        if ($document === false) {
            return [];
        }
        $phrases = [];
        foreach ($document->xpath('//*[@translate]') ?: [] as $node) {
            foreach (explode(' ', (string) $node['translate']) as $part) {
                $value = isset($node[$part]) ? (string) $node[$part] : (string) ($node->{$part} ?? '');
                if (trim($value) !== '') {
                    $phrases[] = trim($value);
                }
            }
        }

        return $phrases;
    }

    /**
     * @param string $locale
     * @return array<string, string>
     */
    private function csv(string $locale): array
    {
        $rows = [];
        $handle = fopen(dirname(__DIR__, 2) . '/i18n/' . $locale . '.csv', 'rb');
        self::assertNotFalse($handle, $locale . '.csv is missing');
        while (($row = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            if (isset($row[0], $row[1])) {
                $rows[$row[0]] = $row[1];
            }
        }
        fclose($handle);

        return $rows;
    }
}
