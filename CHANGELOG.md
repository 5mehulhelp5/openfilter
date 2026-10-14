# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Module skeleton `Iranimij_OpenFilter` on the shared core `iranimij/module-base`: configuration section `openfilter` under the `iranimij` tab, ACL, English and German translations.
- Disjunctive facet counts for layered navigation (`Api\FacetCountStrategyInterface`, `Api\Data\FacetFilterInterface`): strategy `single_query` (post_filter + filtered aggregations, no extra search request) and `per_attribute` (one extra request per active attribute), chosen by `openfilter/search/count_strategy` (automatic by default). Plugins on Magento's shared Elasticsearch/OpenSearch query mapper and aggregation builder; GraphQL and other search requests are not touched.
- Docs: Hyvä integration notes (1.4.2 and 1.5.2), facet counts (strategies and evidence), index fields (core hooks for the built-in filters).
