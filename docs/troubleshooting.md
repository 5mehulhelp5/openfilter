# Troubleshooting

## A filter does not show

Run `bin/magento openfilter:doctor -f <attribute code> -c <category id>`, or open the filter in **Catalog › OpenFilter › Filters** and use **Why is this filter not showing?**. Both list each condition with its fix: the filter is shown, the attribute is used in layered navigation (category and search pages separately), its field is in the search index, the category is an anchor, products in it have a value, and there is more than one option.

## Counts look wrong

Counts come from the search engine. After bulk changes outside the admin, run `bin/magento indexer:reindex catalogsearch_fulltext`. OpenFilter's counts are disjunctive: the options of a filter you already use are counted as if that filter were not set, so "Blue 12" next to a selected "Red" means 12 products if you add blue.

## Log and debug mode

OpenFilter writes to `var/log/openfilter.log`. Switch on **Stores › Configuration › Iranimij › OpenFilter › General › Debug mode** (it clears the full page cache) to get:

- log lines: the count strategy chosen for the search engine, why OpenFilter steps aside (another layered navigation), each request cut by the "most values" rule;
- attributes in the storefront markup, readable in the browser's inspector:

| Element | Attributes |
|---|---|
| `#of-filters` | `data-of-debug-strategy`, `data-of-debug-engine`, `data-of-debug-values` (active values) |
| each filter group | `data-of-debug-filter` (filter key; "(core)" for Magento's own filters), `data-of-debug-display` |
| each option link | `data-of-debug-option` (option id or range), `data-of-debug-count` |

Keep debug mode off in production: the attributes are part of cached pages.

## Pages show old filters after a change

Settings and URL names are cached in the `openfilter` cache type, cleaned on every save. The full page cache is marked invalid when a storefront setting changes; refresh it in **System › Cache Management** or with `bin/magento cache:clean full_page`.

## A theme changed the filter markup

OpenFilter's templates can be overridden like any Hyvä template (`Iranimij_OpenFilterHyva/templates/...` in your theme); keep the regions and `data-of-*` attributes listed in [Storefront](storefront.md) so AJAX updates keep working.
