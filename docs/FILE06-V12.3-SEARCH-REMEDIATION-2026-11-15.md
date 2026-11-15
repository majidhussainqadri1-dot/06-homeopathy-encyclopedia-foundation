# File 06 — V12.3 source safety completion review (2026-11-15)

## Scope and immutable evidence boundary

- Governing scope: **Sabri-Website-Master-Plan-V12.3.docx**, File 06: **49** requirements (19 FR, 10 NFR, 2 CEN, 18 FUT); original `docs/FILE-06-V12.3-TRACEABILITY-2026-11-14.md` requires individual acceptance, not a path-only inventory.
- Repository: `majidhussainqadri1-dot/06-homeopathy-encyclopedia-foundation`.
- **Actual main HEAD before this branch**: `c6f44e923f2a6f2ed82d967d18422624169b2770` (README only).
- **Draft source baseline**: PR #17 head `136b067033d32962aab0c386e08a206c83f64a24`. This branch is based on PR #17 and is **not** `main`, staging or production.
- This independent work does not merge PR #17 or activate the proposed File 26 search connector.
- **Production installed version, deployed checksums, DB schema/options/migrations, logs, and parity remain unknown** (tracking issue #18). A Jetpack `site_disconnected` state and a WPVibe HTTP 530 access error are **not** evidence of a live plugin defect.

## Newly confirmed source candidate issues and corrections

| Source defect | RED evidence | Candidate correction | GREEN evidence | Live |
|---|---|---|---|---|
| S15-01 — Search SQL or schema failure could yield a misleading empty success/PHP warning; legacy REST path not fail-closed | Workflow `43684812302` | `HE_V22_Search::checked_results()`, 503 WP_Error, short-circuit all primary/fallback/autocomplete queries, propagate error without public success headers | Workflow `43684972413`, 20/20 full source suites | **UNVERIFIED** |
| S15-02 — Public `entries` and `autocomplete` caching and separate old REST fallback could retain corrected/retracted scholarly data | Workflows `43685195988`, disposable WP `43685411987` | `private, no-store` for both controllers; direct `HE_V2_API` handlers now share the guarded search implementation | Full source `43685520249`, disposable WP `43685520223` | **UNVERIFIED** |
| S15-03 — Draft WordPress post alias leaked through autocomplete if canonical row remained approved during a state drift | Disposable WP `43685754769` | Both autocomplete queries now require matching WordPress `post_type=he_entry` and `post_status=publish`, besides canonical review/safety/version gates | Disposable WP `43685863479` (16/16 local search checks) | **UNVERIFIED** |
| S15-04 — Canonical entry body had public 120s + stale 300s cache and version-only ETag, not withdrawal-aware | Disposable WP `43685991363` | Canonical entry GET is `private, no-store`; removed version-only ETag | Disposable WP `43686068285` | **UNVERIFIED** |
| S15-05 — Aborted list request could clear current `aria-busy`; older autocomplete response could overwrite latest suggestions | Source `43686275668` | Front-end request generations, abort control, stale response guards and keyboard dismissal | Source `43686358524`, disposable WP `43686358533` | **UNVERIFIED** |

**New source defects identified: 5 distinct categories. New source candidate fixes: 5. Live-verified fixes: 0.** S15-02 includes old and intercepted API routes and is counted once as a common caching root cause; S15-05 combines list and suggestions concurrency as one source issue. These are separate from PR #17's earlier 10 source candidate findings; no claim of a global zero-defect release is made.

## Exact-head source and disposable runtime evidence (before this report-only commit)

- Functional source head: `c0d3b9111b5f533291f2908e298341d78dd56acb`; compare to PR #17 baseline: 13 commits, 9 paths changed, including 3 new tests/QA files.
- Source workflow `43686358524` **SUCCESS**, PHP/JS syntax, new deterministic browser and DB negative cases, original inherited suites, **20/20** sequential full QA passes, double deterministic package reproduction.
- Disposable integration workflow `43686358533` **SUCCESS**, WordPress 7.0.1 / PHP 8.3 / MariaDB 11.4, four pinned File00/06/15/26 plugin sources. **37 File26 + 23 File15 + 9 transactional-fault + 20 search REST = 89 passed assertions**. Fixtures are synthetic, not a substitute for medical review or genuine patient consent.
- Two legacy auto-apply workflows have previously returned `failure` with zero jobs, unrelated to the verified source regression; do not label these as confirmed application defects without job-level evidence.
- Re-run both workflows against the **final exact source+documentation commit**. No historical success automatically certifies future heads.

## Remaining gates: no release claims

1. G1: read-only LIVE freeze: actual Hostinger plugin ZIP/file SHA256 and runtime, WordPress/PHP/DB tables, `wp_options`, migrations, errors and original backups — **BLOCKED**.
2. Compare exact Hostinger artifact with repository main and full candidate; conduct **Deployment-Parity Audit** before any production application-code diagnosis — **BLOCKED**.
3. All File 06 V12.3 49 requirements: per-requirement semantic positive/negative browser/API/role checks, independent editorial rights, citation source/edition/page/provenance, clinical safety and patient consent — **INCOMPLETE**.
4. Real File00/05/10/12/15/16/19/20/21/22/23/24/25/26 provider parity; event retry, retraction/tombstones, cursor/resume and owner-safe caching — **PARTIAL**.
5. File26 source `status=proposed`, no operational search cutover/tombstone approval. File15 cross-file consumer still needs real staging reconciliation and failover evidence — **HOLD**.
6. WCAG 2.2 AA browser and screen-reader checks, RTL/Urdu/Arabic, 320–1920px layout, zoom 200–400%, real p75/p95 performance — **UNVERIFIED**.
7. Exact installed package on Hostinger staging; fresh/upgrade/idempotent migrations, backup restore and rollback; two independent human reviews; Founder sign-off — **UNVERIFIED**.
8. Only after permitted deployment and verified live retest may a defect be declared **Resolved**. **No production mutation was performed**.

## Mandatory incident fields

| Field | Status |
|---|---|
| Repository main HEAD | `c6f44e923f2a6f2ed82d967d18422624169b2770` |
| Draft source baseline | `136b067033d32962aab0c386e08a206c83f64a24` |
| Last green functional source HEAD | `c0d3b9111b5f533291f2908e298341d78dd56acb` |
| Proposed runtime and contract | `2.4.20` (unmerged candidate only) |
| Proposed core / Future schema | `10` / `2` (source only) |
| Exact deployed version | **UNVERIFIED** |
| Actual live DB version | **UNVERIFIED** |
| Migration State | **UNVERIFIED** |
| Live Verification Status | **UNVERIFIED** |
| Source corrected / production resolved | **5 / 0** |
| Release disposition | **HOLD — DRAFT / NO MAIN MERGE / NO DEPLOY** |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**
