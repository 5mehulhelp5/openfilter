# Index fields

OpenFilter's built-in filters (rating, in stock, on sale, new) are filterable and countable in the search engine, so they need fields in the product search documents. M0 verified the core extension points on Magento 2.4.7, 2.4.8 and 2.4.9 (`Test/Integration/Index/IndexHooksTest`, run on every matrix leg).

## Extension points

| Need | Core hook | Notes |
|---|---|---|
| Add values to each product document | virtual type **`additionalFieldsProviderForElasticsearch`** (`Magento\AdvancedSearch\Model\Adapter\DataMapper\AdditionalFieldsProvider`), argument `fieldsProviders` | The argument of `Magento\Elasticsearch\Model\Adapter\BatchDataMapper\ProductDataMapper`, shared by the OpenSearch and Elasticsearch 8 engines. Core registers `categories` and `prices` here. A provider implements `AdditionalFieldsProviderInterface::getFields(array $productIds, $storeId)` and returns fields per product id, batched. |
| Declare the field types in the index mapping | virtual type **`elasticsearch5FieldProvider`** (`Magento\Elasticsearch\Model\Adapter\FieldMapper\Product\CompositeFieldProvider`), argument `providers` (core: `static`, `dynamic`) | Used by the OpenSearch and Elasticsearch 8 mappers. OpenFilter adds one provider for `of_rating` (integer), `of_in_stock` (integer), `of_on_sale_<group>_<website>` (integer), `of_news_from` / `of_news_to` (date). |

## What reindexes a product's search document

The `catalogsearch_fulltext` mview subscribes to the product entity tables (`catalog_product_entity`, `_int`, `_decimal`, `_datetime`, `_varchar`, `_text`), category and website assignments, links, and **`catalogrule_product_price`**. Consequences:

| Field | Reindexed by core? | M1 action |
|---|---|---|
| `of_on_sale_*` | Price, special price and dates (entity tables) and catalog price rules: **yes**. A special price whose date window opens or closes changes nothing in these tables. | Plugin after the price indexer's row/full actions: reindex the same products in the search index (Magento refreshes the price index for date windows daily). |
| `of_news_from` / `of_news_to` | Yes (datetime table). Compared to "now" in the store timezone at query time, so no daily reindex is needed. | none |
| `of_rating` | **No**: `review_entity_summary` is not subscribed. | Plugin after review aggregation: reindex the product's search document. |
| `of_in_stock` | Not through the mview (`cataloginventory_stock_item` is not subscribed). With MSI, `Magento_InventoryCatalogSearch` reindexes on salability changes (`UpdateFulltextIndexOnProductSalabilityChange`). Legacy stock: product save. | Legacy stock: plugin after the stock indexer's row action. |

## Built-in filters (M1)

| Filter | Index field | Source | Applied and counted |
|---|---|---|---|
| Rating "N stars and up" | `of_rating` (0–100, store view) | `review_entity_summary` | `of_rating ≥ N × 20`; one range aggregation for 4, 3, 2 and 1 stars |
| In stock | `of_in_stock` (0/1) | MSI stock of the website (`inventory_stock_<id>`), legacy `cataloginventory_stock_status` without MSI | term; only when "Display out of stock products" is on (otherwise every listed product is in stock and the filter is hidden) |
| On sale | `of_on_sale_<group>_<website>` (1) | price index: final price below the regular price | term on the current customer group's field |
| New | `of_news_from`, `of_news_to` (dates, open ends filled) | "Set Product as New" dates | window compared to "now" in the store's timezone at query time |

The mapper adds each switched-on built-in as a clause (when selected) and a count aggregation, and registers it as a facet filter, so SingleQuery and PerAttribute exclude a built-in's own filter from its count like any option filter. Everything stays inside the one search request.

Reindex triggers (`Plugin\Index`): review aggregation → product; partial price indexer runs (special price windows refreshed by cron) and legacy stock indexer runs → same products in `catalogsearch_fulltext`.
