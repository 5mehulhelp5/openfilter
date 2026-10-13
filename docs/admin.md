# Admin

Everything lives under **Catalog › OpenFilter**: **Filters** (one list of attribute filters and built-in filters) and **Settings** (Stores › Configuration › Iranimij › OpenFilter). Access is controlled by the ACL resources *Catalog › OpenFilter › Filters* and *Stores › Settings › Configuration › Iranimij › OpenFilter*.

## First run

After installing, the admin shows a notice: how many filterable attributes OpenFilter found and which defaults it set (colours as swatches, sizes as text swatches, price as a slider, other lists as checkbox lists, Yes/No attributes as switches). Rating, In stock, On sale and New start switched off. **Review defaults** opens the Filters grid and dismisses the notice.

## Filters grid

One row per filter: label, code, type (attribute or built-in), how it looks, several values, options before "Show more", search box threshold, "Google may index", position, storefront (shown or hidden).

- **Inline edit**: click a row, change the common fields, Save. Each row is saved on its own; a row with a value that does not fit (for example swatches on an attribute without swatches) reports its error and the other rows are still saved.
- **Mass actions**: *Show on the storefront*, *Hide from the storefront*, *Reset to defaults* (labels per store view and URL names are kept). Hiding a filter never changes the attribute's own "Use in Layered Navigation" setting, so it stays in the grid.
- Row actions: **Edit** and **Why is it not showing?**

## Filter form

| Section | Settings |
|---|---|
| Why is this filter not showing? | the checks below, for any store view and category page |
| How it looks | display type: only the types that fit the attribute are offered (swatches need swatch data, sliders a number attribute, the on/off switch a Yes/No attribute or a list with two options) |
| How it behaves | show on the storefront, several values, options before "Show more" (0 = all), search box from N options (0 = never), open when the page loads (automatic / open / closed), option order, hide when only one option is left, position (empty = the attribute's position) |
| SEO | "Let Google index pages filtered by this" (single-value pages only), URL name of the filter, URL names of the options per store view |
| Labels per store view | a label for all store views and one per store view (empty = the attribute's label) |

Saving is all or nothing: settings, labels and URL names are written in one transaction.

### URL names

URL names are what shoppers and search engines see in filter URLs (`?color=blue,red`). They are created once from the option labels and **never change because a label changed** (see [Filter URLs](url-format.md)).

- Option URL names are checked as one set per store view before anything is written: lowercase letters, digits and single dashes, at most 64 characters, no name used twice. Two options can swap names in one save.
- A store view field left empty uses the name for all store views; a store view row is only stored where the name differs.
- **Generate again from the label** (per option) creates a fresh name from the option's label in every store view when you save. This changes published URLs; old URLs with option IDs keep working, old names do not.

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

The same checks power `bin/magento openfilter:doctor` (1.0).

## Settings

| Setting | Path | Default | Scope |
|---|---|---|---|
| Enable OpenFilter | `openfilter/general/enabled` | Yes | store view |
| Update results without reloading the page | `openfilter/general/ajax` | Yes | store view |
| Debug mode | `openfilter/general/debug` | No | global |
| Filters open by default | `openfilter/display/expanded_groups` | 3 | store view |
| Price slider step | `openfilter/display/price_step` | 1 | store view |
| Ask search engines not to follow filter links from this many active values | `openfilter/seo/nofollow_threshold` | 2 | store view |
| Most filter values active at once | `openfilter/seo/max_active_values` | 5 | store view |
| How filter counts are computed | `openfilter/search/count_strategy` | Automatic | global |

Numbers are validated in the browser and on the server. Changing a setting, a filter, a label or a URL name marks the full page cache as invalid: refresh it in System › Cache Management, as with other catalog settings.
