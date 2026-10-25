<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Migration;

use Magento\Framework\Module\ModuleListInterface;

/**
 * Other layered navigation modules installed and enabled next to OpenFilter (1.2 migration), by module name.
 */
class VendorModules
{
    /**
     * @param ModuleListInterface $moduleList enabled modules
     * @param array<string, string> $prefixes module name prefix => product name
     */
    public function __construct(
        private readonly ModuleListInterface $moduleList,
        private readonly array $prefixes = [
            'Amasty_Shopby' => 'Amasty Improved Layered Navigation',
            'Mirasvit_LayeredNavigation' => 'Mirasvit Layered Navigation',
            'Mirasvit_SeoFilter' => 'Mirasvit SEO Filters',
            'Mageplaza_LayeredNavigation' => 'Mageplaza Layered Navigation',
            'Mageplaza_SeoUrl' => 'Mageplaza SEO-Friendly URL',
            'Aheadworks_Layerednav' => 'Aheadworks Layered Navigation',
            'WeltPixel_LayeredNavigation' => 'WeltPixel Layered Navigation',
        ]
    ) {
    }

    /**
     * @return array<string, string> enabled module name => product name, sorted by module name
     */
    public function enabled(): array
    {
        $found = [];
        foreach ($this->moduleList->getNames() as $module) {
            foreach ($this->prefixes as $prefix => $product) {
                if (str_starts_with($module, $prefix)) {
                    $found[$module] = $product;
                    break;
                }
            }
        }
        ksort($found);

        return $found;
    }
}
