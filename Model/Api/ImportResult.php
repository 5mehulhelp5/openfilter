<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Api;

use Iranimij\OpenFilter\Api\Data\ImportResultInterface;

class ImportResult implements ImportResultInterface
{
    /**
     * @param bool $applied
     * @param string[] $changes
     * @param string[] $errors
     */
    public function __construct(
        private readonly bool $applied = false,
        private readonly array $changes = [],
        private readonly array $errors = []
    ) {
    }

    /**
     * @inheritDoc
     */
    public function isApplied(): bool
    {
        return $this->applied;
    }

    /**
     * @inheritDoc
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * @inheritDoc
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
