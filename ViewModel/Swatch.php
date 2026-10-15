<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\ViewModel;

use Iranimij\OpenFilter\ViewModel\Data\FilterGroup;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Swatches\Helper\Data as SwatchData;
use Magento\Swatches\Helper\Media;
use Magento\Swatches\Model\Swatch as CoreSwatch;

/**
 * Core swatch data (colour, image, text) for the options of a swatch filter, read in one lookup per filter.
 * Colours are only ever hex values: templates draw them as SVG fills, never as inline CSS.
 */
class Swatch implements ArgumentInterface
{
    public const COLOR = 'color';
    public const IMAGE = 'image';
    public const TEXT = 'text';

    private const HEX = '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i';

    /**
     * @param SwatchData $swatchData
     * @param Media $media
     */
    public function __construct(
        private readonly SwatchData $swatchData,
        private readonly Media $media
    ) {
    }

    /**
     * Swatch per option value; options without usable swatch data show their label as a text swatch.
     *
     * @param FilterGroup $group
     * @return array<int|string, array{type: string, value: string}> numeric option ids become integer keys
     */
    public function forGroup(FilterGroup $group): array
    {
        if ($group->getKey() === null || $group->getOptions() === []) {
            return [];
        }
        $labels = [];
        foreach ($group->getOptions() as $option) {
            $labels[$option->getValue()] = $option->getLabel();
        }

        return $this->forOptions($labels);
    }

    /**
     * Swatch per option id (also for GraphQL); options without usable swatch data show their label as text.
     *
     * @param array<int|string, string> $labels option id => label
     * @return array<int|string, array{type: string, value: string}>
     */
    public function forOptions(array $labels): array
    {
        if ($labels === []) {
            return [];
        }
        $data = $this->swatchData->getSwatchesByOptionsId(array_map('strval', array_keys($labels)));
        $swatches = [];
        foreach ($labels as $value => $label) {
            $swatches[$value] = $this->swatch($data[$value] ?? null, $label);
        }

        return $swatches;
    }

    /**
     * @param array<string, mixed>|null $data
     * @param string $label
     * @return array{type: string, value: string}
     */
    private function swatch(?array $data, string $label): array
    {
        $type = (int) ($data['type'] ?? CoreSwatch::SWATCH_TYPE_EMPTY);
        $value = trim((string) ($data['value'] ?? ''));
        if ($type === CoreSwatch::SWATCH_TYPE_VISUAL_COLOR && preg_match(self::HEX, $value)) {
            return ['type' => self::COLOR, 'value' => $value];
        }
        if ($type === CoreSwatch::SWATCH_TYPE_VISUAL_IMAGE && $value !== '') {
            return [
                'type' => self::IMAGE,
                'value' => (string) $this->media->getSwatchAttributeImage(CoreSwatch::SWATCH_THUMBNAIL_NAME, $value),
            ];
        }
        if ($type === CoreSwatch::SWATCH_TYPE_TEXTUAL && $value !== '') {
            return ['type' => self::TEXT, 'value' => $value];
        }

        return ['type' => self::TEXT, 'value' => $label];
    }
}
