# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/): `Api/` interfaces, the filter URL format, the storefront markup contract (`docs/storefront.md`) and the CLI JSON format change only in a major version.

## [1.0.0-rc1] - 2026-10-15

First release: layered navigation for Magento 2.4.7–2.4.9 on OpenSearch (Elasticsearch 8 on 2.4.7).

### Added
- Filters: multi-select per attribute (OR) and single-select, disjunctive counts in one search request (`Api\FacetCountStrategyInterface`: `single_query` with `post_filter` and filtered aggregations, `per_attribute` fallback, chosen per engine), hide empty options (selected ones stay), hide single-option filters, sort by position, name or count.
- Price and decimal sliders whose bounds come from the same search request, in the display currency and tax display mode.
- Built-in filters rating ("4 stars and up"), in stock (MSI-aware), on sale (per customer group and website) and new (dates at query time), as search index fields with reindex triggers for reviews, prices and stock.
- Readable store-scoped URL names (transliteration incl. German umlauts, collisions, never changed silently), `Api\UrlCodecInterface`, 301 to the canonical filter URL, option-id fallback for old links.
- SEO: robots (`NOINDEX,FOLLOW` except single values of filters marked indexable), canonical (indexable pages themselves, others the category; one tag, also when Magento's category canonical is off), `rel="nofollow"` from 2 active values, at most 5 values per URL (302 with `X-Robots-Tag: noindex`).
- Admin: Catalog › OpenFilter › Filters grid (inline edit, show/hide/reset), filter form (how it looks, how it behaves, SEO with URL names per store view, labels per store view), OpenFilter fieldset on the attribute page, "Why is this filter not showing?" panel, first-run notice, settings under Stores › Configuration › Iranimij › OpenFilter.
- `Api\FilterConfigRepositoryInterface` (incl. `getList(SearchCriteria)`), `Api\SlugRepositoryInterface`, `Api\FilterStateInterface`.
- Command line: `openfilter:doctor`, `openfilter:slugs:generate`, `openfilter:config:export`, `openfilter:config:import` (JSON schema 1, dry run, all or nothing).
- View models for theme packages (`FilterBlock`, `State`, `Slider`, `Swatch`, `Debug`) and the structural stylesheet `openfilter.css` (of-* classes, theme tokens, logical properties).
- Coexistence: OpenFilter steps aside when ElasticSuite or Adobe Live Search provides the layered navigation, with an admin notice.
- Log channel `var/log/openfilter.log`, debug mode (`data-of-debug-*` attributes, strategy choice and redirects logged), uninstall class, English and German translations.
