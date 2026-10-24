# Moving from another filter module (1.2)

OpenFilter replaces a layered navigation module such as Amasty Improved Layered Navigation, Mirasvit Layered Navigation or Mageplaza Layered Navigation. Two things carry over: your filters, from Magento's own attribute settings, and the search engine value of the old filter URLs, through permanent redirects.

## Steps

1. Install OpenFilter ([Install](install.md)) on a staging copy first.
2. Run `bin/magento openfilter:migrate`. It lists the other filter modules that are still enabled, how many filters OpenFilter took over, and whether old filter URLs redirect.
3. Switch the other module off with the command it prints (`bin/magento module:disable …`, then `bin/magento setup:upgrade`). Two layered navigations on one page break each other; `openfilter:doctor` warns while one is enabled.
4. Review the filters under *Catalog › OpenFilter › Filters*. OpenFilter starts from Magento's attribute settings (*Use in Layered Navigation*, position, swatches). The other module kept its own settings (display modes, SEO rules, option images) in its own tables; OpenFilter does not read them, so set what you need in the filter form. URL names come from the option labels ([Filter URLs](url-format.md)).
5. If the old module had SEO-friendly filter URLs, turn on the redirects (below) and check a list of old URLs, for example the filtered pages Google Search Console reports:

```bash
bin/magento openfilter:migrate --redirects=on --filter-keys=shopby
bin/magento openfilter:migrate --urls=old-urls.txt   # one URL per line: shows where each one redirects
```

## Redirects of old filter URLs

*Stores › Configuration › OpenFilter › Moving from another filter module › Redirect old filter URLs* (off by default, per store view). When on, an address that no page has, starts with a category's URL and continues with filter words is answered with a permanent redirect (301) to OpenFilter's URL of the same category and filters, in the store's URL format (query or [path](url-format.md#path-urls-12-opt-in)):

| Old URL | Redirects to |
|---|---|
| `/women/tops-women/black-blue.html` | `/women/tops-women.html?color=black,blue` |
| `/women/tops-women/shopby/black.html` | `/women/tops-women.html?color=black` |
| `/women/tops-women/color_black.html` | `/women/tops-women.html?color=black` |
| `/women/tops-women/color/black-blue/size/xs.html` | `/women/tops-women.html?color=black,blue&size=xs` |
| `/women/tops-women/color:black,blue.html?price=20-50` | `/women/tops-women.html?color=black,blue&price=20-50` |

How the old path is read:

- The words after the category are matched against the store view's filters: each filter's attribute code and URL name, each option's URL name, written with dashes, underscores or nothing between their words (`heavy-duty`, `heavy_duty`, `heavyduty`), in any case.
- Options may be joined by `-`, `_`, `,` or `/`; an attribute name (or code) before options says which filter they belong to (`color_black`, `color-black-blue`, `color/black-blue`, `color:black,blue`).
- Path parts listed in *Filter keys of the old URLs* (default `shopby`) are skipped.
- Query parameters stay; Magento's price parameter (`price=20-50`) is OpenFilter's too.
- **Nothing is guessed:** if a word is unknown, or an option name belongs to two filters and no attribute name says which, the address stays a 404. Old URLs from a module that numbered duplicate names (`black-1`) or used its own aliases need their own URL rewrites (*Marketing › URL Rewrites*), or matching URL names in OpenFilter's filter form.

The redirect runs after Magento's URL rewrites, landing pages and the All Products page, so it never takes an address another page has. Addresses that begin with a route (`checkout/`, `customer/` …) are not looked at; the others cost one URL rewrite query.

## The formats, and where they come from

The formats were taken only from each vendor's public user guide (read on 2026-10-16); no vendor code was read or used. Formats a guide does not describe are not covered.

| Module | What its guide documents | Source |
|---|---|---|
| Amasty Improved Layered Navigation | option names after the category (`category/autumn.html`), joined by a chosen separator (`red-green-blue.html`); attribute code before the option (`color_black`); a filter key (`shopby/black-xl-activity`); price and decimal filters stay query parameters (`?price=100-200`) | [User guide](https://amasty.com/user-guides/magento-2-improved-layered-navigation/) |
| Mirasvit Layered Navigation / SEO Filters | short formats `category/option1-option2`, `category/option1/option2`, `category/option1_option2`; long formats `category/attr1/option1-option2/attr2/option3`, `category/attr1-option1-option2/attr2-option3`, `category/attr1:option1,option2/attr2:option3`; a filter prefix (`filterprefix/material/cotton.html`); multi-word attribute names without separator, with `_`, `-` or capitals | [Filters SEO URL](https://mirasvit.com/manual/magento-2-layered-navigation/configuration/filters-seo-url) |
| Mageplaza SEO-Friendly URL / Layered Navigation | the option after the category (`jackets-women/heavyduty.html`); attribute code before it (`climate_allweather`); a filter key instead of the attribute (`shopby/allweather`) | [SEO-friendly URL](https://docs.mageplaza.com/magento/seo-friendly-url) |

Other modules whose old URLs follow one of these patterns are covered the same way.
