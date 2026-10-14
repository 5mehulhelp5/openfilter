# PROGRESS

Fine-grained checkpoint for the OpenFilter build. Updated after every local slice and every merged PR. The Notion build log (07 · Build Brief, section 10) holds the per-milestone summary. **GitHub working-hours rule lifted (Iman, 2026-10-14): GitHub activity is allowed at any time; release tags still need Iman's go.** **One chat per milestone** (Iman, 2026-10-13): each chat ends with this file, the build log and the prompt for the next chat.

## Current milestone

**M0–M4 merged on GitHub, 2026-10-14 ~21:20 Europe/Berlin.** Repositories iranimij/openfilter, openfilter-hyva, openfilter-hyva-bundle, openfilter-dev-env (public; ci-ok required on the first three). Merged: core #8–#39 (#10–#17 and hyva #1–#4 went in with the next green slice PR, #18 and hyva #5: those early slices were red only on the unit coverage gate / on hyva's test against the newest core), CI fixes core #40, hyva #14–#16, bundle #1, dev-env #1–#5; hyva #17 points hyva CI back to core main. Full matrix on the M4 top: green on 2.4.7 (OpenSearch and Elasticsearch 8), 2.4.8, 2.4.9 × PHP 8.2–8.4 plus the Performance job (server time −43 ms vs core); E2E green on Hyvä 1.4.2 and 1.5.2 (Playwright, timing budgets, Lighthouse). CI findings on the first GitHub runs (all fixed in the slice that introduced them): integration job setup (test framework Bootstrap, Composer advisories on 2.4.7, ES7/ES8 client conflict, Elasticsearch 8 needs indices.id_field_data.enabled for Magento 2.4.7, Hyvä's recurring setup needs a root module list), PHPUnit 9/10 compatibility (data provider docblocks, isArray, typed test properties on 2.4.7), PHPStan on older Magento PHPDoc, E2E fixture order for German URL names, RTL drawer on Linux scroll bars, CI timing gate = 300 ms swap (long tasks recorded, Iman). Details: openfilter-dev-env/modules/M4-ledger.md. **Next:** release 1.0 only on Iman's go (openfilter-dev-env/docs/release-1.0.md: rc1 → Packagist by Iman → README gate → 1.0.0), then M5 and M6 back to back (D18: beat every competitor, plus benchmark suite, headless first, merchant insights, migration tooling).

**M4 · SEO, CLI, docs, release 1.0.0: local slices S1–S8 done, 2026-10-13 ~16:45, inside working hours.** Plan `~/.claude/plans/pasted-content-id-4b5b-read-https-app-n-quirky-pancake.md` (approved; Iman's four answers = the recommendations: README gate at rc1 with an `@RC` override, canonical also when core's category canonical is off, sort/limit/mode dropped from the canonical, M4 pushed on the M3 branches without M3's yes); ledger `openfilter-dev-env/modules/M4-ledger.md`; decisions D17. Stacks: openfilter `feat/m4-s1-seo` → `s2-cli` → `s3-ops` → `s4-minors` → `s5-perf` → `s6-docs` → `s7-qa` → `s8-close` on `feat/m3-s8-close`; openfilter-hyva `feat/m4-s1-seo` → `s3-ops` → `s4-minors` → `s5-perf` → `s6-docs` → `s7-qa` → `s8-close` on `feat/m3-s8-close` (`.github/ci/openfilter-ref` = `feat/m4-s8-close`); bundle `chore/m4-release` on `main`; dev-env `test/m4-e2e` on `test/m3-e2e`. Iman reviewed the storefront mid-milestone ("the style of the filters does not seem good"): filters restyled as Hyvä-like cards (S4 branch). Final local runs (~16:40): unit core 325 / hyva 13, coverage gate incl. Model/Seo 100 % and Model/Transfer 93.9 %; integration core 134/134 + performance group 17/17 (server time −42.7 ms vs core, gate ≤ +15), hyva 28/28 (SQL with Hyvä templates: category 129 = 129, search 136 ≤ 137); Playwright storefront 141 passed + 10 skipped by design, admin 12/12, timing budgets 4/4, README quick start 2/2; Lighthouse on/off PASS (1.00 → 1.00, CLS 0.000); PHPCS zero, PHPStan clean, mkdocs --strict ok, composer validate --strict ×3. Release: runbook `openfilter-dev-env/docs/release-1.0.md` (no tag without Iman's go).

