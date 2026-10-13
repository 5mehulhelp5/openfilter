<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Layer\Filter;

/**
 * A layer filter driven by OpenFilter's filter state (its items build canonical links).
 */
interface OpenFilterInterface
{
    /**
     * Provider key of the filter, or null when OpenFilter does not handle it on this request.
     *
     * @return string|null
     */
    public function getOpenFilterKey(): ?string;

    /**
     * @return bool
     */
    public function isMultiselect(): bool;
}
