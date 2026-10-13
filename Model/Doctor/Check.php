<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Doctor;

/**
 * One diagnostic check: what was checked, whether it passed, and how to fix it (shared by the admin panel and the
 * openfilter:doctor command).
 */
class Check
{
    /**
     * @param string $code stable identifier (tests, CLI output)
     * @param string $title what is checked, as a statement that should be true
     * @param bool $ok
     * @param string $detail what was found
     * @param string|null $fix what to do when it fails
     * @param string|null $url where to fix it (admin URL)
     */
    public function __construct(
        public readonly string $code,
        public readonly string $title,
        public readonly bool $ok,
        public readonly string $detail = '',
        public readonly ?string $fix = null,
        public readonly ?string $url = null
    ) {
    }

    /**
     * @return array{code: string, title: string, ok: bool, detail: string, fix: string|null, url: string|null}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'title' => $this->title,
            'ok' => $this->ok,
            'detail' => $this->detail,
            'fix' => $this->ok ? null : $this->fix,
            'url' => $this->ok ? null : $this->url,
        ];
    }
}
