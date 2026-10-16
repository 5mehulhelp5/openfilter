# Admin

Everything lives under **Catalog › OpenFilter**: **Filters** (one list of attribute filters and built-in filters) and **Settings** (Stores › Configuration › Iranimij › OpenFilter). Access is controlled by the ACL resources *Catalog › OpenFilter › Filters* and *Stores › Settings › Configuration › Iranimij › OpenFilter*.

## First run

After installing, the admin shows a notice: how many filterable attributes OpenFilter found and which defaults it set (colours as swatches, sizes as text swatches, price as a slider, other lists as checkbox lists, Yes/No attributes as switches). Rating, In stock, On sale and New start switched off. **Review defaults** opens the Filters grid and dismisses the notice.

## Filters grid

One row per filter: label, code, type (attribute or built-in), how it looks, several values, options before "Show more", search box threshold, "Google may index", order (its place on the storefront: 1, 2, 3 …), position (the number behind the order), products must match (any / all), where it shows, storefront (shown or hidden).

Above the grid, warnings name what shoppers would trip over: two active filters with the same name (rename one in *Label and tooltip*, or hide one), and search result pages without attribute filters.

- **Inline edit**: click a row, change the common fields, Save. Each row is saved on its own; a row with a value that does not fit (for example swatches on an attribute without swatches) reports its error and the other rows are still saved.
- **Mass actions**: *Show on the storefront*, *Hide from the storefront*, *Show on search pages*, *Hide on search pages*, *Reset to defaults* (labels per store view and URL names are kept). Hiding a filter never changes the attribute's own "Use in Layered Navigation" setting, so it stays in the grid. *Show / Hide on search pages* set the attribute's "Use in Search Results Layered Navigation" (built-in filters always show on search pages).
- Row actions: **Edit** and **Why is it not showing?**

## Sort filters (1.1)

Catalog › OpenFilter › Filters › **Sort filters** lists every filter in its storefront order. Drag a filter by its handle, or use **Move up** / **Move down** (keyboard friendly: focus stays on the button and the new place is announced). **Find a filter** narrows the list by name; **Save order** stays at the bottom of the window and writes the positions 10, 20, 30… in one go (the gaps leave room for a filter later); the grid's *Position* column and the form show the same numbers.

## Filter form

