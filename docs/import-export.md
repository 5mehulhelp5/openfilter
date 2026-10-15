# Import and export

Move filter settings, labels and URL names between installations (staging to production, one shop to the next) as JSON.

```bash
bin/magento openfilter:config:export -o openfilter.json
bin/magento openfilter:config:import openfilter.json --dry-run   # lists every change, writes nothing
bin/magento openfilter:config:import openfilter.json
```

## Format (schema 2)

```json
{
    "schema": 2,
    "filters": {
        "color": {
            "settings": {"display_mode": "swatch", "multiselect": true, "logic": "or", "visible_options": 6,
                "search_threshold": 12, "expanded": null, "sort_by": "position", "hide_single_option": true,
                "seo_indexable": true, "position_override": null, "active": true, "ranges": null, "tree_depth": 3},
            "labels": {"de": "Farbe"},
            "tooltips": {"admin": "Pick one or more colours"},
            "url_name": {"admin": "color"},
            "options": {"Red": {"admin": "red", "de": "rot"}}
        },
        "rating": {"settings": {"active": true}}
    }
}
```

- Filters are keyed by attribute code or built-in code (`price`, `rating`, `stock`, `sale`, `new`, `category`); an attribute named like a built-in is `attribute:<code>`.
- Store views are keyed by store code; `admin` means all store views.
- Options are keyed by their admin label. When an attribute has several options with the same label, they are numbered in option order: `"Wool #1"`, `"Wool #2"`.
- Every section and setting is optional: a file may contain only what it changes.
- Schema 2 (OpenFilter 1.1) adds `"logic": "and"` ("Match all"), `ranges` (the list of the *Ranges* display type, for example `"0-50,50-100,100-"`; `null` = automatic), `tree_depth` (levels of the category tree, 1–5), `category_mode` and `category_ids` (**Where it shows**: `all`, `only` or `except` the listed category ids; ids are those of the store the file comes from) and the `tooltips` section. Files with schema 1 from OpenFilter 1.0 import unchanged.

## All or nothing

The import first checks the whole file: schema, known filters, store views and options, value types, URL name format. Then it writes every filter inside one database transaction through the same rules as the admin (display types that fit the attribute, URL names unique per attribute and store view). If any value is refused, nothing is written and the command lists the reason and exits with `1`.

`--dry-run` runs the same checks and writes inside a transaction it always rolls back, so URL name collisions are found exactly as in a real import. OpenFilter's cache is cleaned afterwards; Magento may show "full page cache invalidated" as after any admin save.

## Over REST

Integrations use the same document over Magento's REST API (admin token, or an integration with the **OpenFilter › Filters** resource):

| Call | What it does |
|---|---|
| `GET /rest/V1/openfilter/config` | the document as a JSON string |
| `POST /rest/V1/openfilter/config` with `{"config": "<document>", "dryRun": true}` | checks and lists the changes (`applied`, `changes`, `errors`); `"dryRun": false` saves them, all or nothing |
| `GET /rest/V1/openfilter/filters?searchCriteria[...]` | the filters (search criteria on `code`, `builtin`, `active`, …) |
| `GET /rest/V1/openfilter/filters/:code` | one filter |
| `PUT /rest/V1/openfilter/filters/:code` with `{"config": {"sort_by": "name"}}` | changes the settings you send; the others stay. Read-only fields (code, attribute, label, position) cannot be sent. |

A value that does not fit the filter answers `400` with the reason; an unknown code `404`; a call without the resource `401`.

