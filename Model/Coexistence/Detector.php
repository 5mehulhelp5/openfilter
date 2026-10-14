<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Coexistence;

use Magento\Framework\Module\Manager as ModuleManager;

/**
 * Detects a search module that provides its own layered navigation (ElasticSuite, Adobe Live Search). OpenFilter
 * then steps aside on the storefront: never two filter systems on one page (02 · Architecture §3).
 */
class Detector
{
    private bool $resolved = false;

    private ?string $active = null;

    /**
     * @param ModuleManager $moduleManager
     * @param array<string, string> $replacements module name => product name shown to the merchant
     */
    public function __construct(
        private readonly ModuleManager $moduleManager,
        private readonly array $replacements = [
            'Smile_ElasticsuiteCatalog' => 'ElasticSuite',
            'Magento_LiveSearchAdapter' => 'Adobe Live Search',
            'Magento_LiveSearchProductListing' => 'Adobe Live Search',
        ]
    ) {
    }

    /**
     * Name of the module that provides layered navigation instead of the stock search, or null.
     *
     * @return string|null
     */
    public function getActiveReplacement(): ?string
    {
        if (!$this->resolved) {
            $this->resolved = true;
            foreach ($this->replacements as $module => $name) {
                if ($this->moduleManager->isEnabled($module)) {
                    $this->active = $name;
                    break;
                }
            }
        }

        return $this->active;
    }
}
