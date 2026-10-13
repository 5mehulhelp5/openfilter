# Hyvä integration

What OpenFilter's Hyvä package hooks into, verified on 2026-10-13 against the OSL Hyvä default theme **1.4.2** (the fixture store) and **1.5.2** (source of the public `hyva-themes/magento2-default-theme` tag), both with `magento2-theme-module` 1.5.2. The Playwright spec `hyva-layered-nav.spec.ts` in [openfilter-dev-env](https://github.com/iranimij/openfilter-dev-env) pins the markup below, so a Hyvä change shows up as a failing test first.

## Blocks and templates

| Page | Block name | Class | Template (Hyvä) | Container |
|---|---|---|---|---|
| Category (anchor, "layered") | `catalog.leftnav` | `Magento\LayeredNavigation\Block\Navigation\Category` | `Magento_LayeredNavigation::layer/view.phtml` | `sidebar.main`, `before="-"` |
| Search results | `catalogsearch.leftnav` | `Magento\LayeredNavigation\Block\Navigation\Search` | same | `sidebar.main` (every page layout moves it there) |
| Active filters | `catalog.navigation.state` / `catalogsearch.navigation.state` (alias `state`) | `…\Navigation\State` | `layer/state.phtml` | child of the leftnav block |
| Filter options | `catalog.navigation.renderer` / `catalogsearch.navigation.renderer` (alias `renderer`) | `…\Navigation\FilterRenderer` | `layer/filter.phtml`; swatch attributes go through `Magento_Swatches::product/layered/renderer.phtml` | child of the leftnav block |

The layout files `Magento_LayeredNavigation/layout/catalog_category_view_type_layered.xml` and `catalogsearch_result_index.xml` declare the same blocks in 1.4.2 and 1.5.2 (only the copyright header differs). `catalog_category_view_type_layered_without_children.xml` is empty.

**Consequence for OpenFilter:** the Hyvä package sets its own templates on these blocks by name (layout `hyva_catalog_category_view.xml` and `hyva_catalogsearch_result_index.xml`) and never edits Hyvä's files. Themes can override `Iranimij_OpenFilterHyva::layer/*.phtml` as usual.

## Markup differences 1.4 → 1.5

| | Hyvä 1.4.2 | Hyvä 1.5.2 |
|---|---|---|
| Filter block | `div[role=region][aria-label="Product filters"]` with `x-data="initLayeredNavigation()"` and inline `@resize`/`@click` expressions | same region; `x-data="initLayeredNavigation"` registered with `Alpine.data` and `$hyvaCsp->registerInlineScript()`; events bound through `x-bind="eventListeners"` (CSP-build compatible) |
| Filter group | `div.filter-option` with `x-data="{open:false}"` and `x-defer="intersect"`; options inside **`<template x-if="open">`** | native **`<details class="filter-option">`** + `<summary>` |
| Option links in the DOM before a group is opened | **no**: crawlers and no-JS users see no links until a group is opened | yes |
| Option markup | `ol.items > li > a[href]` with count, `aria-label` "Red filter, 12 available products" | same (`filter.phtml` unchanged) |
| Swatch options | `a.swatch-option-link-layered[rel=nofollow]` with inline `style="background-color:…"`; colour is the only visible signal | rendering moved into the `SwatchRenderer` view model; still inline styles |
| Active filters | `div.filter-current` with remove links and "Clear All" | same content, early `return` when empty |

What this means for OpenFilter's templates (M3):
- real `<a href>` options are always in the DOM (1.4 hides them);
- no inline styles: swatch colours come from a class and a custom property set in a stylesheet, or an `<img>`;
- a visible label next to every colour swatch;
- one Alpine component registered with `Alpine.data`, so it also runs with Hyvä's Alpine CSP build.

## Product list, toolbar, pager

- The product list template (`Magento_Catalog::product/list.phtml`) renders `section#product-list[tabindex=-1]` containing the top toolbar, the grid (`.products.wrapper`) and the bottom toolbar (pager and limiter). It is identical in 1.4.2 and 1.5.2.
- **Empty result:** with no products the template prints only `.message.info.empty` ("We can't find products matching the selection") and **no `#product-list`**. OpenFilter's `#of-results` region must therefore wrap the product list block from layout, not live inside the list template.
- **Sorter, limiter and view mode** are not links: `initToolbar…()` builds the URL and sets `location.href` on `change`/`click` (`toolbar.phtml`). The AJAX layer intercepts these in the capture phase on `#of-results`: it reads the new value from the control, builds the URL from the current URL and stops the event before Hyvä's handler runs. The pager uses plain links.
- Region wrappers planned for M3: `#of-filters` (the leftnav block), `#of-state` (the state block, moved out of the filter block so chips sit above the results) and `#of-results` (a container around the product list block). Each region is a layout container (`htmlTag="div" htmlId="of-…"`).

## Alpine

- Hyvä ships Alpine **3.14.3** (`Hyva_Theme::js/alpine3.min.js`). It also ships `alpine3-csp.min.js` for strict CSP setups. `Alpine.initTree(el)` exists in 3.x and is what the AJAX layer calls on swapped regions after `replaceChildren`, so components inside product cards (add to cart, wishlist, swatches) start again.
- OpenFilter's component is written for both builds: `x-data="openFilter"` registered through `Alpine.data`, no inline expressions, and one inline script followed by `$hyvaCsp->registerInlineScript()`.

## Product card block cache

Hyvä caches each product card for an hour. The key (`Hyva\Theme\ViewModel\ProductListItem::getItemCacheKeyInfo`) is: product id, view mode, template type, template, store, currency, the hide-details flags, the category id (only when categories are in product URLs), the customer group, the image display area, the image attributes and the tax rates. It contains **no filter state**. A filtered URL reuses cached cards, which is correct because a card does not change with the filters. OpenLabel's labels are part of the card HTML, so they survive every AJAX swap without integration code.

## Mobile behaviour (stock)

Below the `md` breakpoint the whole filter block collapses behind the "Shop By" toggle (`blockOpen`) above the product list. There is no off-canvas drawer and no "Show N products" button, and every option click is a full page load. OpenFilter's drawer (M3) replaces this.

## CSP

The fixture runs the storefront CSP in enforce mode. Hyvä 1.5 registers its inline scripts through `HyvaCsp`. Hyvä 1.4.2's layered navigation script has no registration call but still runs on the fixture (no violations in the smoke spec). OpenFilter registers every inline script it prints.
