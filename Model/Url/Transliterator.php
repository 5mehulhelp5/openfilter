<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Url;

/**
 * Turns an option label into a URL name (Spec Slug, "Generation rules"): German transliteration first
 * (ä → ae, ö → oe, ü → ue, ß → ss), then any script to ASCII, then [a-z0-9] with single dashes, at most 64 characters.
 * Returns '' when nothing usable is left; the caller falls back to the option id.
 */
class Transliterator
{
    public const MAX_LENGTH = 64;

    private const GERMAN = ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss', 'ẞ' => 'ss'];

    /** Marks that carry no meaning in a URL ("Cocona® fabric" → cocona-fabric). */
    private const MARKS = ['®' => '', '™' => '', '©' => '', '℠' => ''];

    /** Used when the intl extension is missing. */
    private const LATIN = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'ā' => 'a', 'ą' => 'a', 'ă' => 'a',
        'æ' => 'ae', 'ç' => 'c', 'ć' => 'c', 'č' => 'c', 'ď' => 'd', 'đ' => 'd', 'ð' => 'd',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ę' => 'e', 'ě' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'ı' => 'i', 'ł' => 'l', 'ľ' => 'l',
        'ñ' => 'n', 'ń' => 'n', 'ň' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o', 'ō' => 'o',
        'ő' => 'o', 'œ' => 'oe', 'ŕ' => 'r', 'ř' => 'r', 'ś' => 's', 'š' => 's', 'ş' => 's', 'ș' => 's',
        'ť' => 't', 'ţ' => 't', 'ț' => 't', 'þ' => 'th', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ū' => 'u',
        'ů' => 'u', 'ű' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'ź' => 'z', 'ż' => 'z', 'ž' => 'z',
    ];

    /**
     * @param bool|null $useIntl null = use intl when the extension is loaded (tests force the fallback)
     */
    public function __construct(
        private readonly ?bool $useIntl = null
    ) {
    }

    /**
     * URL name for a label, or '' when the label has no usable characters.
     *
     * @param string $label
     * @return string
     */
    public function slug(string $label): string
    {
        // Labels may hold HTML entities (Magento sample data: "&frac14; zip", "LumaTech&trade;"); the result is
        // reduced to [a-z0-9-] below, never printed as HTML.
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $label = html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strtr(mb_strtolower(trim(strtr($label, self::MARKS)), 'UTF-8'), self::GERMAN);
        $useIntl = $this->useIntl ?? function_exists('transliterator_transliterate');
        if ($useIntl) {
            $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
            $text = is_string($ascii) ? $ascii : $text;
        } else {
            $text = strtr($text, self::LATIN + ['¼' => '1-4', '½' => '1-2', '¾' => '3-4']);
        }
        $text = (string) preg_replace('/[^a-z0-9]+/', '-', strtolower($text));
        $text = trim($text, '-');

        return trim(substr($text, 0, self::MAX_LENGTH), '-');
    }
}
