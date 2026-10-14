<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Url;

/**
 * Builds collision-free URL names for the options of one attribute in one store view, in option sort order, so the
 * result is stable: the first "Red" keeps "red", the next ones get "red-2", "red-3".
 */
class SlugGenerator
{
    /** Parameter names Magento or OpenFilter already use (Spec Slug, rule 6). */
    public const RESERVED = [
        'p', 'q', 'cat', 'product_list_order', 'product_list_dir', 'product_list_limit', 'product_list_mode',
        '___store', '___from_store', 'price', 'rating', 'stock', 'sale', 'new',
    ];

    /**
     * @param Transliterator $transliterator
     */
    public function __construct(
        private readonly Transliterator $transliterator
    ) {
    }

    /**
     * URL names for options, keeping the slugs that already exist.
     *
     * @param array<int, string> $labels option id => label, in option sort order
     * @param array<int, string> $existing option id => slug that must not change
     * @return array<int, string> option id => slug for every option in $labels
     */
    public function forOptions(array $labels, array $existing = []): array
    {
        $taken = array_flip(array_values(array_intersect_key($existing, $labels)));
        $slugs = [];
        foreach ($labels as $optionId => $label) {
            if (isset($existing[$optionId])) {
                $slugs[$optionId] = $existing[$optionId];
                continue;
            }
            $slug = $this->unique($this->transliterator->slug($label) ?: (string) $optionId, $taken);
            $taken[$slug] = true;
            $slugs[$optionId] = $slug;
        }

        return $slugs;
    }

    /**
     * Parameter name for an attribute: its code, unless Magento or a built-in filter uses that name.
     *
     * @param string $attributeCode
     * @param string[] $takenByOthers parameter names of the other attributes in the same store view
     * @return string
     */
    public function forAttribute(string $attributeCode, array $takenByOthers = []): string
    {
        $taken = array_flip(array_merge(self::RESERVED, $takenByOthers));

        return $this->unique($attributeCode, $taken);
    }

    /**
     * Whether a merchant-entered option URL name has the allowed shape.
     *
     * @param string $slug
     * @return bool
     */
    public function isValidOptionSlug(string $slug): bool
    {
        return strlen($slug) <= Transliterator::MAX_LENGTH && preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) === 1;
    }

    /**
     * @param string $slug
     * @param array<string, mixed> $taken
     * @return string
     */
    private function unique(string $slug, array $taken): string
    {
        if (!isset($taken[$slug])) {
            return $slug;
        }
        $suffix = 2;
        while (isset($taken[$slug . '-' . $suffix])) {
            $suffix++;
        }

        return $slug . '-' . $suffix;
    }
}
