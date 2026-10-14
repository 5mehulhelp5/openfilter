<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Admin;

use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Magento\Framework\FlagManager;
use Magento\Framework\Notification\MessageInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;

/**
 * First-run notice (08 · UX Spec §5): what OpenFilter set up on install, with a "Review defaults" link that opens the
 * Filters grid and dismisses the notice.
 */
class FirstRunNotice implements MessageInterface
{
    public const FLAG = 'openfilter_defaults_reviewed';

    /**
     * @param FilterConfigProvider $provider
     * @param FlagManager $flags
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly FilterConfigProvider $provider,
        private readonly FlagManager $flags,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getIdentity()
    {
        return 'openfilter_first_run';
    }

    /**
     * @inheritDoc
     */
    public function isDisplayed()
    {
        return !$this->flags->getFlagData(self::FLAG);
    }

    /**
     * @inheritDoc
     */
    public function getText()
    {
        $attributes = count(array_filter(
            $this->provider->getForStore(Store::DEFAULT_STORE_ID),
            static fn ($config): bool => !$config->isBuiltin()
        ));

        return (string) __(
            'OpenFilter found %1 filterable attributes and set sensible defaults: colours as swatches, sizes as text swatches, price as a slider, other lists as checkbox lists, yes/no attributes as switches. Rating, In stock, On sale and New are off until you switch them on. <a href="%2">Review defaults</a>',
            $attributes,
            $this->url->getUrl('openfilter/filter/index', ['reviewed' => 1])
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
