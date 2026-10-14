<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Console\Command;

use Iranimij\OpenFilter\Api\SlugRepositoryInterface;
use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\ResourceModel\SlugStorage;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Console\Cli;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento openfilter:slugs:generate: URL names for options that have none (new options, imported catalogs).
 * Existing names never change unless --overwrite is given, because published filter URLs depend on them.
 */
class SlugsGenerate extends Command
{
    /**
     * @param FilterConfigProvider $provider
     * @param SlugRepositoryInterface $slugs
     * @param SlugStorage $storage
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resource
     * @param CacheType $cache
     */
    public function __construct(
        private readonly FilterConfigProvider $provider,
        private readonly SlugRepositoryInterface $slugs,
        private readonly SlugStorage $storage,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resource,
        private readonly CacheType $cache
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('openfilter:slugs:generate')
            ->setDescription('Creates URL names for filter options that have none')
            ->addOption('attribute', 'a', InputOption::VALUE_REQUIRED, 'Only this attribute code')
            ->addOption('store', 's', InputOption::VALUE_REQUIRED, 'Only this store view code (default: all)')
            ->addOption('overwrite', null, InputOption::VALUE_NONE, 'Generate every name again from the labels (changes published URLs)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would change, write nothing');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $only = $input->getOption('attribute');
        $storeCode = $input->getOption('store');
        $storeId = is_string($storeCode) && $storeCode !== '' ? (int) $this->storeManager->getStore($storeCode)->getId() : null;
        $overwrite = (bool) $input->getOption('overwrite');
        $dryRun = (bool) $input->getOption('dry-run');
        $attributes = [];
        foreach ($this->provider->getForStore(Store::DEFAULT_STORE_ID) as $config) {
            if (!$config->isBuiltin() && in_array($config->getInputType(), ['select', 'multiselect'], true)
                && (!is_string($only) || $only === '' || $config->getCode() === $only)) {
                $attributes[(int) $config->getAttributeId()] = $config->getCode();
            }
        }
        if ($attributes === []) {
            $output->writeln('<error>No option filter matches.</error>');

            return Cli::RETURN_FAILURE;
        }
        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        $total = 0;
        try {
            foreach ($attributes as $attributeId => $code) {
                $before = $this->storage->optionSlugRows($attributeId);
                $this->slugs->regenerate($attributeId, $storeId, $overwrite);
                $changed = $this->changes($before, $this->storage->optionSlugRows($attributeId));
                $total += $changed;
                $output->writeln(sprintf('%s: %d URL name(s) %s', $code, $changed, $dryRun ? 'would change' : 'written'));
            }
        } catch (\Throwable $e) {
            $connection->rollBack();
            $this->cache->clean();
            throw $e;
        }
        if ($dryRun) {
            $connection->rollBack();
            $this->cache->clean(); // regenerate() refreshed the URL name cache inside the transaction
        } else {
            $connection->commit();
        }
        $output->writeln(sprintf(
            '<info>%d URL name(s) %s.</info>',
            $total,
            $dryRun ? 'would change (dry run, nothing written)' : 'written'
        ));

        return Cli::RETURN_SUCCESS;
    }

    /**
     * @param array<int, array<int, string>> $before store id => option id => slug
     * @param array<int, array<int, string>> $after
     * @return int rows added, changed or removed
     */
    private function changes(array $before, array $after): int
    {
        $count = 0;
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $storeId) {
            $old = $before[$storeId] ?? [];
            $new = $after[$storeId] ?? [];
            $count += count(array_diff_assoc($new, $old)) + count(array_diff_key($old, $new));
        }

        return $count;
    }
}
