<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Plugin\Swatches;

use Iranimij\OpenFilter\Model\Layer\LayerContext;
use Magento\Swatches\Block\LayeredNavigation\RenderLayered;

/**
 * Swatch filters build their links outside the filter items; give them OpenFilter's canonical toggle links.
 */
class RenderLayeredPlugin
{
    /**
     * @param LayerContext $context
     */
    public function __construct(
        private readonly LayerContext $context
    ) {
    }

    /**
     * @param RenderLayered $subject
     * @param callable $proceed
     * @param string|null $attributeCode
     * @param int|string $optionId
     * @return string
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundBuildUrl(RenderLayered $subject, callable $proceed, $attributeCode, $optionId)
    {
        $key = $this->context->isActive() ? $this->context->keyForAttributeCode((string) $attributeCode) : null;

        return $key === null ? $proceed($attributeCode, $optionId) : $this->context->toggleUrl($key, (string) $optionId);
    }
}
