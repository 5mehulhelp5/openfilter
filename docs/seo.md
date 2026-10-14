# SEO rules

Filtered category pages multiply: ten colours and six sizes already make hundreds of URLs. OpenFilter keeps search engines on the pages worth indexing and out of the rest. The rules are conservative by default: only pages you mark can be indexed.

## Robots

| Page | Robots |
|---|---|
| No filter | your store's setting (unchanged) |
| Exactly one value of a filter with **Let Google index pages filtered by this** | your store's setting (unchanged) |
| Anything else: two values, two filters, a filter not marked, a category drill-down plus a filter | `NOINDEX,FOLLOW` |
| Search results with any filter | `NOINDEX,FOLLOW` |

Mark a filter in **Catalog › OpenFilter › Filters › (filter) › SEO**. Good candidates are filters whose single values match what people search for: brand, colour, material. `FOLLOW` stays everywhere, so crawlers still reach your products.

## Canonical

| Page | Canonical |
|---|---|
| Indexable filtered page | the page itself: `/women/tops.html?color=red` (page number kept, sorting, limit and view mode dropped) |
| Any other filtered page | the category: `/women/tops.html` |
| Search results | none (as Magento) |

OpenFilter prints the canonical on filtered category pages even when Magento's "Use Canonical Link Meta Tag For Categories" is off, and replaces Magento's tag when it is on: there is always exactly one.

## Nofollow

Once a page has **2** active values (setting: "Ask search engines not to follow filter links from this many active values"), the links that would add another value carry `rel="nofollow"`. Links that remove a value stay followable. `0` switches the rule off.

## Most values at once

A URL with more than **5** active values ("Most filter values active at once") answers with a **302** to the URL with its first five values (in canonical order) and an `X-Robots-Tag: noindex` header. Bots that combine every filter end on a page that already exists, and your page cache does not fill with combinations nobody visits. `0` means no limit.

## Canonical parameters (301)

Every filtered URL has one canonical spelling (filters in position order, values in option order, readable URL names, lower case). Other spellings, such as an old link with option ids, answer with a **301** to it. See [Filter URLs](url-format.md).

## After AJAX updates

Filter clicks fetch the same URL a crawler would and copy the title, robots, canonical and prev/next links from it, so the page in the browser always carries the head a direct load of that URL has.

## Not in 1.0

Meta title, description and H1 templates for filtered pages and an XML sitemap of indexable filter pages arrive in 1.1. Path-style URLs (`/women/tops/red.html`) are planned as an option for 1.2.
