<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Console\Command;

use Iranimij\OpenFilter\Model\Transfer\Importer;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento openfilter:config:import: applies an export file all or nothing; --dry-run lists the changes.
 */
class ConfigImport extends Command
{
    /**
     * @param Importer $importer
     * @param File $file
     */
    public function __construct(
        private readonly Importer $importer,
        private readonly File $file
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('openfilter:config:import')
            ->setDescription('Applies a JSON file from openfilter:config:export, all or nothing')
            ->addArgument('file', InputArgument::REQUIRED, 'JSON file')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Check the file and list the changes, write nothing');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = (string) $input->getArgument('file');
        try {
            $json = $this->file->fileGetContents($path);
        } catch (FileSystemException $e) {
            $output->writeln(sprintf('<error>Cannot read %s.</error>', $path));

            return Cli::RETURN_FAILURE;
        }
        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $output->writeln(sprintf('<error>%s is not valid JSON: %s</error>', $path, json_last_error_msg()));

            return Cli::RETURN_FAILURE;
        }
        $dryRun = (bool) $input->getOption('dry-run');
        $result = $this->importer->import($data, $dryRun);
        foreach ($result['changes'] as $change) {
            $output->writeln('  ' . $change);
        }
        if ($result['errors'] !== []) {
            foreach ($result['errors'] as $error) {
                $output->writeln('<error>' . $error . '</error>');
            }
            $output->writeln('<error>Nothing was imported.</error>');

            return Cli::RETURN_FAILURE;
        }
        $output->writeln(sprintf(
            '<info>%d change(s) %s.</info>',
            count($result['changes']),
            $dryRun ? 'found (dry run, nothing written)' : 'imported'
        ));

        return Cli::RETURN_SUCCESS;
    }
}
