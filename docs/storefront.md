# Storefront (Hyvä)

What shoppers get from `iranimij/openfilter-hyva` on Hyvä 1.4 and 1.5, how it behaves without JavaScript, and the markup contract themes can rely on from 1.0.

## What it looks like

- **Filters** in the sidebar, in the order of their position. The first three are open (setting *Filters open by default*; per filter *Open when the page loads*), the others are closed, and a filter with a selected value is always open. Each filter is a native disclosure (`<details>`), so it opens and closes without JavaScript and without moving the page.
- **Options** show their count. Options without products are hidden, except selected ones (so they can be removed). Lists longer than *Options before "Show more"* get a **Show more (N)** disclosure, and lists with more options than *Search box from this many options* get a **search box** that narrows the list as you type, including the options behind "Show more".
- **Display types**: lists with a check box, **swatches** (colour, image or text, always with a visible label and count), **price and decimal sliders** (two thumbs plus *From* / *To* fields), **rating** ("4 stars & up"), **Yes/No switches** (In stock, On sale, New and Yes/No attributes) and the **category** drill-down.
- **Chips** above the results: "Colour: Blue ×" removes one value, **Clear all** removes every filter and keeps the sort order.

## What happens on a click

With *Update results without reloading the page* on (*Stores › Configuration › Iranimij › OpenFilter*, the default), a click fetches the same URL a normal click would load, so Varnish and the full page cache answer it. OpenFilter then swaps three regions: the filters, the chips and the results (toolbar, products, pager).

- The previous results stay visible under a thin progress bar until the new ones arrive. A cached URL takes about 70 ms on the test store.
- The browser history gets the new URL. **Back and Forward** restore the filters and the scroll position.
- The page title, `robots`, `canonical` and `prev`/`next` tags match what a crawler sees on that URL.
- The focus stays on the option you clicked. After removing a chip, applying the drawer or changing the page, it moves to the results. A screen reader hears the new product count ("22 products").
- Sorting, products per page, the grid/list switch and the pager use the same swap.
- If the response is not a normal page (an error or a timeout), the browser simply loads the URL.
- If a range or a typed URL leaves no products, the chips show **No products match. Remove the last filter** with a link back.

## Phones

Below 768 px the filters move into a drawer, opened by a sticky **Filter (N)** button above the results (N counts the active values).

- Changes in the drawer are **staged**: the drawer's filters and counts update, and the footer button reads **Show 123 products**. The page behind does not change yet.
- **Show N products** applies the staged filters without another request (the page was already fetched to count N).
- **Close**, **Escape**, a tap outside or the **Back** button discard the staged changes; the focus returns to the Filter button.
- Touch targets are at least 44 px, swatches at least 36 px.

## Without JavaScript

Every filter is a real link (`<a href>`) to the filtered URL, so crawlers and shoppers without JavaScript filter by navigation. The slider is a GET form with *From* / *To* fields and an **Apply** button (steps: *Price slider step* for prices); the store answers with a 301 to the canonical URL (`price_from=20&price_to=50` becomes `price=20-50`). On phones the filters show as a plain list. The search box and the slider's thumbs are not shown without JavaScript.

## Search results

The search results page has the same filters, chips, drawer and swap. Every link keeps the search query (`q`).

## Accessibility

- Filters are disclosures; options are links with "selected" announced for selected values (`aria-current`); colour is never the only signal (label, check mark, bold weight).
- Slider thumbs: arrow keys move one step, Page Up / Page Down ten steps; the number fields are the accessible fallback.
- The drawer is a modal dialog (focus kept inside, Escape closes it).
- Automated checks: axe-core finds no WCAG 2.1 A/AA violation in OpenFilter's markup on load, after a swap, in the open drawer and on a right-to-left store view.

## Right to left

The stylesheet uses logical properties only, so a store view with `<html dir="rtl">` mirrors the filters: counts on the left, chips from the right, the slider's lower end on the right, the drawer from the right.

## Content Security Policy

The AJAX component is one static inline script, registered with Hyvä's CSP helper (`$hyvaCsp->registerInlineScript()`), and works with Hyvä's standard and CSP Alpine builds. Templates print no inline styles or handlers; swatch colours are SVG fills. After a swap, inline scripts of product cards run once per distinct script, so a store with a hash-only CSP allows them when the first page carried the same script.

## Markup contract (from 1.0)

Themes can style or extend these; class names and attributes stay stable within 1.x.

| Element | Selector |
|---|---|
| Filter region (swapped) | `#of-filters` inside `dialog#of-drawer.of-drawer` |
| Chips region (swapped) | `#of-state.of-state` (`data-of-count`, `data-of-announce`) |
| Results region (swapped) | `#of-results.of-results` |
| One filter | `details.of-group.of-group--{list,swatch,slider,from_to,rating,toggle,category}[data-of-group="<key>"]` |
| Option | `a.of-option` / `a.of-swatch` with `data-of-link`, `data-of-value`, `aria-current="true"` when selected |
| Show more | `details.of-more` |
| Search box | `.of-search input[data-of-search]` |
| Slider | `form.of-slider[data-of-slider]` with `[data-of-range]` and `[data-of-number]` fields |
| Chip / Clear all | `a.of-chip` / `a.of-clear` |
| Drawer button, footer | `button[data-of-drawer-open]`, `[data-of-drawer-apply]`, `[data-of-drawer-clear]` |

Any link with `data-of-link` inside the regions uses the swap. After every swap the window receives the event `openfilter:swapped` (`detail.url`); other components can listen to it. `window.openFilter.navigate(url)` swaps to a URL.

## Restyling

`view/base/web/css/openfilter.css` (about 2.3 KB gzipped) uses only `of-*` classes and custom properties. Override the tokens on `.of-drawer`, `.of-filters`, `.of-state` or `.of-results`:

| Token | Default | Used for |
|---|---|---|
| `--of-accent` | `#1d4ed8` | selection, slider, focus ring, Apply |
| `--of-muted` | `#4b5563` | counts, captions |
| `--of-border` | `#d1d5db` | lines, swatch frames, track |
| `--of-surface` | `#fff` | drawer and button backgrounds |
| `--of-radius` | `.375rem` | corners |
| `--of-gap` | `.5rem` | spacing |
| `--of-row` | `2rem` (`2.75rem` on touch screens) | option row height |

Templates can be overridden in a theme under `Iranimij_OpenFilterHyva/templates/layer/...` (`view.phtml`, `group.phtml`, `state.phtml`, `filter/{list,swatch,slider,rating,toggle,category}.phtml`, `js/openfilter.phtml`). Keep the region ids and the `data-of-*` attributes when you do.

## Switching it off

With OpenFilter disabled, or stepping aside for another layered navigation (ElasticSuite, Live Search), the page keeps the theme's own templates: OpenFilter's layout handles are only added while it is enabled. With *Update results without reloading the page* off, the filters, chips and slider form stay and every click is a normal page load.
