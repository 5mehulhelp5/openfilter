# Benchmark

OpenFilter is measured against Magento's own layered navigation on the same store, with the same shopper scenarios, by a script anyone can run: [`bench/run.mjs`](https://github.com/iranimij/openfilter-dev-env/tree/main/bench) in the dev environment (`node bench/run.mjs`, or the E2E workflow with `bench=true`, which also fails when OpenFilter needs more engine requests than Magento or adds more than 15 ms).

## What is measured

| Measure | How |
|---|---|
| Search engine requests | OpenSearch `_nodes/stats` `query_total` around one uncached page load |
| Server time | median of 11 uncached loads (Varnish and the page cache skipped), full HTML |
| Page cache | a scripted session of filter clicks; how many of a second shopper's pages Varnish answers |
| OpenFilter's bytes | its inline script and stylesheet on a filtered page, gzipped |

Magento runs with Hyvä's templates; scenarios use the URLs each mode understands (Magento: option ids, one value per filter) in their canonical spelling.

## Results

Magento 2.4.9, Hyvä 1.4.2, OpenSearch 2, Luma sample data, Varnish; local Docker (Warden) on an Apple laptop, 2026-10-15. Absolute times depend on the machine; compare the columns.

| Scenario | Magento: engine requests · server time | OpenFilter: engine requests · server time |
|---|---|---|
| Category page (Women › Tops) | 4 · 189 ms | 1 · 171 ms |
| One filter (colour) | 4 · 173 ms | 1 · 171 ms |
| Three filters (colour, size, price) | 4 · 159 ms | 1 · 160 ms |
| Two values of one filter | not possible | 1 · 175 ms |
| Search page | 4 · 169 ms | 1 · 160 ms |
| Search with a filter | 4 · 170 ms | 1 · 165 ms |
| Second shopper's clicks served by Varnish | 5 of 5 | 6 of 6 |

OpenFilter's own bytes on a filtered page: script 6.0 KB, stylesheet 2.9 KB (gzipped). Lighthouse with OpenFilter on and off: same performance score (1.00), no layout shift added (the E2E workflow checks it on every run).

What the numbers say:

- **One search request per page, whatever is picked.** Magento asks the engine four times per page (the products and its price intervals); OpenFilter gets products, every filter's counts (each without its own picks, so shoppers see what another value adds) and the slider bounds from one request. With the *per attribute* strategy (older engines) it adds one request per other active filter.
- **No server time added**, while doing more: several values per filter, disjunctive counts, built-in filters, SEO tags.
- **Every filter click is a cacheable page.** The AJAX layer fetches the same URL a browser would, so Varnish serves repeat clicks and the back button.

The integration tests hold the same line on every Magento version in CI: 0 extra SQL queries and at most +15 ms server time per category page (measured −35 to −55 ms).

## Other modules

Paid modules are not installed or measured (OpenFilter is written clean room). Their public pages, checked on 2026-10-15, publish no measured page times or engine request counts:

| Module | What its public page says about speed |
|---|---|
| [Mirasvit Layered Navigation](https://mirasvit.com/magento-2-extensions/layered-navigation.html) | runs in production on catalogs of up to 600,000 SKUs; option counts are cached for up to an hour; "needs a properly sized server and a healthy OpenSearch instance to stay fast"; a changelog entry removes about 23 KB of JavaScript from pages with its widget |
| [Amasty Improved Layered Navigation](https://amasty.com/improved-layered-navigation-for-magento-2.html) | describes AJAX filtering as faster; no measured numbers found (the page refused automated reading) |
| [Mageplaza Layered Navigation](https://www.mageplaza.com/magento-2-layered-navigation-extension/) | describes AJAX filtering as faster; no measured numbers found |
| [ElasticSuite](https://github.com/Smile-SA/elasticsuite) | replaces Magento's search stack; no layered navigation timings published |

If a vendor publishes measurements, open an issue and the table will link them.
