# Hyvä compatibility tracker entry (draft)

Draft for the Hyvä compatibility tracker (submitted by Iman after the 1.0.0 release). Not linked from the
documentation navigation.

| Field | Value |
|---|---|
| Module | OpenFilter (layered navigation) |
| Vendor | Iman Aboheydary (`iranimij`) |
| Composer package | `iranimij/openfilter-hyva-bundle` (core `iranimij/openfilter`, Hyvä package `iranimij/openfilter-hyva`) |
| Repository | https://github.com/iranimij/openfilter |
| Licence | MIT, free |
| Compatibility | Native: Hyvä support is built in through `iranimij/openfilter-hyva`; no `hyva-themes/magento2-*-compat` package is needed |
| Tested Hyvä versions | default theme 1.4.2 and 1.5.2 (every change); 1.3.x best effort |
| Tested Magento versions | 2.4.7 (OpenSearch and Elasticsearch 8), 2.4.8, 2.4.9; PHP 8.2 to 8.4 |
| Hyvä Checkout | Not applicable (category and search result pages) |
| Notes | Replaces the layered navigation templates on category and search pages with its own (filter groups as native disclosures, real links underneath); one Alpine component registered with the CSP helper (no inline handlers, no `eval`, no inline styles), works with Alpine's CSP build; AJAX fetches the same URL as a normal page load, so Varnish and the built-in page cache serve filter clicks; no Tailwind build needed (one static stylesheet, theme tokens through CSS custom properties); right-to-left stores mirror; steps aside when ElasticSuite or Adobe Live Search provides the layered navigation |

## Short description

Free layered navigation for Magento 2 and Hyvä on the OpenSearch Magento ships: multi-select filters with correct
counts in one search request, AJAX that the page cache serves and Hyvä's CSP allows, price and decimal sliders,
rating / in stock / on sale / new filters, a mobile drawer with "Show N products", SEO rules (readable URLs,
canonical, robots, nofollow, a limit on combined values) and an admin with diagnostics. Every filter works without
JavaScript.
