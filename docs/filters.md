# Filters

## Settings

Every filterable attribute and every built-in filter has settings (`Api\FilterConfigRepositoryInterface`, Catalog › OpenFilter › Filters: see [Admin](admin.md)): display type, multi-select, options before "Show more", search box threshold, expanded, option order (position, name, count), hide a filter with one option, indexable for search engines, position, label per store view.

On install OpenFilter stores sensible starting settings: colour-like (visual) swatches → swatch multi-select, text swatches → swatch multi-select, other selects → list multi-select, decimals → slider, Yes/No → toggle. Price and category are on; rating, in stock, on sale and new are off until switched on.

Storefront requests read the settings and URL names from the **OpenFilter** cache type (Stores › Cache Management): no database query for them. Saving settings cleans that cache and marks the full page cache invalid.

## Behaviour

- **Multi-select (OR within an attribute, AND across attributes)** with disjunctive counts: options of an active filter are counted as if that filter were not set ([Facet counts](facet-counts.md)).
- **"Products must match all of the picked values" (1.1)** per option filter: for features a product has several of (a jacket that is *Hooded* and *Full Zip*), the picked values combine with AND. Counts then say how many products have that value as well, so they shrink as values are added; the page still costs one search request.
- Options without products are hidden (Magento's "Filterable (with results)"), unless selected. A filter with a single option is hidden unless something is selected.
- **Sliders** (price, decimal attributes): bounds come from the same search request, ignore the slider's own range, and follow the customer group's prices; price bounds are shown in the display currency and tax display (default product tax class).
- **Ranges (1.1)** (price, decimal attributes, display type *Ranges*): a list such as €0.00–€39.99, €40.00–€59.99, €60.00 and above. A range includes its lower bound and stops before the upper one. Your own list (`0-40,40-60,60-`) or automatic ranges: about five, with round bounds (steps of 1, 2 or 5 × 10ⁿ) in the currency shoppers see. With "Shoppers can pick several values" on, picked ranges combine with *any of* (`?price=0-40,60-`). Counts come from the same search request and ignore the price pick, like every other filter.
- **Category: drill-down or tree (1.1).** *Checkbox list* is Magento's drill-down (a link leaves for the subcategory). *Category tree* lists the categories below the page's category (the store's root on search pages) down to 1–5 levels with counts; picking one narrows the same page (`?cat=21`), several combine with *any of* (`?cat=21,25`) when "Shoppers can pick several values" is on. Counts come from the same search request and ignore the category picks. The tree is read from OpenFilter's cache (rebuilt when a category is saved, moved or deleted), so the page needs fewer SQL queries than Magento's drill-down.
- **Built-in filters**: rating ("N stars and up"), in stock (only when out-of-stock products are displayed), on sale (per customer group and website), new ("Set Product as New" window, evaluated at request time). See [Index fields](index-fields.md).

## Coexistence

When ElasticSuite or Adobe Live Search provides the layered navigation, OpenFilter switches its storefront part off and the admin shows a notice: never two filter systems on one page.

## Budget

A category page with any number of active filters costs **one search request** with the SingleQuery strategy (products, counts, slider bounds, built-in counts) and **no extra MySQL queries** compared with Magento (measured in `Test/Integration/Performance/BudgetTest`).
