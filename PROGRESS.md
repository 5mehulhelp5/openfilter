# PROGRESS

Fine-grained checkpoint for the OpenFilter build. Updated after every local slice and every merged PR. The Notion build log (07 · Build Brief, section 10) holds the per-milestone summary. Working-hours rule: no GitHub activity Mon–Fri 08:00–18:00 Europe/Berlin (`openfilter-dev-env/bin/after-hours`).

## Current milestone

**M0 · Foundation + search spike** (started 2026-10-13, target 2026-10-15).

## M0 slices

| # | Slice | State |
|---|---|---|
| S0 | Fixture `openfilter-dev-env` | in progress |
| S1 | `openfilter` skeleton + CI (own OpenSearch integration job) | in progress |
| S2 | `openfilter-hyva` + bundle skeletons, e2e workflow | todo |
| S3 | Hyvä verification → `docs/hyva-integration.md` | todo |
| S4 | Search spike → `docs/facet-counts.md` | todo |
| S5 | Index fields → `docs/index-fields.md` | todo |
| S6 | One-page specs in Notion | todo |
| S7 | After hours: repos, milestones, push, full matrix, merge, report | todo |

## Next step

Build the fixture store (`bin/setup`), then run the S1 unit tests RED → write `Model/Config` → GREEN.

## Open questions

None.
