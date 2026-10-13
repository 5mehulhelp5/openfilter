# Upgrade and uninstall

## Upgrade

```bash
composer update iranimij/openfilter-hyva-bundle --with-dependencies
bin/magento setup:upgrade
```

OpenFilter follows semantic versioning: `Api/` interfaces, the URL format, the storefront markup contract ([Storefront](storefront.md)) and the CLI JSON format change only in a major version. See the CHANGELOG of each package.

## Switch off

**Stores › Configuration › Iranimij › OpenFilter › General › Enable OpenFilter = No** per store view. Magento's own layered navigation (and Hyvä's templates) return at once; settings and URL names stay.

## Uninstall

For a Composer install:

```bash
bin/magento module:uninstall Iranimij_OpenFilterHyva Iranimij_OpenFilter
bin/magento indexer:reindex catalogsearch_fulltext
```

This removes the packages, OpenFilter's six tables (`openfilter_*`), its settings (`openfilter/*`) and the record of its data patches, so a later install starts with fresh defaults. Keep the export of your settings first: `bin/magento openfilter:config:export -o openfilter.json`. The reindex removes OpenFilter's fields from the product search index. Magento's attributes and their "Use in Layered Navigation" flags are never changed by OpenFilter.

`iranimij/module-base` stays installed while other iranimij modules use it.
