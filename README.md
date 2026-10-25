# OpenFilter: layered navigation for Magento 2 and Hyvä

[![CI](https://github.com/iranimij/openfilter/actions/workflows/ci.yml/badge.svg)](https://github.com/iranimij/openfilter/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/iranimij/openfilter-hyva-bundle.svg)](https://packagist.org/packages/iranimij/openfilter-hyva-bundle)
[![MIT](https://img.shields.io/badge/licence-MIT-blue.svg)](LICENSE)

**The layered navigation shops pay for, free.** Multi-select filters with correct counts, AJAX that Varnish can cache and Hyvä's Content Security Policy allows, sliders, rating / stock / sale / new filters, a mobile drawer and SEO rules, on the OpenSearch Magento already ships. MIT, no licence key, no paid tier.

![Filtering a Hyvä category page with OpenFilter: two colours and a style, results update in place, a chip removes a value, the Back button restores the previous filters](docs/assets/openfilter.gif)

- **One search request per filtered page.** Counts are disjunctive (the options of a filter you use are counted as if it were not set) and come from the same OpenSearch request as the products, the counts of every other filter and the slider bounds. No extra MySQL queries.
- **AJAX served from your page cache.** A filter click fetches the same URL a normal page load would, so Varnish or the built-in cache answers it. One small Alpine component (5.9 KB gzipped), registered with Hyvä's CSP helper: no inline handlers, no `eval`, no inline styles.
- **Works without JavaScript.** Every option is a real link, every slider a form: crawlers, screen readers and failed scripts get the same filters.

## Install

Requires Magento Open Source, Adobe Commerce or Mage-OS 2.4.7–2.4.9, PHP 8.2–8.4, OpenSearch (or Elasticsearch 8 on 2.4.7) and a Hyvä theme 1.4 or 1.5. From the Magento root:

<!-- install:start -->
```bash
composer require iranimij/openfilter-hyva-bundle
bin/magento setup:upgrade
bin/magento indexer:reindex catalogsearch_fulltext
```
<!-- install:end -->

In production mode also run `bin/magento setup:di:compile` and `bin/magento setup:static-content:deploy`. Details: [Install](docs/install.md).

## Quick start

1. **Catalog › OpenFilter › Filters** lists your filterable attributes and the built-in filters with sensible defaults: colour as a swatch multi-select, size as a text swatch multi-select, price as a slider, other selects as lists, Yes/No attributes as switches. Click **Review defaults**.
2. Open a category: filters update the results in place, the URL stays readable (`/women/tops-women.html?color=black,blue&size=m`), Back restores the previous filters and the scroll position.
3. Mark the filters whose single values deserve a search result (brand, colour) as **Let Google index pages filtered by this**. Everything else stays `NOINDEX,FOLLOW`.
4. Run `bin/magento openfilter:doctor` once: it checks the search engine, the cache and every filter, and says how to fix what it finds.

## What you get

| | |
|---|---|
| Filters | multi-select per attribute (OR, or "match all"), single-select option, swatches (colour, image, text), option images, lists with "Show more" and a search box, dropdowns, price and decimal sliders with from–to fields (display currency and tax aware) or ranges, rating "4 stars and up", in stock (MSI-aware), on sale (per customer group and website), new, category drill-down or tree; filters that show only after a pick in another; category, search result and All Products pages |
| Storefront (Hyvä 1.4 and 1.5) | AJAX with Back/Forward and scroll restore, chips and Clear all, mobile drawer with "Show 123 products" counted before you apply, sidebar or horizontal bar, apply button on desktop, quick filter bar, "Load more" or loading as shoppers scroll, keyboard and screen reader support (axe-clean), right-to-left, no layout shift, no Tailwind rebuild |
| SEO | readable store-scoped URL names (Grün → `gruen`), query or path URLs (`/women/tops-women/color-black.html`), one canonical URL per page (301), robots and canonical tags, `rel="nofollow"` beyond 2 active values, at most 5 values per URL (302), title/description/H1 templates, landing pages with their own head and text, an XML sitemap of indexable filter pages, 301s from other modules' old filter URLs |
| Admin | Filters grid with inline edit, a form per filter (how it looks, how it behaves, where it shows, SEO, labels per store view), Sort filters, landing pages, insights into how shoppers filter (opt-in, anonymous), a setup checklist, settings on the attribute page, "Why is this filter not showing?" |
| Operations | `openfilter:doctor`, `openfilter:migrate` (moving from another filter module), `openfilter:slugs:generate`, `openfilter:config:export` / `import` (JSON, dry run, all or nothing), GraphQL and REST, `var/log/openfilter.log`, debug mode |

Tested together with [OpenLabel](https://github.com/iranimij/openlabel) (free product labels): labels survive every filter update.

## Compared with paid modules

From each vendor's public product page and documentation on 2026-10-13; features change, so check before you decide. Product names belong to their owners; OpenFilter is not affiliated with any of them.

| | OpenFilter | Amasty Improved Layered Navigation | Mirasvit Layered Navigation | Mageplaza Layered Navigation | ElasticSuite |
|---|---|---|---|---|---|
| Price | free, MIT | $109–$579 per year | $199–$580 | $149–$399 (first year) | free, OSL |
| Search engine | Magento's OpenSearch | Magento's | Magento's | Magento's | its own search stack |
| Multi-select with correct counts | yes | yes | yes | yes | yes |
| AJAX on Hyvä with its CSP package | yes | stated as not supported | Hyvä support stated | Hyvä compatibility stated | no AJAX |
| Filters work without JavaScript | yes | not documented | not documented | not documented | yes (no AJAX) |
| Canonical and robots rules | yes | yes | yes | Pro edition | no |
| Limit on combined filter values | yes | not documented | yes | not documented | no |
| Rating, stock, sale, new filters | yes | yes | yes | Pro edition | no |

Paid modules sell landing pages, infinite scroll, SEO URLs and analytics in their higher tiers; OpenFilter 1.2 has them free: path URLs, landing pages, "Load more" and infinite scroll, a quick filter bar, option images, dependent filters, an All Products page and anonymous insights, plus [redirects of the old filter URLs](docs/migration.md) of Amasty, Mirasvit and Mageplaza when you switch. A Luma package and an ElasticSuite adapter follow in 1.3 ([roadmap](https://github.com/iranimij/openfilter/milestones)). How it compares on speed with Magento's own layered navigation: [benchmark](docs/benchmark.md).

## SEO notes

Faceted navigation can flood search engines with near-duplicate pages. OpenFilter's defaults keep every filtered page `NOINDEX,FOLLOW` except single values you mark, point canonicals at the category, stop crawlers from following links beyond 2 active values and redirect URLs with more than 5. The head is updated after every AJAX swap, so the browser always shows what a crawler sees for that URL. [SEO rules](docs/seo.md).

## Cache notes

Filtered pages are ordinary GET pages: Varnish and the built-in page cache store them per URL and customer group, exactly as any category page. Filter clicks fetch those cached URLs, and a response cache in the browser serves Back and Forward instantly. Filter settings and URL names live in their own cache type (`openfilter`), cleaned on save; storefront settings mark the full page cache for refresh. No private content, no extra cache rules.

## Documentation

[Install](docs/install.md) · [Filters](docs/filters.md) · [Admin](docs/admin.md) · [Storefront and theming](docs/storefront.md) · [Filter URLs](docs/url-format.md) · [SEO rules](docs/seo.md) · [Command line](docs/cli.md) · [Import and export](docs/import-export.md) · [Troubleshooting](docs/troubleshooting.md) · [Other search modules](docs/coexistence.md) · [Upgrade and uninstall](docs/upgrade-uninstall.md) · [CHANGELOG](CHANGELOG.md)

Site: <https://iranimij.github.io/openfilter/>

## Support

Issues are triaged within 7 days; there is no SLA. Report bugs and ideas in [GitHub issues](https://github.com/iranimij/openfilter/issues), security problems privately ([SECURITY.md](SECURITY.md)). Contributions are welcome: [CONTRIBUTING.md](CONTRIBUTING.md) (clean-room rule included).

## Licence

MIT, see [LICENSE](LICENSE) and [NOTICE](NOTICE). Not affiliated with, endorsed by or sponsored by Hyvä Themes B.V., Adobe Inc. or the Mage-OS Association.
