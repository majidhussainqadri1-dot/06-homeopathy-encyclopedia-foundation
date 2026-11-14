# File 06 V12.3 — Continuation / Second-Review Evidence Ledger
**Recorded:** 2026-11-14 • **Repository only:** `06-homeopathy-encyclopedia-foundation` • **Draft PR #17**, based on Draft PR #16.

## Truth boundary
This is a controlled, source-branch-only follow-up under the original 49-ID V12.3 scope. All production assertions are blocked until authorized live deployed bytes, plugin version, MariaDB shape, migration, wp_options, runtime logs, and exact parity are examined. A disposable WordPress success is **not** Hostinger staging or Live success. No reviewer was impersonated and no original clinical claim or patient dataset was used.

## Newly discovered distinct SOURCE review findings

| ID | V12.3 IDs | Root-cause/source finding | Red-first evidence | Candidate correction | Current disposition |
|---|---|---|---|---|---|
| SRC-04 | F06-FR-019; F06-CEN-02 | File26 owner `list_batch` ignored unknown/mismatched `scope` keys, potentially returning a broader snapshot than requested | #43602197063, wrong connector negative test FAILED | Require empty scope or exactly `['connector' => SLUG]`; deny extra keys | Source retest PASS, no Live |
| SRC-05 | F06-FR-003; F06-FR-019 | Projection did not cross-check canonical row's `public_id` against emitted DTO `id` | Source review + negative fixture (not individually red-first) | Strict owner/DTO ID equality | Source retest PASS, no Live |
| SRC-06 | F06-FR-009; F06-CEN-02 | Click-time `(int)` coercion accepted malformed source version strings or fractional numbers | Source review + negative fixture (not individually red-first) | Canonical positive integer/string-version check | Source and real DB negative retest PASS, no Live |
| SRC-07 | F06-FR-008/009; F06-FR-003 | Native WP post edits could replace approved title/summary/body and canonical slug while retaining approved revision/public status | #43602599547 (content) and #43602961182 (slug), both real DB FAILED as expected | Compare full public fields and slug against immutable approved snapshot before permitting `publish`; mismatch goes pending | Real WP retest PASS, no Live |
| SRC-08 | F06-FR-011/012; F06-FUT-012 | Governed merge could combine remedy with a different knowledge type; File15 remedy consumer skips non-remedy target, risking stale mapping | #43603831717, real DB cross-type negative FAILED as expected | In locked transaction require same canonical type; return typed 422 error before mutation | Real WP same-type positive + cross-type negative PASS, no Live |
| SRC-09 | F06-NFR-006; F06-CEN-01 | File26 `health` previously claimed `review_and_safety_verified=true` from schema readiness alone | #43604225300, isolated health truth test FAILED as expected | Report `approval_status_flags_checked=true`, `review_coverage_attested=false`; do not claim actual human review | New source/DB retest required; no Live |

These are **6 distinct newly discovered source findings** in this continuation. SRC-07 covers both direct content and slug bypass manifestations of the *same* unreviewed-publication root cause; it is counted **once**. Previous session SRC-01/02/03 and two QA harness defects are separate historical records. Overall this task series contains **9 distinct candidate source findings + 2 earlier QA harness defects**, *not* 11 live defects. No production fix is marked resolved.

## Real WordPress/MariaDB evidence, pinned to exact source
- Earlier source verification, Run **#43604067390**: **20/20** sequential full suites PASS on the referenced exact commit in that run; do not transfer this approval to a later HEAD.
- Earlier disposable WordPress integration, Run **#43604067450**: **36/36** File06→File26 assertions PASS, **23/23** File06→File15 assertions PASS. Four plugins File00/06/15/26 activated in a separate WordPress 7.0.1, PHP 8.3, MariaDB 11.4 environment. Tests included synthetic same-type actual owner merge → File06 event/outbox → File15 inbox → mapping status `review_required`, idempotent replay and hash collision rejection.
- The latest additional File26 health-truth assertion and any later commits **must get new exact-HEAD source and disposable DB success** before the final release ledger says PASS. Never substitute an old SHA.
- Synthetic editorial eligibility is a fixture; the actual independent scholarly/medical reviewer, rights documents and production member/patient consent are **UNVERIFIED**.

