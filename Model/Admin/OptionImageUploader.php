<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Admin;

use Iranimij\OpenFilter\Model\Config\OptionImages;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Image\AdapterFactory;
use Magento\Framework\UrlInterface;
use Magento\MediaStorage\Model\File\Uploader;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Option image uploads (1.2): to OptionImages::TMP_DIR first, into OptionImages::MEDIA_DIR when the filter form is
 * saved. Only real images (checked by the image adapter, as Magento's category images). Not Magento's ImageUploader:
 * the media gallery's plugin on it imports every moved file into the gallery, which refuses folders outside its own.
 */
class OptionImageUploader
{
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    private const MIME_TYPES = ['image/jpg', 'image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    /**
     * @param UploaderFactory $uploaderFactory
     * @param Filesystem $filesystem
     * @param AdapterFactory $imageAdapterFactory
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly UploaderFactory $uploaderFactory,
        private readonly Filesystem $filesystem,
        private readonly AdapterFactory $imageAdapterFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Saves an upload to the temporary folder.
     *
     * @param string $fileId name of the uploaded file in the request
     * @return array<string, mixed> name, file, url, size, type and tmp_name, in the format of Magento's uploader
     * @throws LocalizedException
     */
    public function saveFileToTmpDir(string $fileId): array
    {
        /** @var Uploader $uploader */
        $uploader = $this->uploaderFactory->create(['fileId' => $fileId]);
        $uploader->setAllowedExtensions(self::EXTENSIONS);
        $uploader->setAllowRenameFiles(true);
        $uploader->setFilesDispersion(false);
        if (!$uploader->checkMimeType(self::MIME_TYPES)) {
            throw new LocalizedException(__('Upload a JPG, PNG, GIF or WebP image.'));
        }
        $uploader->addValidateCallback('openfilter_option_image', $this->imageAdapterFactory->create(), 'validateUploadFile');
        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $result = $uploader->save($media->getAbsolutePath(OptionImages::TMP_DIR));
        if (!is_array($result) || empty($result['file'])) {
            throw new LocalizedException(__('The image could not be saved. Please try again.'));
        }
        unset($result['path']);
        $result['url'] = $this->mediaUrl() . OptionImages::TMP_DIR . '/' . ltrim((string) $result['file'], '/');
        $result['name'] = $result['file'];

        return $result;
    }

    /**
     * Moves an upload from the temporary folder into place under a name not taken yet.
     *
     * @param string $name file name in OptionImages::TMP_DIR
     * @return string file name in OptionImages::MEDIA_DIR
     * @throws LocalizedException
     */
    public function moveFileFromTmp(string $name): string
    {
        $name = $this->fileName($name);
        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $from = OptionImages::TMP_DIR . '/' . $name;
        if ($name === '' || !$media->isFile($from)) {
            throw new LocalizedException(__('The uploaded image "%1" is gone. Please upload it again.', $name));
        }
        $target = $this->fileName(Uploader::getNewFileName($media->getAbsolutePath(OptionImages::MEDIA_DIR . '/' . $name)));
        $media->renameFile($from, OptionImages::MEDIA_DIR . '/' . $target);

        return $target;
    }

    /**
     * @return string
     */
    private function mediaUrl(): string
    {
        $store = $this->storeManager->getStore();

        return $store instanceof Store ? $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) : '/media/';
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