| Section | Settings |
|---|---|
| Why is this filter not showing? | the checks below, for any store view and category page |
| How it looks | display type: only the types that fit the attribute are offered (swatches need swatch data, sliders and ranges a number attribute or price, the on/off switch a Yes/No attribute or a list with two options, the category tree the category filter); **Ranges** (price and number filters, 1.1): a list such as `0-50,50-100,100-`, empty = automatic; **Category tree levels** (category filter, 1.1): 1–5 |
| How it behaves | show on the storefront, several values, **Products must match** (option filters, 1.1): *any* of the picked values (more products) or *all* of them (fewer products, for features a product has several of), options before "Show more" (0 = all), search box from N options (0 = never), open when the page loads (automatic / open / closed), option order, hide when only one option is left, **In the quick filter bar** (1.2: its values may appear as links above the products), position (empty = the attribute's position) |
| Where it shows (1.1) | *In every category* (default), *Only in these categories* or *In every category except these*, with Magento's category picker; a category counts with the categories below it; search result pages always show the filter. **Show only after a pick in** (1.2): another filter this one waits for, and optionally **Only for these values** of it (empty = any value); filters that would wait for each other are refused |
| Option images (1.2) | an image per option for the *Images* display type (attributes with a list of options; edited with *All Store Views*); remove an image to show the label again |
| SEO | "Let Google index pages filtered by this" (single-value pages only), URL name of the filter, URL names of the options |
| Label and tooltip | the label for all store views (empty = the attribute's label) and a tooltip (a short plain-text hint shoppers open next to the filter name, 1.1), or the chosen store view's own label and tooltip |

Saving is all or nothing: settings, labels and URL names are written in one transaction.

### One store view at a time

The form edits one scope at a time, chosen with the store view switcher at the top of the page (the same switcher as on product and category pages), so it looks the same with 2 or 50 store views:

- **All Store Views** edits the settings (marked `[GLOBAL]`), the label for all store views and the URL names for all store views. **Reset to defaults** is offered only here.
- **A store view** (for example *Deutsch*) edits only that store view's label, the filter's URL name and the option URL names in that store view. The values for all store views are shown next to them, read only; settings are read only. Each option row also shows the option's label in that store view.
- An empty store view field shows *Same as for all store views* and follows the value for all store views. Clearing a field puts the store view back on the shared value.
- **Save** writes only the scope you are looking at and brings you back to it. Switching scope with unsaved changes asks first.

To edit many store views in one go, use `openfilter:config:export` / `openfilter:config:import` ([Import and export](import-export.md)).

### URL names

URL names are what shoppers and search engines see in filter URLs (`?color=blue,red`). They are created once from the option labels and **never change because a label changed** (see [Filter URLs](url-format.md)).

- Option URL names are checked as one set per store view before anything is written: lowercase letters, digits and single dashes, at most 64 characters, no name used twice. Two options can swap names in one save.
- A store view field left empty uses the name for all store views; a store view row is only stored where the name differs. This also holds for the filter's own URL name (for example `farbe` in a German store view instead of `color`).
- **Generate again from the label** (per option) creates a fresh name from the option's label when you save: in **All Store Views** for every store view, in a store view only for that store view. This changes published URLs; old URLs with option IDs keep working, old names do not.

## Attribute page

Stores › Attributes › Product › *attribute* › **Storefront Properties** has an **OpenFilter** fieldset for attributes that are filters: how it looks, several values, options before "Show more", search box threshold, URL name, "Let Google index". It is saved with the attribute (an invalid value stops the save). A link opens the full filter form for labels, option URL names and position.

Saving any product attribute refreshes OpenFilter's cache, so an attribute that becomes filterable appears on the storefront without a cache flush.

## Why is this filter not showing?

Each line is a condition the filter needs, with what was found and how to fix it. The first problem is usually the answer.

| Check | Fails when |
|---|---|
| OpenFilter is on for this store view | "Enable OpenFilter" is No |
| No other layered navigation is active | ElasticSuite or Adobe Live Search provides the layered navigation |
| The attribute is used in layered navigation | the attribute is not a filter at all |
| The filter is set to show on the storefront | it is hidden in the grid or the form |
| …on category pages / on search result pages | "Use in Layered Navigation" / "Use in Search Results Layered Navigation" is No |
| Every option has a URL name | options were imported without saving the attribute |
| Out-of-stock products are shown in the catalog | (In stock filter) "Display Out of Stock Products" is No |
| The search index has the field | the product index of the store view lacks the field: reindex `catalogsearch_fulltext` |
| *Category* shows layered navigation | the category is not an anchor or shows a static block only |
| Products in *category* match this filter | no visible product of the category has a value (counted by the search engine) |
| More than one option has products here | one option only and "Hide when only one option is left" is on |

The same checks power `bin/magento openfilter:doctor` (1.0). With a category picked, **Open this category on the storefront** (1.1) opens that category in the chosen store view in a new tab, to see the filter where shoppers do.

## Settings

| Setting | Path | Default | Scope |
|---|---|---|---|
| Enable OpenFilter | `openfilter/general/enabled` | Yes | store view |
| Update results without reloading the page | `openfilter/general/ajax` | Yes | store view |
| Debug mode | `openfilter/general/debug` | No | global |
| Filters open by default | `openfilter/display/expanded_groups` | 3 | store view |
| Price slider step | `openfilter/display/price_step` | 1 | store view |
| Where filters show on desktop (1.1) | `openfilter/display/layout` | Sidebar next to the products | store view |
| When results update on desktop (1.1) | `openfilter/display/apply_mode` | At once, with every click | store view |
| Keep the sidebar in view while scrolling (1.1) | `openfilter/display/sticky` | No | store view |
| More products (1.2) | `openfilter/display/pagination` | Page links (Magento's pager); or a "Load more" button, or as shoppers scroll | store view |
| Quick filter bar above the products: values shown (1.2) | `openfilter/display/quick_bar_size` | 0 (no bar) | store view |
| All Products page (1.2): on, URL key, title | `openfilter/all_products/enabled`, `url_key`, `title` | No, `all-products`, All products | store view |
| Ask search engines not to follow filter links from this many active values | `openfilter/seo/nofollow_threshold` | 2 | store view |
| Most filter values active at once | `openfilter/seo/max_active_values` | 5 | store view |
| Page title / meta description / H1 of filtered pages (1.1) | `openfilter/seo/meta_title`, `meta_description`, `h1` | empty (Magento's own) | store view |
| Add indexable filter pages to the XML sitemap (1.1) | `openfilter/sitemap/enabled`, `min_products`, `max_urls` | No, 1, 5000 | store view |
| How filter counts are computed | `openfilter/search/count_strategy` | Automatic | global |

Numbers are validated in the browser and on the server. Changing a setting, a filter, a label or a URL name marks the full page cache as invalid: refresh it in System › Cache Management, as with other catalog settings.
