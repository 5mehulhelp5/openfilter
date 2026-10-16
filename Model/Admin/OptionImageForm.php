<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Admin;

use Iranimij\OpenFilter\Model\Config\OptionImages;
use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Magento\Framework\Exception\LocalizedException;

/**
 * The "Option images" table of the filter form (1.2): one row per option with its image, uploads moved from the
 * temporary folder into media/openfilter/options when the form is saved.
 */
class OptionImageForm
{
    /** Name of the uploaded file in the upload request (one name for every row). */
    public const FILE_FIELD = 'option_image';

    /**
     * @param OptionImages $images
     * @param SlugStorage $slugStorage option labels
     * @param OptionImageUploader $uploader moves uploads from OptionImages::TMP_DIR to OptionImages::MEDIA_DIR
     */
    public function __construct(
        private readonly OptionImages $images,
        private readonly SlugStorage $slugStorage,
        private readonly OptionImageUploader $uploader
    ) {
    }

    /**
     * Rows of the form: option id, admin label and the image in the uploader's format.
     *
     * @param int $attributeId
     * @return array<int, array{option_id: int, option_label: string, image: array<int, array<string, string>>}>
     */
    public function rows(int $attributeId): array
    {
        $labels = $this->slugStorage->optionLabels($attributeId)['admin'];
        $urls = $this->images->urls(array_keys($labels), 0);
        $paths = $this->images->paths();
        $rows = [];
        foreach ($labels as $optionId => $label) {
            $optionId = (int) $optionId;
            $rows[] = [
                'option_id' => $optionId,
                'option_label' => (string) $label,
                'image' => isset($urls[$optionId]) ? [[
                    'name' => $this->fileName($paths[$optionId]),
                    'file' => $paths[$optionId],
                    'url' => $urls[$optionId],
                    'type' => 'image',
                ]] : [],
            ];
        }

        return $rows;
    }

    /**
     * Saves the posted rows of one attribute: new uploads are moved into place, a removed image is forgotten.
     *
     * @param int $attributeId
     * @param mixed $rows posted "option_images"
     * @return void
     * @throws LocalizedException when an upload cannot be moved
     */
    public function save(int $attributeId, mixed $rows): void
    {
        $known = $this->slugStorage->optionLabels($attributeId)['admin'];
        $current = $this->images->paths();
        $changes = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row) || !isset($row['option_id']) || !isset($known[(int) $row['option_id']])) {
                continue;
            }
            $optionId = (int) $row['option_id'];
            $image = is_array($row['image'] ?? null) ? reset($row['image']) : null;
            if (!is_array($image) || !isset($image['name'])) {
                if (isset($current[$optionId])) {
                    $changes[$optionId] = null;
                }
                continue;
            }
            // A fresh upload carries tmp_name (as in Magento's category images); a stored image comes back as it was.
            if (isset($image['tmp_name'])) {
                $changes[$optionId] = ltrim((string) $this->uploader->moveFileFromTmp((string) $image['name']), '/');
            }
        }
        $this->images->save($changes);
    }

    /**
     * The file name of a path (the part after the last slash).
     *
     * @param string $path
     * @return string
     */
    private function fileName(string $path): string
    {
        $slash = strrpos($path, '/');

        return $slash === false ? $path : substr($path, $slash + 1);
    }
}
