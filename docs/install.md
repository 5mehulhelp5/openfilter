# Install

## Requirements

| | Supported |
|---|---|
| Magento Open Source / Adobe Commerce / Mage-OS | 2.4.7, 2.4.8, 2.4.9 (Mage-OS: current release) |
| PHP | 8.2, 8.3, 8.4 (the pairs Magento supports) |
| Search engine | OpenSearch 2.x; Elasticsearch 8 on 2.4.7 |
| Hyvä | default theme 1.4.x and 1.5.x (1.3 best effort), `hyva-themes/magento2-theme-module` ^1.3 |

The store must already run Hyvä: OpenFilter's Hyvä package requires Hyvä's theme module, which Composer installs from the Hyvä repositories your project already has configured.

## Install with Composer

From the Magento root:

```bash
composer require iranimij/openfilter-hyva-bundle
bin/magento setup:upgrade
bin/magento indexer:reindex catalogsearch_fulltext
```

The bundle installs `iranimij/openfilter` (search engine integration, admin, SEO, CLI), `iranimij/openfilter-hyva` (Hyvä templates, AJAX, mobile drawer) and the shared base module `iranimij/module-base`. In production mode also run `bin/magento setup:di:compile` and `bin/magento setup:static-content:deploy`, as after any module install.

The reindex adds OpenFilter's fields (rating, in stock, on sale, new) to the product search index. Price, stock, review and date changes keep them current through Magento's own indexers.

## First steps

1. Open **Catalog › OpenFilter › Filters**. OpenFilter found your filterable attributes and set defaults (colour as a swatch multi-select, size as a text swatch multi-select, price as a slider, other selects as lists, Yes/No attributes as switches). Click **Review defaults** to dismiss the notice.
2. Open a category page: filters are multi-select links with counts, results update without a reload, and the URL stays readable (`?color=red,blue&size=m`).
3. Run `bin/magento openfilter:doctor`: it checks the search engine, the cache and every filter, and says how to fix what it finds.

Settings: **Stores › Configuration › Iranimij › OpenFilter**. Every filter: **Catalog › OpenFilter › Filters** (see [Admin](admin.md)).

## Packages

| Package | Contents |
|---|---|
| `iranimij/openfilter-hyva-bundle` | the three packages below, one require |
| `iranimij/openfilter` | core: search integration, filters, URLs and SEO, admin, CLI, PHP API |
| `iranimij/openfilter-hyva` | Hyvä storefront: templates, one Alpine component, structural CSS |
| `iranimij/module-base` | shared base of the iranimij modules (settings tab, installed modules page) |

A Luma storefront package is planned for 1.2. The core alone works with Magento's own layered navigation templates (multi-select by navigation, correct counts, URL and SEO rules); the AJAX layer, mobile drawer, sliders and swatch templates come with a storefront package.