## Specific review outcomes
1. **Original file/path truth:** All 49 original V12.3 requirement IDs checked for presence (19 FR, 10 NFR, 2 CEN, 18 FUT). The corresponding 49 source path references are now enforced as physically existing by automated inventory QA. This is **path evidence, NOT feature acceptance**.
2. **Current File26:** Proposal-only registry; no user-facing search activation, no cutover/tombstone certification. Every read needs current owner version and public/safety flags; no private research/clinical body in projection.
3. **File15 owner contract:** Actual disposable merge consumer and clinical comparison invalidation tested; real interdisciplinary medical review remains separately governed.
4. **Original publication safety:** Direct WordPress title, excerpt, content or slug mutation cannot keep the earlier approved source snapshot publicly active; a safe identical restore is allowed.
5. **Unresolved high-priority review risk:** **Issue #19**, possible post-commit `secure_merge()` persistence vs outbox emission gap. Source call order is suspicious but no disposable fault-injection result yet. **OPEN INVESTIGATION**, not counted fixed; merits owner transaction/outbox redesign or proven compensation/reconciliation.
6. **Original live evidence blocker:** **Issue #18**. WordPress Jetpack `site_disconnected`; WPVibe unavailable and HTTP 530 in prior access attempts. No installed File06 version/DB/schema/migration parity evidence; not proof of downtime or File06 runtime failure.

## 12-gate scorecard (NO fabricated percentages)

| Phase | Current evidence | Gate verdict |
|---|---|---|
| G0 — frozen master scope | 49 canonical IDs, exact source baseline and PR dependency path | Completed for ID/path inventory; semantic signoff not implied |
| G1 — live reality/parity | No authorized deployed ZIP, checksums, DB migration or site logs | **BLOCKED** |
| G2 — 49 semantics/69 catalogue overlap | 49 paths mapped; individual positive/negative journeys and historical 69 applicability incomplete | **PARTIAL** |
| G3 — protected Draft integration | Source candidate PR#17 stacked on #16, File26 proposed, File15 pinned for CI | **PARTIAL** |
| G4 — 19 core FR | Tests focused on identity, review/version guard, merge, owner connectors | **PARTIAL** |
| G5 — 18 FUT | Some source candidates; 18/18 provider/human acceptance not demonstrated | **NOT ACCEPTED** |
| G6 — 10 NFR | Basic authorization, schema, repeatable source QA; WCAG/perf/privacy/recovery unverified | **PARTIAL** |
| G7 — cross-file | Actual disposable File00/06/15/26, but 05/10/12/16/17/19/20/21/22/23/24/25 still not accepted | **PARTIAL** |
| G8 — visual/RTL/accessibility/performance | No device/assistive/p75/p95 field acceptance | **BLOCKED** |
| G9 — 2 independent reviews | Red-first adversarial source tests, but **not 2 independent human reviews** | **BLOCKED** |
| G10 — staging/rollback | No exact Hostinger staging build, restore rehearsal or Founder signoff | **BLOCKED** |
| G11 — Live release/retest | No authorized production package/deployment/retest | **BLOCKED** |

## Required five live fields
| Field | Current value |
|---|---|
| Repository main HEAD | `c6f44e923f2a6f2ed82d967d18422624169b2770` (README-only; not deployed evidence) |
| Candidate HEAD | Resolve PR #17 HEAD and exact successful run after final write |
| Deployed Version | **UNVERIFIED** |
| DB Version | **UNVERIFIED** |
| Migration State | **UNVERIFIED** |
| Live Verification Status | **UNVERIFIED** |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

**Release decision: HOLD / DRAFT / NO MAIN MERGE / NO LIVE DEPLOY.** No actual clinical or public data were supplied for release acceptance.
