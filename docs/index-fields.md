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
