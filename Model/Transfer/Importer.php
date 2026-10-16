<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Model\Transfer;

use Iranimij\OpenFilter\Api\FilterConfigRepositoryInterface;
use Iranimij\OpenFilter\Api\SlugRepositoryInterface;
use Iranimij\OpenFilter\Model\Config\FilterConfig;
use Iranimij\OpenFilter\Model\Config\FilterConfigEditor;
use Iranimij\OpenFilter\Model\Config\FilterConfigProvider;
use Iranimij\OpenFilter\Model\Cache\Type as CacheType;
use Iranimij\OpenFilter\Model\Url\OptionSlugBatch;
use Iranimij\OpenFilter\Model\Url\UrlCodec;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;

/**
 * Applies an export file all or nothing: the file is checked first (Schema), then every filter is written in one
 * database transaction through the same services as the admin form (settings, labels, URL names as one set per
 * attribute); any refused value rolls everything back. A dry run does the same and always rolls back.
 */
class Importer
{
    /**
     * @param Exporter $exporter
     * @param Schema $schema
     * @param FilterConfigRepositoryInterface $repository
     * @param FilterConfigEditor $editor
     * @param SlugRepositoryInterface $slugs
     * @param OptionSlugBatch $optionSlugs
     * @param FilterConfigProvider $provider
     * @param UrlCodec $codec
     * @param ResourceConnection $resource
     * @param CacheType $cache
     * @param LoggerInterface $logger the openfilter channel
     */
    public function __construct(
        private readonly Exporter $exporter,
        private readonly Schema $schema,
        private readonly FilterConfigRepositoryInterface $repository,
        private readonly FilterConfigEditor $editor,
        private readonly SlugRepositoryInterface $slugs,
        private readonly OptionSlugBatch $optionSlugs,
        private readonly FilterConfigProvider $provider,
        private readonly UrlCodec $codec,
        private readonly ResourceConnection $resource,
        private readonly CacheType $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param mixed $data decoded JSON
     * @param bool $dryRun
     * @return array{applied: bool, changes: string[], errors: string[]}
     */
    public function import(mixed $data, bool $dryRun): array
    {
        $codes = $this->exporter->storeCodes();
        $errors = $this->schema->validate($data, $this->exporter->knownFilters(), array_values($codes));
        if ($errors !== []) {
            return ['applied' => false, 'changes' => [], 'errors' => $errors];
        }
        /** @var array{filters: array<string, array<string, mixed>>} $data */
        $changes = $this->schema->diff($this->exporter->export(), $data);
        $storeIds = array_flip($codes);
        $connection = $this->resource->getConnection();
        $connection->beginTransaction();
        try {
            foreach ($data['filters'] as $key => $filter) {
                $this->apply((string) $key, $filter, $storeIds);
            }
        } catch (LocalizedException $e) {
            $connection->rollBack();
            $this->forget();

            return ['applied' => false, 'changes' => $changes, 'errors' => [$e->getMessage()]];
        } catch (\Throwable $e) {
            $connection->rollBack();
            $this->forget();
            throw $e;
        }
        if ($dryRun) {
            $connection->rollBack();
            $this->forget();
        } else {
            $connection->commit();
            $this->logger->info(sprintf('OpenFilter config import: %d change(s) applied.', count($changes)));
        }

        return ['applied' => !$dryRun, 'changes' => $changes, 'errors' => []];
    }

    /**
     * @param string $key
     * @param array<string, mixed> $filter
     * @param array<string, int> $storeIds store code => id
     * @return void
     * @throws LocalizedException
     */
    private function apply(string $key, array $filter, array $storeIds): void
    {
        $config = $this->repository->getByCode($key);
        if (!$config instanceof FilterConfig) {
            throw new LocalizedException(__('%1: this filter cannot be edited.', $key));
        }
        $settings = (array) ($filter['settings'] ?? []);
        try {
            $edited = $this->editor->apply($config, $this->editorValues($settings));
            if (array_key_exists('depends_values', $settings) && $edited->getDependsOn() !== null) {
                $parent = $this->repository->getByCode((string) $edited->getDependsOn());
                $wanted = count($edited->getDependsValues());
                $ids = $this->exporter->parentValues($edited, $parent instanceof FilterConfig ? $parent : null, false);
                if (count($ids) !== $wanted) {
                    throw new LocalizedException(__('Some values to wait for are not options of "%1".', $edited->getDependsOn()));
                }
                $edited->setDependsValues($ids);
            }
            $saved = $this->repository->save($edited);
        } catch (LocalizedException $e) {
            throw new LocalizedException(__('%1: %2', $key, $e->getMessage()), $e);
        }
        foreach ((array) ($filter['labels'] ?? []) as $code => $label) {
            $this->repository->saveLabel($key, $storeIds[$code], $label === '' ? null : (string) $label);
        }
        foreach ((array) ($filter['tooltips'] ?? []) as $code => $tooltip) {
            $this->repository->saveTooltip($key, $storeIds[$code], $tooltip === '' ? null : (string) $tooltip);
        }
        $attributeId = $saved->isBuiltin() ? null : $saved->getAttributeId();
        if ($attributeId === null) {
            return;
        }
        foreach ((array) ($filter['url_name'] ?? []) as $code => $name) {
            if ($name !== $this->slugs->getAttributeSlug($attributeId, $storeIds[$code])) {
                $this->slugs->saveAttributeSlug($attributeId, $storeIds[$code], (string) $name);
            }
        }
        $optionIds = array_flip($this->exporter->optionKeys($attributeId));
        $names = [];
        foreach ((array) ($filter['options'] ?? []) as $label => $byStore) {
            foreach ((array) $byStore as $code => $name) {
                $names[$optionIds[(string) $label]][$storeIds[$code]] = (string) $name;
            }
        }
        if ($names !== []) {
            try {
                $this->optionSlugs->save($attributeId, $names);
            } catch (LocalizedException $e) {
                throw new LocalizedException(__('%1: %2', $key, $e->getMessage()), $e);
            }
        }
    }

    /**
     * Schema values in the editor's posted form.
     *
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function editorValues(array $settings): array
    {
        $values = [];
        foreach ($settings as $name => $value) {
            $values[$name] = match (true) {
                is_bool($value) => $value ? '1' : '0',
                $value === null => '',
                is_array($value) => implode(',', array_map('strval', $value)),
                default => (string) $value,
            };
        }

        return $values;
    }

    /**
     * Drops cached copies after a rollback: saves inside the transaction refreshed OpenFilter's cache with values
     * the database no longer has.
     *
     * @return void
     */
    private function forget(): void
    {
        $this->cache->clean();
        $this->provider->reset();
        $this->codec->reset();
    }
}
