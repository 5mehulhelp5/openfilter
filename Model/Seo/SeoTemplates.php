<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Seo;

/**
 * Page title, meta description and H1 of filtered category pages from the merchant's templates (1.1):
 * {category} = the category name, {filters} = the active values ("Red, Blue"), {store} = the store name.
 * Unknown placeholders stay as they are; spaces left by an empty placeholder are tidied.
 */
class SeoTemplates
{
    /**
     * @param string $template
     * @param array{category: string, filters: string[], store: string} $values
     * @return string '' when the template is empty
     */
    public function render(string $template, array $values): string
    {
        if (trim($template) === '') {
            return '';
        }
        $text = strtr($template, [
            '{category}' => $values['category'],
            '{filters}' => implode(', ', $values['filters']),
            '{store}' => $values['store'],
        ]);
        $text = (string) preg_replace('/\s+/u', ' ', $text);
        $text = (string) preg_replace('/\s+([,:.])/u', '$1', $text);

        return trim($text, " \t\n\r\0\x0B|,-:");
    }
}
