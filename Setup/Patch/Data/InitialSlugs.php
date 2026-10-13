<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Setup\Patch\Data;

use Iranimij\OpenFilter\Api\SlugRepositoryInterface;
use Iranimij\OpenFilter\Model\Config\FilterConfigStorage;
use Iranimij\OpenFilter\Model\Url\SlugProvider;
use Iranimij\OpenFilter\Model\Url\SlugStorage;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * URL names for the options and parameters of every filterable attribute, generated once at install.
 */
class InitialSlugs implements DataPatchInterface
{
    /**
     * @param FilterConfigStorage $configStorage
     * @param SlugRepositoryInterface $slugRepository
     * @param SlugProvider $slugProvider
     * @param SlugStorage $slugStorage
     */
    public function __construct(
        private readonly FilterConfigStorage $configStorage,
        private readonly SlugRepositoryInterface $slugRepository,
        private readonly SlugProvider $slugProvider,
        private readonly SlugStorage $slugStorage
    ) {
    }

    /**
     * @inheritDoc
     */
    public function apply(): self
    {
        foreach ($this->configStorage->filterableAttributes(0) as $id => $attribute) {
            if (in_array($attribute['input_type'], ['select', 'multiselect'], true)) {
                $this->slugRepository->regenerate($id);
            }
        }
        $stored = $this->slugStorage->attributeSlugsForStore(0);
        $this->slugProvider->reset();
        foreach ($this->slugProvider->forStore(0)['attributes'] as $id => $slug) {
            if (!isset($stored[$id])) {
                $this->slugStorage->writeAttributeSlug($id, 0, $slug);
            }
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies(): array
    {
        return [DefaultFilterConfig::class];
    }

    /**
     * @inheritDoc
     */
    public function getAliases(): array
    {
        return [];
    }
}
