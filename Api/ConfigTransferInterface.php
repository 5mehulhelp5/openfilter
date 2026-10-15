<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Api;

use Iranimij\OpenFilter\Api\Data\ImportResultInterface;

/**
 * OpenFilter's settings as one JSON document (the format of `openfilter:config:export`), over REST (1.1).
 *
 * @api
 */
interface ConfigTransferInterface
{
    /**
     * @return string the JSON document
     */
    public function export(): string;

    /**
     * All or nothing: either every change is applied or none. A dry run reports the changes and errors only.
     *
     * @param string $config the JSON document
     * @param bool $dryRun
     * @return \Iranimij\OpenFilter\Api\Data\ImportResultInterface
     */
    public function import(string $config, bool $dryRun = true): ImportResultInterface;
}
