<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Coexistence;

use Magento\Framework\Notification\MessageInterface;

/**
 * Admin system message while OpenFilter steps aside for another layered navigation.
 */
class AdminNotice implements MessageInterface
{
    /**
     * @param Detector $detector
     */
    public function __construct(
        private readonly Detector $detector
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getIdentity()
    {
        return 'openfilter_coexistence';
    }

    /**
     * @inheritDoc
     */
    public function isDisplayed()
    {
        return $this->detector->getActiveReplacement() !== null;
    }

    /**
     * @inheritDoc
     */
    public function getText()
    {
        return (string) __(
            'OpenFilter is off on the storefront: %1 provides the layered navigation. Disable one of them.',
            (string) $this->detector->getActiveReplacement()
        );
    }

    /**
     * @inheritDoc
     */
    public function getSeverity()
    {
        return self::SEVERITY_NOTICE;
    }
}
