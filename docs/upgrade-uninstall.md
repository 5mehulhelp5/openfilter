# Upgrade and uninstall

## Upgrade

```bash
composer update iranimij/openfilter-hyva-bundle --with-dependencies
bin/magento setup:upgrade
bin/magento setup:static-content:deploy   # production mode; in developer mode clean pub/static instead
bin/magento cache:flush
```

Deploy the static content after every update: the stylesheet keeps its URL until the static content version changes, so without a deploy returning shoppers keep the previous `openfilter.css` from their browser cache (new parts of the page would show unstyled).

OpenFilter follows semantic versioning: `Api/` interfaces, the URL format, the storefront markup contract ([Storefront](storefront.md)) and the CLI JSON format change only in a major version. See the CHANGELOG of each package.

## Switch off

**Stores › Configuration › Iranimij › OpenFilter › General › Enable OpenFilter = No** per store view. Magento's own layered navigation (and Hyvä's templates) return at once; settings and URL names stay.

## Uninstall

For a Composer install:

```bash
bin/magento module:uninstall Iranimij_OpenFilterHyva Iranimij_OpenFilter
bin/magento indexer:reindex catalogsearch_fulltext
```

This removes the packages, OpenFilter's six tables (`openfilter_*`), its settings (`openfilter/*`) and the record of its data patches, so a later install starts with fresh defaults. Keep the export of your settings first: `bin/magento openfilter:config:export -o openfilter.json`. The reindex removes OpenFilter's fields from the product search index. Magento's attributes and their "Use in Layered Navigation" flags are never changed by OpenFilter; "Use in Search Results Layered Navigation" changes only when you use the grid's *Show on search pages* / *Hide on search pages*.

`iranimij/module-base` stays installed while other iranimij modules use it.
