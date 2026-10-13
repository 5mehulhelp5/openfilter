# PROGRESS

Fine-grained checkpoint for the OpenFilter build. Updated after every local slice and every merged PR. The Notion build log (07 · Build Brief, section 10) holds the per-milestone summary. **Working-hours rule (Iman, 2026-10-13): no GitHub activity Mon–Fri 08:00–18:00 Europe/Berlin** (`openfilter-dev-env/bin/after-hours` exits 1 inside them). **One chat per milestone** (Iman, 2026-10-13): each chat ends with this file, the build log and the prompt for the next chat.

## Current milestone

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
4. Merge in order (retarget S5 to `main` before merging S4), e2e on dev-env green (Hyvä 1.4.2 + 1.5.2), update this file and the build log, M0 report, then stop. M1 starts in a new chat.

## M1 slices (local, stacked on `feat/m0-s5-index-fields`)

Plan: `~/.claude/plans/pasted-content-id-b16b-read-https-app-n-glittery-aho.md` (approved 2026-10-13 09:20). Ledger: `openfilter-dev-env/modules/M1-ledger.md`. Each slice is its own branch `feat/m1-sN-*`, stacked.

| # | Slice | State |
|---|---|---|
| S1 | Schema (`openfilter_attribute`, `_attribute_store`, `_builtin`, `_builtin_store`), `FilterConfigInterface` + repository, per-store cache (cache type `openfilter`, enabled by a data patch) | done locally: `feat/m1-s1-config` |
| S2 | Transliterator, slugs, data patches | in progress |
| S3–S9 | codec, multi-value filters, strategies, stats, built-ins, normaliser + coexistence, budgets | open |

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
| openfilter (M0 local) | 24 / 105 assertions | 26 / 58 (skeleton 4, DisjunctiveCounts 18, IndexHooks 4) on 2.4.9 + OpenSearch 2.5 | PHPCS zero, PHPStan 6 clean, coverage `Model` 99.2 % (gate ≥ 85 % on Model/Search, Model/Config), mkdocs --strict ok |
| openfilter-hyva (M0 local) | 4 / 21 | 1 / 3 | PHPCS zero, PHPStan 6 clean |
| openfilter-dev-env | — | — | Playwright 13 (12 desktop: smoke 6, hyva-layered-nav 5, facet-counts 1; admin login setup 1); screenshots project 4 (not a gate) |

## Open questions for Iman

None blocking. FYI: the M0 "done when" (CI green on the full matrix) can only be checked after 18:00 today.

## Known failing or skipped tests

- `hyva-layered-nav.spec.ts` skips itself once OpenFilter's own templates are active (M3), by design.

## Fixture gotchas (see openfilter-dev-env README)

Docker has 7.6 GB: stop the `openlabel` stack (`warden env stop` in openlabel-dev-env) before starting `openfilter`; never run integration tests and Playwright at once. After `warden env up` of a new env, Traefik may answer "404 page not found" until `docker restart traefik`. After `setup:di:compile` (e.g. `bin/install-modules`), delete `generated/metadata` before integration tests or the sandbox install fails. Composer `--no-plugins` in the container; OpenSearch pinned to 2.5.0 (Apple Silicon); CSP enforce flag set via SQL. Stock Hyvä 1.4 filter groups use `x-defer="intersect"`: Playwright must scroll a group into view before opening it. Local PHPStan: `vendor/bin/phpstan analyse -c modules/phpstan-local.neon` in php-fpm (php-debug lacks the generated code).

## Last CI run

None yet (repositories are created after hours in S7).
