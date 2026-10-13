# Other search and filter modules

OpenFilter works with the search engine Magento ships (OpenSearch, Elasticsearch 8 on 2.4.7). Two layered navigations on one page break each other, so OpenFilter steps aside when another one is active:

| Active module | OpenFilter |
|---|---|
| ElasticSuite (`Smile_ElasticsuiteCatalog`) | storefront off, admin notice |
| Adobe Live Search (`Magento_LiveSearchAdapter`, `Magento_LiveSearchProductListing`) | storefront off, admin notice |

Stepping aside means: no OpenFilter plugins on the search request, no OpenFilter templates, no URL redirects; the other module's layered navigation works as before. The admin shows a notice naming the module. `bin/magento openfilter:doctor` reports it too, together with any other module that replaces or wraps Magento's layered navigation classes.

OpenFilter does not replace Magento's search or relevance, and adds no index of its own: its fields live in Magento's product search index.

An ElasticSuite adapter (OpenFilter's AJAX and SEO rules on top of ElasticSuite's filters) is planned for 1.2.
