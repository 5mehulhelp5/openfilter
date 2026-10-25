# Insights (1.2)

Insights show what shoppers do with your filters: which filters and values they use, which filters nobody uses, which filter combinations find no products, which filters are worth letting search engines index, and a filter order by use. They are off by default.

## Turn it on

*Stores › Configuration › OpenFilter › Insights*:

| Setting | Path | Default | Scope |
|---|---|---|---|
| Collect filter insights | `openfilter/insights/enabled` | No | store view |
| Keep the counts for this many days | `openfilter/insights/retention_days` | 90 | global |

With it on, filter pages (category pages, search results, landing pages, the All Products page) send a small report after they load and after each filter change. A cron job (`openfilter_insights_purge`, nightly) deletes the days older than the retention, whether insights are on or off, so Magento's cron must run.

## The Insights page

*Catalog › OpenFilter › Insights* (ACL *Insights*). Pick a store view and a period (7, 30 or 90 days).

| Section | What it shows |
|---|---|
| Most used filters | Filters by the pages they were used on, and their share of all filter pages viewed |
| Most used values | The 20 values picked most, with their store view labels |
| Filters nobody used | Active filters no page used in the period: hide them or move them down |
| Filter combinations without products | The 20 combinations shoppers asked for that found nothing: products to stock or tag |
| Filters worth letting search engines index | Option filters used on at least 10 % of the pages (and at least 20 times) that are not marked *Let Google index pages filtered by this* |
| Suggested filter order | Active filters by use, most used first; hidden filters keep their place at the end |

**Apply to Sort filters** opens *Sort filters* with the suggested order. Nothing is saved until you click **Save order** there. Below 50 page views the page says there is not enough data yet.

## What is sent

One JSON body per page load and per filter change, sent with `navigator.sendBeacon` to `POST /openfilter/insights/collect` of the store view:

```json
{"s": "default", "c": 21, "f": {"color": ["49", "50"], "price": ["20-50"]}, "b": 2}
```

| Key | Meaning |
|---|---|
| `s` | Store view code; it must be the store view the request reaches |
| `c` | Category ID, `0` on search results |
| `f` | Active filters: filter key (attribute code, `price`, `rating`, `stock`, `sale`, `new`, `category`) and values (option IDs, ranges `from-to`, `1` for on/off filters, category IDs) |
| `b` | Products found, as a bucket: `0` none, `1` 1–10, `2` 11–100, `3` more, `-1` unknown |

The endpoint answers `204` when it counted the beacon, `400` when anything does not match the store view (an unknown or inactive filter, a value no filter link could carry, more than 20 values, a body over 4 KB, another store view's code) and `404` where insights are off. It needs no form key, so pages from the full page cache can send it.

Counts are added per day, store view, category, filter and value: one view, one count per filter, one per value (ranges count only as their filter) and, for a page without products, its filter combination. There is no row per request.

## Headless storefronts

A headless storefront can send the same beacon: POST the JSON above to `https://<store>/openfilter/insights/collect` (with `___store=<code>` where the store view is not in the address), after each products query with filters. Take the keys from `openfilter_aggregations { code }` and the values from `options { value }` ([GraphQL](graphql.md)); ranges as `from-to`. Send it only when insights are on for the store view; a `404` means they are off.

## Privacy

- Nothing about the shopper is sent or stored: no cookie, no customer or session ID, no IP address, no user agent, no referrer, no time of day (only the day).
- The script reads only the page (`#of-state` and the product count) and sets no cookie or storage of its own. The endpoint reads only the body.
- Browsers send the cookies of the shop with any request to it, as with every other request of the page; OpenFilter does not read them, and Magento's visitor log skips the OpenFilter route.
- Counts cannot be traced back to a person, so no consent banner entry is needed for them under the usual reading of the GDPR; check with your own advisor if in doubt.
- Anyone can post a beacon, as anyone can load your pages: values are checked against your filters, so made-up values are refused, but counts can be inflated by bots that run JavaScript.
- `bin/magento module:uninstall Iranimij_OpenFilter` drops the table `openfilter_insight_daily`.
