# OpenFilter headless example

One static page that shows OpenFilter's layered navigation from Magento's GraphQL API: `index.html`, `app.js` (plain JavaScript, no build, no dependencies) and `style.css`.

- Filters in the merchant's order with counts, swatches, tooltips, "all must match" hints, category levels, slider bounds and price ranges, all from `products { openfilter_aggregations }` (see [docs/graphql.md](../../docs/graphql.md)).
- The URL keeps the storefront's format (`?category=21&color=black,blue&price=10-30`), so a headless page and the Magento page share links.
- Options: `?category=<id>`, `?store=<store view code>` (sent as the `Store` header), `?endpoint=<GraphQL URL>`.

Try it: copy the folder next to your storefront (same origin as `/graphql`), or point `?endpoint=` to a store that allows your origin. The OpenFilter dev environment serves it at `/openfilter-headless/index.html`.
