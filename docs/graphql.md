# GraphQL (headless)

Headless storefronts (PWA Studio, Next.js, Vue Storefront, your own) get OpenFilter's layered navigation from Magento's `products` query: add the field `openfilter_aggregations`.

```graphql
query ($filter: ProductAttributeFilterInput) {
  products(filter: $filter, pageSize: 24) {
    total_count
    items { name sku }
    openfilter_aggregations {
      code filter_key label url_param display_mode multiselect logic tooltip position
      options { value label url_value count selected depth from to swatch { type value } }
      range { min max from to }
    }
  }
}
```

```json
{ "filter": { "category_id": { "eq": "21" }, "color": { "in": ["49", "50"] }, "size": { "eq": "167" } } }
```

## What you get

- The store view's filters (send the `Store` header) in the merchant's order, only where they show (**Where it shows**, by the `category_id` or `category_uid` of the query), with the labels, tooltips and URL names of that store view.
- **Counts that help shoppers pick:** each filter is counted without its own picks, so with Black and Blue picked, Green still shows how many products it would add. Filters set to *Products must match all of the picked values* (`logic: "and"`) count with their picks. Values without products are left out unless picked.
- **Picks:** `selected` marks the values of the `filter` argument; `value` is what to send back (`filter_key` names the argument: the attribute code, `price`, or `category_id`).
- **Sliders** (`display_mode` `slider` or `from_to`): `range.min` / `range.max` of the products found without the slider's own pick, `range.from` / `range.to` the picked bounds.
- **Ranges** (`display_mode` `ranges`): options `from`–`to`, [from, to), with counts. Send one as `price: { from: "10", to: "30" }`.
- **Categories:** the categories below the queried category (the store's root category without one), as many levels as the category tree is set to (`depth` 1 = a child), with Magento's counts. Pick one by querying its `category_id`.
- **Swatches:** `swatch.type` `color` (value `#hex`), `image` (value a URL) or `text`.

Everything comes from the same search request as the products: with the *single query* strategy OpenFilter adds no request to Magento's own (measured in the integration tests).

## Landing pages (1.2)

`openfilter_landing_page(url_key: "black-tops")` returns an enabled [landing page](storefront.md#landing-pages-12) of the store view (`Store` header), or null: `url`, `title`, `heading`, `meta_title`, `meta_description`, `description` (HTML with widgets and variables rendered), `robots`, `category_uid` and `filters { code values }`. Query `products` with that `category_uid` and the filters (`code: { in: values }`, ranges as `from`–`to`) to list its products:

```graphql
{ openfilter_landing_page(url_key: "black-tops") { heading description category_uid filters { code values } } }
```

## Insights (1.2)

Where [insights](insights.md) are on, a headless storefront can report filter use with the same beacon as the Hyvä storefront: `POST /openfilter/insights/collect` with `{"s": store code, "c": category ID, "f": {code: [value, …]}, "b": bucket}`, keys from `openfilter_aggregations { code }` and values from `options { value }`. See [Insights › Headless storefronts](insights.md#headless-storefronts).

## Good to know

- Prices and ranges are in the units of Magento's `price` filter (the price index), not converted to the display currency.
- Magento's `aggregations` field of the same query counts the same way (it is the same search). Queries without `openfilter_aggregations` are unchanged.
- The built-in **rating**, **in stock**, **on sale** and **new** filters depend on the shopper's session on the storefront and are not part of GraphQL yet.
- The storefront picks several categories on one page (`?cat=21,25`); Magento's `category_id` argument lists one category per query, so a headless page picks one category at a time.
- GraphQL is optional: OpenFilter does not require Magento's GraphQL modules (`composer suggest`), and a store without them is unaffected.

A complete page that uses only this field and plain JavaScript is in [`examples/headless`](https://github.com/iranimij/openfilter/tree/main/examples/headless).
