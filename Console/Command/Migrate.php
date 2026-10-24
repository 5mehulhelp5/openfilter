<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Console\Command;

use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Migration\OldUrlResolver;
use Iranimij\OpenFilter\Model\Migration\VendorModules;
use Laminas\Uri\Http as HttpUri;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento openfilter:migrate (1.2): moving from another layered navigation module. Lists the other filter modules
 * still enabled, how many filters OpenFilter took from Magento's attribute settings, whether old filter URLs
 * redirect; turns the redirects on or off; checks a list of old URLs (one per line, for example exported from
 * Google Search Console) without changing anything.
 */
class Migrate extends Command
{
    /**
     * @param VendorModules $vendorModules
     * @param FilterConfigProvider $configProvider
     * @param OldUrlResolver $resolver
     * @param StoreManagerInterface $storeManager
     * @param WriterInterface $configWriter
     * @param TypeListInterface $cacheTypes
     * @param File $files
     */
    public function __construct(
        private readonly VendorModules $vendorModules,
        private readonly FilterConfigProvider $configProvider,
        private readonly OldUrlResolver $resolver,
        private readonly StoreManagerInterface $storeManager,
        private readonly WriterInterface $configWriter,
        private readonly TypeListInterface $cacheTypes,
        private readonly File $files
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('openfilter:migrate')
            ->setDescription('Moving from another layered navigation module: what is left to do, old URL redirects')
            ->addOption('store', 's', InputOption::VALUE_REQUIRED, 'Store view code (default: the default store view)')
            ->addOption('redirects', null, InputOption::VALUE_REQUIRED, 'Turn the old URL redirects on or off (on|off)')
            ->addOption('filter-keys', null, InputOption::VALUE_REQUIRED, 'Path parts of the old URLs before the filters, comma-separated (for example shopby)')
            ->addOption('urls', null, InputOption::VALUE_REQUIRED, 'A file of old URLs, one per line: show where each would redirect');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $code = $input->getOption('store');
        $store = is_string($code) && $code !== ''
            ? $this->storeManager->getStore($code)
            : $this->storeManager->getDefaultStoreView();
        if (!$store instanceof Store) {
            $output->writeln('<error>No store view.</error>');

            return Cli::RETURN_FAILURE;
        }
        $storeId = (int) $store->getId();
        if (!$this->write($input, $output, is_string($code) && $code !== '' ? $storeId : null)) {
            return Cli::RETURN_FAILURE;
        }

        $output->writeln('<comment>Other filter modules</comment>');
        $modules = $this->vendorModules->enabled();
        foreach ($modules as $module => $product) {
            $output->writeln(sprintf('  %s (%s) is enabled', $module, $product));
        }
        $output->writeln($modules === []
            ? '  none enabled'
            : '  Switch them off so one module filters the pages: bin/magento module:disable ' . implode(' ', array_keys($modules)));

        $output->writeln('<comment>Filters</comment>');
        $output->writeln(sprintf(
            '  %d filters on in %s. Attribute filters start from Magento\'s attribute settings (Use in Layered Navigation, position); the other module\'s own settings are not read, review them under Catalog > OpenFilter > Filters.',
            count($this->configProvider->getActive($storeId)),
            $store->getCode()
        ));

        $output->writeln('<comment>Old filter URLs</comment>');
        $output->writeln($this->resolver->isEnabled($storeId)
            ? '  redirect to OpenFilter URLs (301) in ' . $store->getCode()
            : '  are not redirected in ' . $store->getCode() . ' (turn on with --redirects=on)');

        $file = $input->getOption('urls');

        return is_string($file) && $file !== '' ? $this->check($file, $store, $output) : Cli::RETURN_SUCCESS;
    }

    /**
     * Writes --redirects and --filter-keys (default scope, or the store view given with --store).
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param int|null $storeId
     * @return bool false on an invalid value
     */
    private function write(InputInterface $input, OutputInterface $output, ?int $storeId): bool
    {
        $changes = [];
        $redirects = $input->getOption('redirects');
        if (is_string($redirects)) {
            if (!in_array($redirects, ['on', 'off'], true)) {
                $output->writeln('<error>--redirects is on or off.</error>');

                return false;
            }
            $changes[OldUrlResolver::XML_PATH_ENABLED] = $redirects === 'on' ? '1' : '0';
        }
        $keys = $input->getOption('filter-keys');
        if (is_string($keys)) {
            $changes[OldUrlResolver::XML_PATH_FILTER_KEYS] = implode(',', array_filter(array_map('trim', explode(',', $keys))));
        }
        foreach ($changes as $path => $value) {
            $storeId === null
                ? $this->configWriter->save($path, $value)
                : $this->configWriter->save($path, $value, ScopeInterface::SCOPE_STORES, $storeId);
        }
        if ($changes !== []) {
            $this->cacheTypes->cleanType('config');
            $this->cacheTypes->cleanType('full_page');
            $this->storeManager->reinitStores();
        }

        return true;
    }

    /**
     * @param string $file
     * @param Store $store
     * @param OutputInterface $output
     * @return int
     */
    private function check(string $file, Store $store, OutputInterface $output): int
    {
        try {
            $lines = array_values(array_filter(
                array_map('trim', explode("\n", $this->files->fileGetContents($file))),
                static fn (string $line): bool => $line !== ''
            ));
        } catch (FileSystemException $e) {
            $output->writeln(sprintf('<error>Cannot read %s.</error>', $file));

            return Cli::RETURN_FAILURE;
        }
        $base = trim((string) (new HttpUri($store->getBaseUrl()))->getPath(), '/');
        $found = 0;
        $output->writeln('<comment>Old URLs in ' . $file . '</comment>');
        foreach ($lines as $line) {
            $url = $line;
            $uri = new HttpUri($url);
            $path = trim((string) $uri->getPath(), '/');
            if ($base !== '' && str_starts_with($path . '/', $base . '/')) {
                $path = trim(substr($path, strlen($base)), '/');
            }
            $query = $uri->getQueryAsArray();
            $target = $this->resolver->resolve($path, array_map('strval', array_filter($query, 'is_scalar')), (int) $store->getId());
            $found += (int) ($target !== null);
            $output->writeln(sprintf('  %s -> %s', $url, $target ?? '(not an old filter URL: stays as it is)'));
        }
        $output->writeln(sprintf('<info>%d of %d would redirect.</info>', $found, count($lines)));

        return Cli::RETURN_SUCCESS;
    }
}
