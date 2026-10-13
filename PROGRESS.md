# PROGRESS

Fine-grained checkpoint for the OpenFilter build. Updated after every local slice and every merged PR. The Notion build log (07 · Build Brief, section 10) holds the per-milestone summary. **Working-hours rule (Iman, 2026-10-13): no GitHub activity Mon–Fri 08:00–18:00 Europe/Berlin** (`openfilter-dev-env/bin/after-hours` exits 1 inside them). **One chat per milestone** (Iman, 2026-10-13): each chat ends with this file, the build log and the prompt for the next chat.

## Current milestone

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

**Iman, 2026-10-13 09:15: start M1 locally now, push after hours.** M1 slices are built as local branches stacked on `feat/m0-s5-index-fields` (openfilter) and `test/m0-e2e` (dev-env), with the same local gates as CI (PHPCS, PHPStan 6, unit + coverage, integration on the fixture's OpenSearch, Playwright). Deviation from 07 §5 ("do not start a milestone before the previous done-when passes"), accepted by Iman: only M0's CI matrix is pending; if it finds a version issue, fix it on the M0 branch and rebase the M1 stack. M0 S7 then runs at the first after-hours moment, and the M1 stack is pushed right after it (full matrix on the top branch).

**S7, after 18:00 Europe/Berlin (check `openfilter-dev-env/bin/after-hours` first):**
1. `gh repo create iranimij/{openfilter,openfilter-hyva,openfilter-hyva-bundle,openfilter-dev-env} --public` (MIT, descriptions as in composer.json); push `main` of each; enable auto-merge and delete-branch-on-merge; branch protection on `main` with the required check `ci-ok` (bundle and dev-env: their own checks, as for OpenLabel).
2. GitHub milestones on `openfilter` (and the same set on openfilter-hyva / openfilter-dev-env): M0 Foundation + search spike (due 2026-10-15), M1 Search engine core (10-19), M2 Admin (10-22), M3 Hyvä render + AJAX + mobile (10-26), M4 SEO, CLI, docs, 1.0.0 (10-29), M5 1.1 superset (11-12), M6 1.2 professional edge (11-26); one tracking issue per milestone with the slice list from 04 · Roadmap.
3. Push `feat/m0-s4-facet-counts` and `feat/m0-s5-index-fields` (base: S4) and dev-env `test/m0-e2e`; open PRs; `gh workflow run CI --ref feat/m0-s5-index-fields` (full matrix: the spike touches `Model/Search`); fill the evidence table in `docs/facet-counts.md` from the matrix; if a leg fails for SingleQuery only, map that engine to PerAttribute in `etc/di.xml` and record it.
4. Merge in order (retarget S5 to `main` before merging S4), e2e on dev-env green (Hyvä 1.4.2 + 1.5.2), update this file and the build log, M0 report.

**M1 push, right after M0 S7 (same evening, still after hours):**
5. openfilter: push the stack `feat/m1-s1-config` → `s2-slugs` → `s3-codec` → `s4-layer-filters` → `s5-strategies` → `s6-stats` → `s7-builtins` → `s8-normaliser` → `s9-close` (each based on the previous; S1 based on `feat/m0-s5-index-fields`, retargeted to `main` once M0 is merged); one PR per slice into the GitHub milestone "M1 Search engine core". dev-env: push `test/m1-e2e` (based on `test/m0-e2e`).
6. `gh workflow run CI --ref feat/m1-s9-close` (full matrix: Model/Search, Model/Index, Model/Layer touched); the M1 rows of `docs/facet-counts.md` get the CI run link. If a leg fails on 2.4.7/2.4.8 or ES8, fix on the lowest slice that introduced it and rebase the stack.
7. Merge in order after green (retarget each next PR to `main` before merging its base; never delete a branch another open PR uses as its base), e2e on dev-env green, update this file and the build log.

**M2 push, right after the M1 merge (same evening, still after hours; Iman: merge M2 too when CI is green):**
8. Rebase `feat/m2-s1-config` … `feat/m2-s7-close` onto `main` (`git rebase --onto main feat/m1-s9-close feat/m2-s1-config`, then each next slice onto the previous), and dev-env `test/m2-e2e` onto dev-env `main`; rerun unit + PHPCS + PHPStan locally after the rebase.
9. Push the stack, one PR per slice into GitHub milestone "M2 Admin" (S1 based on `main`, each next on the previous); PR CI on each; `gh workflow run CI --ref feat/m2-s7-close` once (full matrix: no Model/Search|Index|Layer change except the FilterList plugin's hidden-filter check in `Plugin/Layer` and `Model/Layer` label names, so the full matrix is required by 07 §5).
10. Merge in order after green, e2e on dev-env green (incl. admin specs), update this file and the build log, M2 report with CI links. M3 starts in a new chat.

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

Docker has 7.6 GB: stop the `openlabel` stack (`warden env stop` in openlabel-dev-env) before starting `openfilter`; never run integration tests and Playwright at once. After `warden env up` of a new env, Traefik may answer "404 page not found" until `docker restart traefik`. After `setup:di:compile` (e.g. `bin/install-modules`), delete `generated/metadata` before integration tests or the sandbox install fails. Composer `--no-plugins` in the container; OpenSearch pinned to 2.5.0 (Apple Silicon); CSP enforce flag set via SQL. Stock Hyvä 1.4 filter groups use `x-defer="intersect"`: Playwright must scroll a group into view before opening it. Local PHPStan: `vendor/bin/phpstan analyse -c modules/phpstan-local.neon` in php-fpm (php-debug lacks the generated code); new factories need `setup:di:compile` first. The fixture runs uncompiled DI from the config cache: after editing `di.xml`, `bin/magento cache:flush` (a stale merged config showed "Cannot instantiate interface … FrontendInterface"). Coverage locally: `XDEBUG_MODE=coverage` in `php-debug` with `phpunit.xml.dist` (`phpunit.coverage.xml` is CI's standalone run). After changing a controller's constructor or return type, delete `generated/code/Iranimij/OpenFilter` and `dev/tests/integration/tmp/sandbox-*/generated/code/Iranimij` (stale interceptors). Admin Playwright specs snapshot and restore the OpenFilter tables; run `--project=admin-chromium` (serial). The host has no `var/`: pipe one-off PHP scripts through stdin (`warden env exec -T php-fpm bash -lc 'cd /var/www/html && php' < script.php`).

## Last CI run

None yet (repositories are created after hours in S7).
