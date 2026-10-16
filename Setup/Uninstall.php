<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenFilter\Setup;

use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

/**
 * `bin/magento module:uninstall Iranimij_OpenFilter` (Composer installs): removes OpenFilter's tables, settings and
 * the record of its data patches, so a later install starts with fresh defaults. Magento's own attributes and
 * search index fields are untouched (the next reindex drops OpenFilter's fields).
 */
class Uninstall implements UninstallInterface
{
    /** Child tables first so foreign keys never block a drop. */
    public const TABLES = [
        'openfilter_option_image',
        'openfilter_filter_category',
        'openfilter_option_slug',
        'openfilter_attribute_slug',
        'openfilter_attribute_store',
        'openfilter_attribute',
        'openfilter_builtin_store',
        'openfilter_builtin',
    ];

    /**
     * @inheritDoc
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context): void
    {
        $connection = $setup->getConnection();
        foreach (self::TABLES as $table) {
            $name = $setup->getTable($table);
            if ($connection->isTableExists($name)) {
                $connection->dropTable($name);
            }
        }
        $connection->delete($setup->getTable('core_config_data'), ['path LIKE ?' => 'openfilter/%']);
        $connection->delete($setup->getTable('patch_list'), ['patch_name LIKE ?' => 'Iranimij\\\\OpenFilter\\\\%']);
    }
}
