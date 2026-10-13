# Command line

| Command | What it does |
|---|---|
| `bin/magento openfilter:doctor` | checks the installation and every filter, with the fix for each problem |
| `bin/magento openfilter:slugs:generate` | creates URL names for options that have none |
| `bin/magento openfilter:config:export` | writes filter settings, labels and URL names as JSON |
| `bin/magento openfilter:config:import <file>` | applies such a file, all or nothing |

## openfilter:doctor

```bash
bin/magento openfilter:doctor                       # default store view, every filter
bin/magento openfilter:doctor -s de -c 21           # store view "de", also check category 21
bin/magento openfilter:doctor -f color --format=json
```

It checks: OpenFilter enabled, no other layered navigation active, the search engine reachable with a product index, the count strategy in use, OpenFilter's cache enabled, every option with a URL name, and other modules that replace or wrap Magento's layered navigation classes (found in the DI configuration, reported by class name). Per filter it runs the checks of the admin's "Why is this filter not showing?" panel: shown on the storefront, used in layered navigation, field in the search index, and with `-c` whether the category has products with a value and more than one option.

Each line is `OK`, `WARN` or `FAIL`. Warnings describe a choice or the catalog (a filter you hid, an attribute not used on search pages) and never fail. The command exits with `1` when a check fails, so it fits a deployment script.

## openfilter:slugs:generate

```bash
bin/magento openfilter:slugs:generate --dry-run     # what would change
bin/magento openfilter:slugs:generate -a color      # one attribute
bin/magento openfilter:slugs:generate --overwrite   # every name again from the labels
```

Without `--overwrite`, existing URL names never change: published filter URLs keep working. Use it after importing options outside the admin (new options saved in the admin get a name on save). `--overwrite` regenerates every name from the current labels; old URLs then answer through the option-id fallback only if they used ids.

## openfilter:config:export / import

See [Import and export](import-export.md).
