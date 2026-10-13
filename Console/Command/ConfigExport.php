<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Console\Command;

use Iranimij\OpenFilter\Model\Transfer\Exporter;
use Magento\Framework\Console\Cli;
use Magento\Framework\Filesystem\Driver\File;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento openfilter:config:export: filter settings, labels and URL names as JSON (stdout or a file).
 */
class ConfigExport extends Command
{
    /**
     * @param Exporter $exporter
     * @param File $file
     */
    public function __construct(
        private readonly Exporter $exporter,
        private readonly File $file
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('openfilter:config:export')
            ->setDescription('Writes filter settings, labels and URL names as JSON')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'File to write (default: standard output)');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = (string) json_encode(
            $this->exporter->export(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) . "\n";
        $path = $input->getOption('output');
        if (!is_string($path) || $path === '') {
            $output->write($json, false, OutputInterface::OUTPUT_RAW);

            return Cli::RETURN_SUCCESS;
        }
        $this->file->filePutContents($path, $json);
        $output->writeln(sprintf('<info>Written to %s.</info>', $path));

        return Cli::RETURN_SUCCESS;
    }
}