**M3 · Hyvä render, AJAX, mobile: all slices done locally (S0–S8), 2026-10-13 ~14:45, inside working hours.** Plan `~/.claude/plans/jazzy-coalescing-cerf.md` (approved; Iman: theme-module required, groups as `<details>`, Lighthouse "unchanged" = median of 3 within 0.05 and axe gated on OpenFilter's regions, **M3 pushed tonight with CI but merged only on his yes**); ledger `openfilter-dev-env/modules/M3-ledger.md`. Stacks: openfilter `feat/m3-s1-regions` → `s2-options` → `s3-chips` → `s4-ajax` → `s5-slider` → `s6-drawer` → `s8-close` on `feat/m2-s7-close` (no core change in S7); openfilter-hyva `feat/m3-s1-regions` → … → `s7-search` → `s8-close` on `main`; dev-env `test/m3-e2e` on `test/m2-e2e`. M3's "done when" holds locally on Hyvä 1.4.2 and 1.5.2 (Varnish, CSP enforced, OpenLabel): swap without reload, back/forward with scroll, no-JS projects, mobile drawer (Apply without a request), labels after swaps, zero CSP errors (sweep on every storefront test), axe clean, Lighthouse on/off score 1.00 → 1.00 and CLS 0.000 → 0.000, swap on a cached URL 65–78 ms. Final local runs (after Iman raised Docker to 16 GB, ~15:20): core integration 112/112 (BudgetTest: 118 = 118 SQL queries, OpenFilter 93 ms vs core 153 ms), hyva integration 14/14, admin Playwright 11/11 + setup, storefront 105 passed + 10 skipped by design, timing budgets `npm run perf` 10/10 (one worker).

**M2 · Admin: all slices done locally (S1–S7), 2026-10-13 ~12:45, inside working hours.** Plan `~/.claude/plans/pasted-content-id-0cbc-read-https-app-n-deep-muffin.md` (approved; Iman's three questions taken with the recommendations); ledger `openfilter-dev-env/modules/M2-ledger.md`. Stack `feat/m2-s1-config` → `s2-grid` → `s3-form` → `s4-attribute` → `s5-why` → `s6-first-run` → `s7-close` on `feat/m1-s9-close`; dev-env `test/m2-e2e` on `test/m1-e2e`. M2's "done when" holds locally: Playwright admin specs pass (11) and the automated merchant scenario turns colour into a multi-select swatch filter with a search box in **4.9 s** (limit 120 s), with the storefront link for a second colour keeping the first. Waiting for after hours: M0 S7, the M1 push, then the M2 push (below).

**M1 · Search engine core: all slices done locally (S1–S9), 2026-10-13 11:10, inside working hours.** Waiting for after hours: M0 S7, then the M1 push (below). M1's "done when" holds locally: counts = brute force (and = an engine-independent catalog model) on simple and configurable products, 3 customer groups, 2 store views, category and search pages, both strategies; a category page with 3 active filters costs 1 search request (SingleQuery) and 0 extra MySQL queries (`BudgetTest`). CI confirmation after hours.

**M0 · Foundation + search spike: local slices done (S0–S6), S7 (after hours) open.** Started 2026-10-13 08:30, full-run mode ("don't ask, make the plan and start"); plan `~/.claude/plans/i-wanna-create-a-elegant-comet.md`; ledger `openfilter-dev-env/modules/M0-ledger.md`. M0's "done when" needs CI green on the full matrix with OpenSearch, which can only run after 18:00.

## M0 slices

Built as local branches while GitHub is off limits. Stack per repo (bottom first):

| # | Slice | State |
|---|---|---|
| S0 | Fixture `openfilter-dev-env`: Warden `openfilter` at https://app.openfilter.test, Magento 2.4.9 + sample data (2,040 products), Hyvä 1.4.2 (OSL path repo), Varnish, storefront CSP enforced, admin 2FA and URL keys off, OpenLabel 1.1 + module-base from Packagist, German store view `de` (colour labels Grün, Weiß, …), catalog price rules General −10 % / Wholesale −20 % / Retailer −15 %, Playwright baseline | done locally: dev-env `main` e5525b7 |
| S1 | `openfilter` skeleton on module-base: section `openfilter` under tab `iranimij` (enabled, debug), ACL under Catalog and `Iranimij_Base::config`, `Model/Config` via `TypedReader`, en_US + de_DE, MIT headers, NOTICE, mkdocs, CI (PHPCS, PHPStan 6, unit × 7, coverage gate, docs, own OpenSearch integration job incl. ES8 on 2.4.7) | done locally: openfilter `main` 0dc7308 |
| S2 | `openfilter-hyva` skeleton (requires module-base + openfilter, `hyva_*` handles, Tailwind stub, CI with openfilter at `.github/ci/openfilter-ref`) + `openfilter-hyva-bundle` metapackage; e2e workflow (Hyvä 1.4.2 + 1.5.2 legs) | done locally: openfilter-hyva `main` 3c3e61a, bundle `main` 2563318 |
| S3 | Hyvä verification → `docs/hyva-integration.md` (1.4.2 + 1.5.2) | done locally (in openfilter 0dc7308; Playwright `hyva-layered-nav.spec.ts`) |
| S4 | Search spike: `FacetCountStrategyInterface`, SingleQuery + PerAttribute, collector, resolver (`openfilter/search/count_strategy`, auto), plugins; `docs/facet-counts.md` | done locally: openfilter `feat/m0-s4-facet-counts` 0467d83; dev-env `test/m0-e2e` c96c628 |
| S5 | Index hooks verified (pool, mapping, mview) → `docs/index-fields.md` | done locally: openfilter `feat/m0-s5-index-fields` (stacked on S4) |
| S6 | One-page specs in Notion under 02 · Architecture: FilterConfig, Slug, UrlCodec; 02 and 10 `[M0 verify]` items resolved | done (Notion) |
| S7 | After hours: create the four repos, protection, auto-merge, GitHub milestones M0–M6 + tracking issues; push; PRs; full matrix; merge; build log; M0 report | **next** |

## Exact next step

1. **Release 1.0 (only after Iman's go):** follow openfilter-dev-env/docs/release-1.0.md — release PRs `chore: 1.0.0-rc1` in core, hyva, bundle; tags `v1.0.0-rc1`; Iman registers the three packages on Packagist; `gh workflow run E2E -R iranimij/openfilter-dev-env -f readme=true -f openfilter_constraint='^1.0@RC'`; then `v1.0.0`, `gh variable set OPENFILTER_ON_PACKAGIST --body true` on dev-env, docs site from docs.yml.
2. **M5 (1.1) in a new chat** with the M5 prompt (D14–D18): 1.1 rows of 03 · Feature Superset plus the D18 extras for M5 (public benchmark suite, headless first).
3. Then M6 (1.2) with merchant insights and migration tooling (D18).

## M4 slices (local, openfilter on `feat/m3-s8-close`, openfilter-hyva on `feat/m3-s8-close`)

| # | Slice | State |
|---|---|---|
| S1 | `Model/Seo` (robots, canonical, nofollow, max values guard 302, category URL from the cache), `ApplySeoHead` observer, `FilterOption::getRel()`; Playwright `m4-seo` desktop/mobile/nojs | done locally |
| S2 | `openfilter:doctor` (`Model/Doctor/Report`, `SystemChecks`), `openfilter:slugs:generate`, `openfilter:config:export/import` (`Model/Transfer`: schema 1, all or nothing, dry run) | done locally |
| S3 | Debug mode (`ViewModel\Debug`, `data-of-debug-*`, log lines, page cache invalidated), uninstall class | done locally |
| S4 | M3 minors (dialog title on every page, inline filters without `showModal`) + storefront restyle after Iman's review | done locally |
| S5 | Server time hard gate (`ServerTimeTest`, CI job "Performance"), SQL budget with the Hyvä templates | done locally |
| S6 | README (GIF, install gate markers, comparison), docs pages, CHANGELOGs, bundle CI and community files, tracker entry; README gate wiring in dev-env | done locally |
| S7 | QA pass (07 §8 checklist in the ledger), keyboard-only journey spec, screenshots `docs/m4-screenshots` (7), Notion | done locally |
| S8 | Release runbook + notes (`openfilter-dev-env/docs/release-1.0.md`, `release-notes-1.0.md`); tags not run | done locally |

## M3 slices (local, openfilter on `feat/m2-s7-close`, openfilter-hyva on `main`)

| # | Slice | State |
|---|---|---|
| S0 | Fixture: store views `hyva15` (Hyvä 1.5.2 copied from the public tag, `bin/install-hyva15`, theme registered by the fixture module `OpenFilterFixture_Themes`) and `ar` (right to left); Playwright projects per Hyvä version, store-cookie setup, CI legs per version, dispatch refs | done locally: dev-env `test/m3-e2e` |
| S1 | `ViewModel\FilterBlock`, structural CSS, observer + handles (OpenFilter off = stock Hyvä), regions `#of-filters` / `#of-state` / `#of-results`, `<details>` groups; openfilter-hyva requires `hyva-themes/magento2-theme-module ^1.3` | done locally |
| S2 | Option templates: swatches (SVG fills), rating, switches, category, Show more, search box; `ViewModel\Swatch` | done locally |
| S3 | Chips and Clear all (`ViewModel\State`) | done locally |
| S4 | One Alpine component: same-URL AJAX, cache, history + scroll, head sync, focus, live count, toolbar and pager, zero-results undo, search box | done locally |
| S5 | Sliders (`ViewModel\Slider`), no-JS range form folded by the normaliser (301) | done locally |
| S6 | Mobile drawer (dialog, staged prefetch, Show N products, Back/Escape) | done locally |
| S7 | Search results page | done locally (openfilter-hyva only) |
| S8 | Acceptance (CSP sweep, axe, OpenLabel, RTL, Lighthouse), docs `storefront.md`, screenshots `docs/m3-screenshots` (6), coverage gate + ViewModel | done locally; final full runs pending the stack |

## M2 slices (local, stacked on `feat/m1-s9-close`)

| # | Slice | State |
|---|---|---|
| S1 | System config (AJAX, filters open by default, price step, nofollow threshold, max active values; full_page invalidated on change; server-side number validation), menu Catalog › OpenFilter › Filters / Settings, admin route | done locally: `feat/m2-s1-config` |
| S2 | `getList(SearchCriteria)` + Filters grid (inline edit, Show/Hide/Reset), `is_active` on `openfilter_attribute`, hidden filters leave the storefront | done locally: `feat/m2-s2-grid` |
| S3 | Filter form (How it looks / behaves / SEO / Labels per store view), option URL names saved as one set, labels reach attribute filters on the storefront | done locally: `feat/m2-s3-form` |
| S4 | Attribute edit fieldset (saved with the attribute; attribute saves clean OpenFilter's cache) | done locally: `feat/m2-s4-attribute` |
| S5 | "Why is this filter not showing?" (`Model/Doctor`: store view + category checks with fixes, engine mapping and counts), grid row action, `magento/module-ui` requirement | done locally: `feat/m2-s5-why` |
| S6 | First-run notice (system message until "Review defaults") | done locally: `feat/m2-s6-first-run` |
| S7 | Done-when scenario (4.9 s), docs `admin.md`, screenshots `docs/m2-screenshots` (6), i18n en_US + de_DE complete, close | done locally: `feat/m2-s7-close`; dev-env `test/m2-e2e` |

## M1 slices (local, stacked on `feat/m0-s5-index-fields`)

Plan: `~/.claude/plans/pasted-content-id-b16b-read-https-app-n-glittery-aho.md` (approved 2026-10-13 09:20). Ledger: `openfilter-dev-env/modules/M1-ledger.md`. Each slice is its own branch `feat/m1-sN-*`, stacked.

| # | Slice | State |
|---|---|---|
| S1 | Schema (`openfilter_attribute`, `_attribute_store`, `_builtin`, `_builtin_store`), `FilterConfigInterface` + repository, per-store cache (cache type `openfilter`, enabled by a data patch) | done locally: `feat/m1-s1-config` |
| S2 | Transliterator, slugs (`openfilter_option_slug`, `_attribute_slug`), slug repository + cached map, observer for new options, patches DefaultFilterConfig / InitialSlugs | done locally: `feat/m1-s2-slugs` |
| S3 | `UrlCodecInterface` (decode/encode/urlFor, numeric id fallback, clamping, canonical order), `FilterStateInterface`, request-scoped `FilterStateProvider`, `Range` | done locally: `feat/m1-s3-codec` |
| S4 | Multi-value attribute filter (OR, selected options stay, hide empty/single, sort by position/name/count), canonical item links incl. swatches and core category links, `LayerContext` | done locally: `feat/m1-s4-layer-filters` |
| S5 | M1 catalog fixture (simple + configurable, 3 groups, 2 store views), counts = brute force = catalog model for both strategies on category and search pages, `var/log/openfilter.log` channel | done locally: `feat/m1-s5-strategies` |
| S6 | Price + decimal sliders: range filters are facet filters, bounds from the same request (`DynamicBucketPlugin`), display currency + tax (`PriceBoundsConverter`), OpenFilter `Price`/`Decimal` filters (core fallback when off) | done locally: `feat/m1-s6-stats` |
| S7 | Built-ins: rating, in stock (MSI-aware), on sale per group/website, new (query-time dates); index providers + mapping; clauses + counts in the same request as facet filters; reindex triggers | done locally: `feat/m1-s7-builtins` |
| S8 | `ParamNormalizer` + predispatch observer: 301 to canonical filter URLs on category/search pages (GET/HEAD, sort kept, page reset only when values were dropped); coexistence `Detector` (ElasticSuite, Live Search) switches OpenFilter off via `Config::isEnabled()` + admin notice | done locally: `feat/m1-s8-normaliser` |
| S9 | `BudgetTest` (1 search request with 3 filters; SQL equal to core; time recorded), coverage gate + `Model/Url`, `Model/Index` (table access moved to `Model/ResourceModel`), docs `filters.md` + `url-format.md`, Playwright `m1-filters.spec.ts`, screenshots `docs/m1-screenshots`, fixture built-ins on | done locally: `feat/m1-s9-close`; dev-env `test/m1-e2e` |

## M1 decisions (also in the Notion build log)

- Iman's questions answered with the recommendations (no reply needed to proceed): slider tax uses the store's default product tax class rate; ≤ 15 ms server time recorded in M1, hard gate in M4; stock Hyvä shows the active price range only until M3's slider template.
- Order change: codec (S3) before the multi-value filters; normaliser late (S8).
- OpenFilter's attribute, price, decimal and built-in filters enter through the `filters` argument of `categoryFilterList` / `searchFilterList` and a `FilterList` plugin, never preferences; core filters remain the fallback when OpenFilter is off or steps aside (ElasticSuite, Live Search).
- Built-ins and sliders are facet filters too: every count and bound ignores its own filter, in one search request; core's price-interval requests are replaced by the request's own `extended_stats`.
- Storefront reads no tables for settings, URL names or tax: cache type `openfilter` (enabled by a data patch), tax rate cached per store and group for one hour.
- Table access lives in `Model/ResourceModel` (integration-tested); the unit coverage gate covers the logic namespaces.
- Test harness: core keeps per-process state (shared search criteria builder, indexer attribute list); tests reset it (`SearchIndexReset`, fresh builder per collection) and dispatch one page per test.

## M0 decisions (also in the Notion build log at close-out)

- Iman's instructions 2026-10-13: full run without questions; D3–D8 and D13 recommendations adopted as decided; GitHub only after hours; one chat per milestone; both modules depend on and use the shared core `iranimij/module-base`.
- Shared core: `openfilter` and `openfilter-hyva` require `iranimij/module-base ^1.0` and list `Iranimij_Base` in `module.xml`; configuration through `TypedReader`; JSON through `SafeJson` (from M1); section under the `iranimij` tab; ACL under `Iranimij_Base::config`; unit `ModuleTest` guards it.
- Fixture: new Warden env `openfilter` (fresh install through `bin/setup`, not a copy of the OpenLabel database); the OpenLabel stack is stopped while it runs (Docker 7.6 GB). OpenLabel comes from Packagist (`openlabel-hyva-bundle ^1.1`), not from local checkouts.
- CI integration does not use the ExtDN integration action (it wires only Elasticsearch): `.github/ci/integration.sh` installs Magento from the public mirror, adds the checkout as a path package, configures OpenSearch (or Elasticsearch 8 on 2.4.7, adding `magento/module-elasticsearch-8`, which the 2.4.7 project does not ship) and runs `Test/Integration`. Search engines start with `docker run` in the job, because service containers cannot switch images per leg.
- Facet counts: plugins on the shared `ElasticAdapter\SearchAdapter\Mapper::buildQuery()` and `SearchAdapter\Aggregation\Builder` (identical on 2.4.7-p10 / 2.4.8-p5 / 2.4.9, OpenSearch and ES8). Facet filters = `must` term filters of `catalog_view_container` / `quick_search_container` on fields with a term bucket; `category_ids` and `visibility` stay conjunctive; GraphQL and other requests untouched. Core's price interval provider gets the original query (it re-runs it). Setting `openfilter/search/count_strategy` (auto | single_query | per_attribute); auto = SingleQuery for opensearch, elasticsearch8, elasticsearch7, PerAttribute otherwise.
- Coverage gate namespaces grow with the code: M0 gates `Model/Search` and `Model/Config` (99.2 % of `Model`).
- `docs/hyva-integration.md` landed with the S1 skeleton commit (written while the store built); it is still S3's deliverable.
- Hyvä 1.5.2 is read from the public `hyva-themes/magento2-default-theme` tag (OSL) and tested in CI only; the local store runs 1.4.2 (OpenLabel's setup).

## Test inventory

| Repo | Unit | Integration | Other |
|---|---|---|---|
| openfilter (M4 local) | 325 / 1,337 assertions | 134 / 460 on 2.4.9 + OpenSearch 2.5 + performance group 17 | PHPCS zero, PHPStan 6 clean, coverage Search 90.7 / Config 95.6 / Url 99.4 / Index 100 / Doctor 99.4 / Seo 100 / Transfer 93.9 / ViewModel 98.4 % (gate ≥ 85 %), mkdocs --strict ok |
| openfilter-hyva (M4 local) | 13 / 43 | 28 / 159 (+ nofollow 2, debug 2, search without filters 1, SqlBudget 9) | PHPCS zero, PHPStan clean; script 5.9 KB, CSS 2.6 KB gzipped |
| openfilter-dev-env (M4) | — | — | Playwright storefront 141 + 10 skipped by design (m4-seo 16, m4-minors 4, m4-a11y 2, m4-keyboard 2), admin 12, perf 4, readme 2; Lighthouse on/off PASS; screenshots `docs/m4-screenshots` |
| openfilter (M3 local) | 273 / 1,150 assertions | 112 / 382 on 2.4.9 + OpenSearch 2.5 (M2's 109 + NormalizationTest +3 for the range form) | PHPCS zero (module rulesets), PHPStan 6 clean, coverage Search 90.6 / Config 95.6 / Url 99.4 / Index 100 / Doctor 99.2 / ViewModel 98.2 % (gate ≥ 85 %, ViewModel added), mkdocs --strict ok |
| openfilter-hyva (M3 local) | 13 / 43 | 14 on 2.4.9 + Hyvä 1.4.2 theme (category 5, option templates 5, AJAX component 2, search 1, module 1) | PHPCS zero, PHPStan clean; script 5.9 KB, CSS 2.3 KB gzipped |
| openfilter-dev-env (M3) | — | — | Playwright storefront 105 on desktop/mobile/nojs × Hyvä 1.4.2 and 1.5.2 + desktop-rtl (10 stock-Hyvä checks skip themselves by design), admin 11, perf 2 (one worker); CSP sweep on every storefront test; Lighthouse on/off PASS; screenshots `docs/m3-screenshots` (6) |
| openfilter (M2 local) | 244 / 953 assertions | 109 / 376 on 2.4.9 + OpenSearch 2.5 (M1 72 + Admin 37: settings 3, page/menu/ACL/first-run 5, grid 9, form 9, attribute fieldset 6, diagnosis 5) | PHPCS zero, PHPStan 6 clean, coverage Search 90.6 % / Config 95.6 % / Url 99.4 % / Index 100 % / Doctor 99.2 % (gate ≥ 85 %, Model/Doctor added), mkdocs --strict ok, `setup:di:compile` ok |
| openfilter (M1 local) | 171 / 565 assertions | 72 / 254 on 2.4.9 + OpenSearch 2.5 (M0 26, config 4, slugs 5, layer 2, M1 counts 9, sliders 5, built-ins 6, normalisation + coexistence 10, budget 5) | PHPCS zero, PHPStan 6 clean, coverage Search 90.6 % / Config 100 % / Url 99.3 % / Index 100 % (gate ≥ 85 %), mkdocs --strict ok, `setup:di:compile` ok |
| openfilter (M0 local) | 24 / 105 assertions | 26 / 58 (skeleton 4, DisjunctiveCounts 18, IndexHooks 4) on 2.4.9 + OpenSearch 2.5 | PHPCS zero, PHPStan 6 clean, coverage `Model` 99.2 % (gate ≥ 85 % on Model/Search, Model/Config), mkdocs --strict ok |
| openfilter-hyva (M0 local) | 4 / 21 | 1 / 3 | PHPCS zero, PHPStan 6 clean |
| openfilter-dev-env (M2) | — | — | Playwright 29 (17 desktop + admin setup 1 + admin 11: m2-config 1, m2-grid 4, m2-filter-form 3, m2-attribute 1, m2-why 1, m2-colour-swatch 1); M2 screenshots `docs/m2-screenshots` (6) |
| openfilter-dev-env (M1) | — | — | Playwright 18 (17 desktop: smoke 6, hyva-layered-nav 5, facet-counts 1, m1-filters 5; admin login setup 1); screenshots project 4 (not a gate); M1 screenshots `docs/m1-screenshots` (4) |

## Open questions for Iman

None blocking. M2: Iman's three questions taken with the recommendations (`is_active` on `openfilter_attribute`; `getList` + `FilterConfigSearchResultsInterface`, `getAll()` kept; merge M2 tonight when green). Iman asked (12:05) whether competitors were compared and whether OpenFilter goes beyond them: answered (research + 03 · Feature Superset, public docs only; 1.0 = parity + unique edges by design, 1.1/1.2 carry the superset); pulling 1.1 items into 1.0 is his call. FYI: the M0 and M1 "done when" CI checks (full matrix) can only run after 18:00. The three M1 questions were answered with the recommendations; say so if you want another choice (tax per product class, a hard time gate now, or a price range list on stock Hyvä).

## Known failing or skipped tests

- `hyva-layered-nav.spec.ts` skips itself once OpenFilter's own templates are active (M3), by design.

## Fixture gotchas (see openfilter-dev-env README)

Docker has 16 GB since 2026-10-13: Warden stacks (openfilter, openlabel, openimport) run side by side; never run integration tests and Playwright at once within one stack. After `warden env up` of a new env, Traefik may answer "404 page not found" until `docker restart traefik`. After `setup:di:compile` (e.g. `bin/install-modules`), delete `generated/metadata` before integration tests or the sandbox install fails. Composer `--no-plugins` in the container; OpenSearch pinned to 2.5.0 (Apple Silicon); CSP enforce flag set via SQL. Stock Hyvä 1.4 filter groups use `x-defer="intersect"`: Playwright must scroll a group into view before opening it. Local PHPStan: `vendor/bin/phpstan analyse -c modules/phpstan-local.neon` in php-fpm (php-debug lacks the generated code); new factories need `setup:di:compile` first. The fixture runs uncompiled DI from the config cache: after editing `di.xml`, `bin/magento cache:flush` (a stale merged config showed "Cannot instantiate interface … FrontendInterface"). Coverage locally: `XDEBUG_MODE=coverage` in `php-debug` with `phpunit.xml.dist` (`phpunit.coverage.xml` is CI's standalone run). After changing a controller's constructor or return type, delete `generated/code/Iranimij/OpenFilter` and `dev/tests/integration/tmp/sandbox-*/generated/code/Iranimij` (stale interceptors). Admin Playwright specs snapshot and restore the OpenFilter tables; run `--project=admin-chromium` (serial). The host has no `var/`: pipe one-off PHP scripts through stdin (`warden env exec -T php-fpm bash -lc 'cd /var/www/html && php' < script.php`).

M3 additions: `bin/install-hyva15` downloads Hyvä 1.5.2 into `app/design/frontend/OpenFilterFixture/hyva-1-5-2` (ignored by git) and creates store view `hyva15`; `ar` is right to left. Playwright picks the store view by cookies saved in the `store-setup` project (`store` + `X-Magento-Vary`, never `PHPSESSID`: a shared session makes parallel workers hit "Redis session exceeded concurrent connections"); storefront specs import `test` from `tests/fixture.ts` (its `request` carries those cookies, and it fails a test on any CSP violation). After template or CSS changes: `bin/magento cache:flush` (Varnish serves the old HTML otherwise). Warden's file sync lags a host edit by a moment: wait ~2 s before a container run. CSS: media queries close the stylesheet (equal specificity, a unit test checks the order).

## Last CI run

M4 top (feat/m4-s8-close) full matrix: https://github.com/iranimij/openfilter/actions/runs/38605814085 (green); E2E: https://github.com/iranimij/openfilter-dev-env/actions/runs/38605819532 (green). Main after the merges: see the repository's Actions tab.


None yet (repositories are created after hours in S7).
