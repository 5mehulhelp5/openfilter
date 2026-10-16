<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Landing;

/**
 * The landing page of the current request (1.2), set by its controller, read by the head, the content block and the
 * filter links.
 */
class CurrentPage
{
    private ?LandingPage $page = null;

    /**
     * @param LandingPage|null $page
     * @return void
     */
    public function set(?LandingPage $page): void
    {
        $this->page = $page;
    }

    /**
     * @return LandingPage|null
     */
    public function get(): ?LandingPage
    {
        return $this->page;
    }
}
