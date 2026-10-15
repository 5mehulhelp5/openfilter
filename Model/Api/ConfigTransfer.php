<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Api;

use Iranimij\OpenFilter\Api\ConfigTransferInterface;
use Iranimij\OpenFilter\Api\Data\ImportResultInterface;
use Iranimij\OpenFilter\Api\Data\ImportResultInterfaceFactory;
use Iranimij\OpenFilter\Model\Transfer\Exporter;
use Iranimij\OpenFilter\Model\Transfer\Importer;

class ConfigTransfer implements ConfigTransferInterface
{
    /**
     * @param Exporter $exporter
     * @param Importer $importer
     * @param ImportResultInterfaceFactory $resultFactory
     */
    public function __construct(
        private readonly Exporter $exporter,
        private readonly Importer $importer,
        private readonly ImportResultInterfaceFactory $resultFactory
    ) {
    }

    /**
     * @inheritDoc
     */
    public function export(): string
    {
        return (string) json_encode($this->exporter->export(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @inheritDoc
     */
    public function import(string $config, bool $dryRun = true): ImportResultInterface
    {
        $data = json_decode($config, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->resultFactory->create(['errors' => [
                (string) __('The configuration is not valid JSON: %1', json_last_error_msg()),
            ]]);
        }
        $result = $this->importer->import($data, $dryRun);

        return $this->resultFactory->create($result);
    }
}
