<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Api\Data;

/**
 * What a config import did or would do (1.1).
 *
 * @api
 */
interface ImportResultInterface
{
    /**
     * @return bool true when the changes were saved (never for a dry run or with errors)
     */
    public function isApplied(): bool;

    /**
     * @return string[] one line per change
     */
    public function getChanges(): array;

    /**
     * @return string[] why nothing was saved
     */
    public function getErrors(): array;
}
