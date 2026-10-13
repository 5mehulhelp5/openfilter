# Facet counts

Multi-select filters need **disjunctive counts**: with "Red" selected, the colour filter must still show how many products are blue, green or black, while every other filter counts only red products. Magento computes every aggregation under every active filter (conjunctive counts), so a multi-select colour filter would show 0 next to every other colour. OpenFilter fixes this behind one interface, `Iranimij\OpenFilter\Api\FacetCountStrategyInterface`, with two strategies.

## Strategies

| Strategy | Code | Search requests | How |
|---|---|---|---|
| **SingleQuery** (preferred) | `single_query` | **same as core** | Active facet filters move from the query to `post_filter` (they still narrow the products), and each aggregation is wrapped in a `filter` aggregation that holds every active facet filter except the ones on its own attribute. One round trip. |
| **PerAttribute** (fallback) | `per_attribute` | core + 1 per attribute with an active filter | The main request stays as Magento builds it. For each attribute with an active filter, one extra request (no hits, only that attribute's aggregation) runs without that attribute's own filter. |

Setting: *Stores › Configuration › Iranimij › OpenFilter › Search engine › How filter counts are computed* (`openfilter/search/count_strategy`): **Automatic** (default), or a fixed strategy. Automatic uses SingleQuery on OpenSearch, Elasticsearch 8 and Elasticsearch 7, and PerAttribute for any other engine. Debug mode writes the choice to the log once per process.

Which filters are "facet filters": term filters in the `must` part of the layered navigation requests (`catalog_view_container`, `quick_search_container`) whose attribute also has a term aggregation. The category drill-down (`category_ids`), `visibility`, price ranges and search text stay as Magento builds them. Other search requests (GraphQL, widgets) are not touched.

## Where it hooks in

Verified on Magento 2.4.7-p10, 2.4.8-p5 and 2.4.9 (source of `magento/module-elasticsearch` 101.0.7-p5, 101.0.8 and 101.0.9, `module-open-search` 100.4.1–100.4.3, `module-elasticsearch-8` 101.0.x):

1. `Magento\OpenSearch\SearchAdapter\Mapper` and `Magento\Elasticsearch8\SearchAdapter\Mapper` both delegate to **`Magento\Elasticsearch\ElasticAdapter\SearchAdapter\Mapper::buildQuery()`**, identical on all three versions. An after-plugin (`Plugin\Search\MapperPlugin`) collects the facet filters from the request, builds their clauses with Magento's own filter builder, and lets the strategy rewrite the query.
2. Both adapters then call **`Magento\Elasticsearch\SearchAdapter\Aggregation\Builder::setQuery()`** and **`::build($request, $rawResponse)`**, also identical on all three versions. `Plugin\Search\AggregationBuilderPlugin` hands `build()` the response with disjunctive counts (SingleQuery unwraps its filter aggregations; PerAttribute runs its count requests there), and hands `setQuery()` the **original** query, because core's price-interval data provider re-runs that query and must see exactly what core would have sent.

Term filters with several values become a `terms` query in core already (`Filter\Builder\Term`), so `addFieldToFilter('color', [49, 50])` is an OR within the attribute without further changes.

## Evidence (M0 spike)

`Test/Integration/Search/DisjunctiveCountsTest` builds its own catalog (three filterable select attributes, 36 products in an anchor category, 4 outside it), then for each strategy and four selections (one value; two values of one attribute; two attributes; three attributes with multiple values) compares the counts of **every** attribute with brute force: one engine request per option, with OpenFilter switched off, counting products matching every active filter except the attribute's own plus that option. It also measures the requests each strategy adds over core on the engine itself (`_nodes/stats` `query_total`).

| Environment | SingleQuery counts = brute force | PerAttribute counts = brute force | Requests added over core | Run |
|---|---|---|---|---|
| 2.4.9, PHP 8.3, OpenSearch 2.5 (local fixture) | 4/4 selections, all attributes | 4/4 | SingleQuery **0**; PerAttribute = active attributes (1, 1, 2, 3) | local, 2026-10-13 |
| 2.4.9, PHP 8.3, OpenSearch 2.5 (local fixture), **M1 catalog**: simple + configurable products, 3 customer groups (Wholesale tier prices, price range), 2 store views (German view hides one product), category and search pages | 4/4 scenarios, all 3 attributes; also equal to the catalog's own PHP model | 4/4 | — | local, 2026-10-13 (`M1CountsTest`) |
| 2.4.9 / 8.3, 8.4, OpenSearch 2.12 | pending | pending | pending | CI full matrix (after hours) |
| 2.4.8-p5 / 8.2, 8.3, 8.4, OpenSearch 2.12 | pending | pending | pending | CI full matrix |
| 2.4.7-p10 / 8.3, OpenSearch 2.12 | pending | pending | pending | CI full matrix |
| 2.4.7-p10 / 8.2, Elasticsearch 8.17 | pending | pending | pending | CI full matrix |

**Decision (preliminary, confirmed when the matrix is green):** SingleQuery on every supported version and engine; PerAttribute stays as the automatic fallback for unknown engines and as a manual setting. If a matrix leg fails for SingleQuery only, that engine/version is mapped to PerAttribute in `etc/di.xml` (`autoByEngine`) and recorded here.

Test note (M1): core's fulltext collection takes `Api\Search\SearchCriteriaBuilder` from `ObjectManager::get()`, one instance per process whose filters are never cleared, and the search indexer caches its attribute list per process. A storefront request builds one layer collection, so shops are not affected, but tests that build many collections or create attributes after an earlier reindex must reset those shared instances (`Test/Integration/Catalog/SearchIndexReset`, and a fresh criteria builder per collection). The M0 brute-force test runs with that reset too.

Note on the request budget: core's price filter already costs extra requests (its interval data provider re-runs the query for statistics). SingleQuery adds none on top. M1 replaces the core price statistics with a `stats` aggregation inside the same request (02 · Architecture §3).
