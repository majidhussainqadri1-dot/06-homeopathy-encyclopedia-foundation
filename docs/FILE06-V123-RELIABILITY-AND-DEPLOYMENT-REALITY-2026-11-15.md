# File06 V12.3 — 15 Nov 2026, additional governed reliability and live-evidence-source review

**Scope:** Draft PR #21 stacked on PR #17. **This is a source candidate, NOT a deploy or operational signoff.** No real patient case, privileged credential, user data, live SQL or site plugin was modified.

## Evidence-first defects: newly found in this review

| ID | Root cause | RED evidence (real disposable WordPress/MariaDB) | Source correction | Live |
| --- | --- | --- | --- | --- |
| SRC-11 | `HE_V22_Governance::secure_merge` reported false retryable version-conflict after an already committed merge when the synchronous event observer threw | Run `43709857228`: `source_merged=YES; events=1; outbox=1; returned=he_version_conflict` | Move post-COMMIT observer/index work outside rollback catch, preserve canonical success; set durable reindex flag on index failure | NOT VERIFIED |
| SRC-12 | Ordinary `HE_V2_Domain::emit_event` likewise reported an event failure after the event/outbox were committed | Run `43710038803`: committed event/outbox but `event_observer_exception` | Isolate optional post-COMMIT observer exception and retain canonical returned event ID and durable outbox | NOT VERIFIED |
| SRC-13 | `HE_V22_Governance::health` counted outbox `status='dead'` while the actual worker stores `dead-letter` | Run `43711833144`: `DEADLETTER_HEALTH_EVIDENCE before=0 db_count=1 reported=0` | Count exact `dead-letter` string; a real disposable queue exhaustion row is now tested | NOT VERIFIED |
| SRC-14 | `HE_V22_Governance::reconcile_outbox` replayed **inbound** `object_type='external'` audit events as locally published outbound messages; health and dry-run repair could count them as missing owner outbox | Run `43712129136`: `OWNER_DIRECTION_EVIDENCE type=external before=0 after=1 reconciled=1` | Add `object_type<>'external'` owner-direction filter in reconciliation, health and repair counts; 5 real DB assertions | NOT VERIFIED |

**Four distinct source candidate defects** newly reproduced then fixed in this task, not four confirmed production incidents. Previous SRC-01..SRC-10 were earlier separate draft findings, not verified as installed-live.

## Independent QA / diagnostics gaps

- Event and outbox writes have distinct failure points; previous fixture only checked the event table. Actual `events` AND `outbox` INSERT failures are now independently fault-injected in a disposable DB, 9/9 assertions each. This is additional coverage rather than a new separate production source defect.
- The pre-existing metadata-only live freeze inventoried 16 of the 28 File06-owned schema tables. Registry-based source test now enforces **15 core + 12 Future + 1 governance quarantine = 28** distinct identifiers, plus masked failure-option presence and safe DB version/estimated row counts. The script refuses to run absent explicit authorization, WP-CLI or a valid WP path. No real Hostinger freeze was possible.
- Post-COMMIT search-index failure has its own disposable trigger; committed merge stays true and a reindex-required flag is persisted.
- Full branch CI runs PHP/JS syntax, inherited source QA, 20 sequential complete regression passes, deterministic package build comparisons and disposable native WordPress with pinned File00/06/15/26 versions. **No status transfers across different commit SHAs.**

## Latest complete candidate checks

- Real disposable WordPress 7.0.1, PHP 8.3, MariaDB 11.4: Run **`43712346090`**, SUCCESS at `13a8a06e475a16a8826405cd80bd98a11fef502b` (this is the source HEAD *before this documentation commit*). Distinct fixtures: 37 File26, 23 File15, 9 event-write failure, 9 outbox-write failure, 7 merge-observer failure, 3 ordinary observer failure, 8 reindex failure, 3 dead-letter health, 5 incoming-audit isolation = **104 runtime assertions PASS**. Separate metadata-only freeze **28/28 table names** PASS. These are synthetic runtime tests only.
- Full source QA at that same SHA: run **`43712346019`** (check exact final conclusion before relying on it). A new commit must receive its *own* green result.
- Browser search/autocomplete hardening is separately available in Draft PR #20; independent adversarial follow-up Draft PR #22, based on #20, avoids overwriting concurrent changes. Never combine their green checks with the exact source SHA of this PR without rebasing/integration retest.

## Remaining / NOT RELEASED

V12.3 has **49 requirement IDs**: 19 FR + 10 NFR + 2 CEN + 18 FUT, each still **unverified in actual deployed environment**. Source paths exist but are not equivalent to all semantics or real expert review. File26 indexing remains **PROPOSED**, not approved for activation or tombstone cutover. Remaining release gates: original citations and source rights, real File00 patient/member consent where relevant, File05/10/12/15/16/21/26 and other owner parity, p75/p95 performance, keyboard/RTL/320px/400%-zoom/WCAG 2.2 AA on actual devices, independent reviews, backup/rollback rehearsal, actual installed build/DB/option/migration/log freeze, Founder approval, controlled deploy and live retest.

### Live reality fields (not inferred from repo)
- Repository **main HEAD:** `c6f44e923f2a6f2ed82d967d18422624169b2770` (README only).
- **PR21 candidate HEAD:** read exact GitHub PR head at review time.
- **Deployed Version:** UNVERIFIED.
- **DB Version:** UNVERIFIED.
- **Migration State:** UNVERIFIED.
- **Live Verification Status:** UNVERIFIED.
- **Live connectivity:** WordPress Jetpack `site_disconnected`; WPVibe direct + relay reported HTTP 530, neither proves origin outage.

**NO MERGE / NO DEPLOY / NO RESOLVED claim.** Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔
