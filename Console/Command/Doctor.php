<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Console\Command;

use Iranimij\OpenFilter\Model\Doctor\Report;
use Magento\Framework\Console\Cli;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento openfilter:doctor: what keeps filters from working, with the fix for each problem. Exits 1 when a
 * check fails (usable in deployment scripts).
 */
class Doctor extends Command
{
    private const LABELS = [
        Report::OK => '<info>OK  </info>',
        Report::WARN => '<comment>WARN</comment>',
        Report::FAIL => '<error>FAIL</error>',
    ];

    /**
     * @param Report $report
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly Report $report,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('openfilter:doctor')
            ->setDescription('Checks the search engine, settings and every filter, and says how to fix problems')
            ->addOption('store', 's', InputOption::VALUE_REQUIRED, 'Store view code (default: the default store view)')
            ->addOption('category', 'c', InputOption::VALUE_REQUIRED, 'Also check the filters on this category id')
            ->addOption('filter', 'f', InputOption::VALUE_REQUIRED, 'Only this filter (attribute or built-in code)')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'table or json', 'table');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $code = $input->getOption('store');
        $store = is_string($code) && $code !== ''
            ? $this->storeManager->getStore($code) : $this->storeManager->getDefaultStoreView();
        if ($store === null) {
            $output->writeln('<error>No default store view.</error>');

            return Cli::RETURN_FAILURE;
        }
        $category = $input->getOption('category');
        $filter = $input->getOption('filter');
        $sections = $this->report->run(
            (int) $store->getId(),
            is_numeric($category) ? (int) $category : null,
            is_string($filter) && $filter !== '' ? $filter : null
        );
        $passed = $this->report->passed($sections);
        if ($input->getOption('format') === 'json') {
            $data = ['store' => $store->getCode(), 'passed' => $passed];
            foreach ($sections as $section => $checks) {
                $data['sections'][$section] = array_map(
                    fn ($check): array => $check->toArray() + ['level' => $this->report->level($check)],
                    $checks
                );
            }
            $output->writeln((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

            return $passed ? Cli::RETURN_SUCCESS : Cli::RETURN_FAILURE;
        }
        $output->writeln(sprintf('<info>OpenFilter doctor, store view %s</info>', $store->getCode()));
        foreach ($sections as $section => $checks) {
            $output->writeln('');
            $output->writeln(sprintf('<comment>%s</comment>', $section));
            foreach ($checks as $check) {
                $row = $check->toArray();
                $output->writeln(sprintf(
                    '  %s %s%s',
                    self::LABELS[$this->report->level($check)],
                    $row['title'],
                    $row['detail'] !== '' ? ' — ' . $row['detail'] : ''
                ));
                if (!$row['ok'] && $row['fix'] !== null) {
                    $output->writeln('       Fix: ' . $row['fix']);
                }
            }
        }
        $output->writeln('');
        $output->writeln($passed
            ? '<info>No problems found (warnings describe choices, not faults).</info>'
            : '<error>Some checks failed.</error>');

        return $passed ? Cli::RETURN_SUCCESS : Cli::RETURN_FAILURE;
    }
}
