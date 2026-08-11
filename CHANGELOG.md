# Changelog

## 2.4.11 — Twelfth fresh ten-round corrected candidate
- Ten sequential review/fix/retest rounds completed; defects corrected in rounds 1–10.
- Unified nuanced research public eligibility, privacy erasure object de-identification, research admin CAS, investigator compatibility, language canonicalization and public translation identifier hygiene.
- Repository candidate only; staging/live/operational evidence remains unverified.

## 2.4.10 — Eleventh fresh ten-round corrective candidate

- Completed ten sequential review → immediate-fix → regression rounds.
- Defect rounds: 1, 2, 3, 4, 5, 6, 7, 8, 9 and 10.
- Restricted/unconsented research permanent-ID requests now fail closed; successful-case public rendering independently requires anonymization and verified consent.
- Research and entry human-review records are atomically bound to the expected row version.
- Successful-case release rechecks both consent metadata and authoritative consent/anonymization flags.
- Entry and research integrity application plus integrity state transitions enforce object-bound File 00/File 06 authorization.
- Entry-integrity transactions fail closed when transaction start or commit certainty is unavailable.
- The owner transition command itself requires a fresh independent approval bound to current content instead of relying only on REST preflight.
- Runtime, contract, current invariants, aggregate QA, deterministic package labels and release documentation are aligned to 2.4.10.
- Staging/live/operational states remain unverified.

## 2.4.9 — Tenth fresh ten-round corrective candidate

- Completed ten sequential review → immediate-fix → regression rounds.
- Defect rounds: 1, 2, 3, 4, 5, 6, 7, 8, 9 and 10.
- Bound entry review decisions atomically to the exact reviewed row version/content/reference state.
- Restricted review evidence fingerprints to current immutable and pending-draft references rather than superseded historical provenance.
- Made idempotency response finalization fail closed across all governed mutation helpers, including reclaimed/stale reservation fencing.
- Made direct and scheduled entry publication atomic across domain state, snapshot, WordPress publication and commit confirmation.
- Added compensating rollback for partial snapshot reference/provenance binding failure.
- Made research publication atomic across File 06 domain state and its governed WordPress publication object.
- Locked research-integrity action/research rows and confirmed transaction start/commit before reporting application success.
- Runtime, contract, current invariants, aggregate QA, deterministic package labels and release documentation are aligned to 2.4.9.
- Staging/live/operational states remain unverified.

## 2.4.8 — Ninth fresh ten-round corrective candidate

- Completed ten sequential review → immediate-fix → regression rounds.
- Defect rounds: 1, 2, 3, 4, 5, 6, 7, 8, 9 and 10.
- Rate-limit storage failures now fail closed instead of silently authorizing mutations.
- Reclaimed idempotency reservations are fenced against stale-worker completion.
- Outbox workers claim deliveries with recoverable CAS processing leases.
- Event/audit and outbox pair persistence is atomic and fails closed on partial writes.
- Round 4 final-smoke regression correction: removed unsupported `@@session.in_transaction`; event/outbox persistence now uses an explicit MySQL-compatible local transaction with fail-closed start/commit handling.
- Outbox reconciliation and consumed-event recording are concurrency-safe.
- Reindex cursors advance only after successful row persistence.
- Background maintenance uses File00-backed File06 repair authority.
- Front-end research archive/search queries are constrained by File06 domain publication state.
- Runtime, current invariants, aggregate QA, deterministic package labels and release documentation are aligned to 2.4.8.
- Staging/live/operational states remain unverified.

## 2.4.7 — Eighth fresh ten-round corrective candidate

- Completed ten sequential review → fix → regression rounds.
- Defect rounds: 1, 2, 3, 4, 5, 6, 7, 9 and 10; round 8 clean.
- Bound research external scholarly review to explicit File 06 reviewer assignment.
- Closed the earlier V22 public research browse path that shadowed later successful-case/dataset privacy guards.
- Rebound outgoing graph provenance atomically when merging concepts.
- Restricted public search evidence grade/text references to the current immutable snapshot.
- Required optimistic expected-version binding for research and entry human reviews.
- Applied native editor/reviewer scope to publishing-dashboard inventory/item reads.
- Made research-integrity apply own accepted-state CAS and increment action row version.
- Staging/live/operational states remain unverified.

## 2.4.6 — Seventh fresh ten-round corrective candidate

- Completed ten sequential review → immediate-fix → regression cycles.
- Defects were found and corrected in rounds `1, 2, 3, 4, 5, 6, 7, 9, 10`; round `8` was clean.
- Closed research first-save state drift so non-published WordPress states cannot become domain-published by default.
- Made public freshness reads side-effect free while retaining persisted background freshness maintenance.
- Bound protected health access to File 00-backed File 06 authorization.
- Removed uncontrolled core REST taxonomy mutation surfaces outside the governed File 06 API.
- Prevented restricted, unconsented, non-anonymized and retracted successful-case detail rendering on public research pages.
- Made non-public research title/excerpt/robots fail closed.
- Publication-gated public replacement and graph-linked concept identifiers.
- Made freshness/research-gap maintenance cursors advance only after successful row work and propagate write failures.
- Aligned runtime, contract, plugin metadata, current-version invariants and aggregate QA to `2.4.6`.
- Staging/live/operational states remain explicitly unverified.

## 2.4.5 — Sixth fresh ten-round corrective candidate

- Added canonical UUID public Future-read contracts for claims, graph, time-machine, freshness and citations while preserving the numeric-ID public block.
- Canonicalized idempotency request fingerprints and added compare-and-swap recovery for stale unfinished reservations.
- Serialized Future maintenance in V24 and removed redundant V241 Future-maintenance ownership.
- Made core maintenance stale takeover/release compare-and-delete safe.
- Made reviewer-assignment privacy erasure cursor-progressive and normalized explicit watchlist boolean values.
- Made retraction-watch cursor advancement retry-safe on transient scholarly-provider failures.
- Re-reviewed multilingual/source-language/translation governance; Round 8 found no new source defect.
- Aligned runtime, contract, plugin readme, inherited invariants and aggregate QA to the exact `2.4.5` candidate, including the sixth-cycle regression suite.
- Defects were found and corrected in rounds `1, 2, 3, 4, 5, 6, 7, 9, 10`; round `8` was clean.
- Staging/live/operational states remain explicitly unverified.

## 2.4.4 — Fifth fresh ten-round corrective candidate

- Gated Future-table routes on completed migration readiness and kept those surfaces fail-closed during reconciliation.
- Minimized public research DTOs for restricted successful cases and dataset metadata.
- Corrected multi-writer research save concurrency accounting and canonical source-language ownership.
- Extended reviewer privacy lifecycle coverage to research posts and made published/corrected/retracted research immutability request-shape independent.
- Made V24 the authoritative dbDelta-safe Future schema and strengthened strict WordPress activation/reactivation runtime-log QA.
- Defects were found and corrected in rounds `1, 2, 3, 4, 6, 8, 9, 10`; rounds `5, 7` were clean.
- Staging/live/operational states remained explicitly unverified.

## 2.4.2 — Third fresh 80-round hardening candidate

- Performed a third independent 80-round source/QA review and immediately corrected defects found in 24 rounds.
- Repaired canonical source-language/alias consistency and made ambiguous cross-language aliases fail closed.
- Bound references to versions of the same concept, bounded rights statuses and required source provenance for knowledge relationships.
- Bound integrity transition/apply authorization to the actual concept/research object before early REST short-circuit execution; validated replacement objects.
- Required merge authorization on source and target plus a documented decision reason.
- Hardened research domain/WordPress publication-state parity, bounded public browse, investigator/conflict/dataset authoring, stale-form concurrency and published-research immutability.
- Bound dataset request/approval flows to public research state and research external-evidence review to assigned research reviewers.
- Added transactional pristine entry/research composer compensation, hard-delete guards, rollback cache repair and complete v2.4.2 uninstall cleanup.
- Replaced arbitrary watchlist objects with private validated concept/topic/research watches while keeping File 19 as notification delivery owner.
- Harmonized governed knowledge translations with the later ten-language policy: dynamic source language + nine translations, human review, current-source-version binding, canonical public translation read API and internal-ID stripping.
- Added bounded `ur-PK` → `ur` normalization with collision refusal and compatibility reads during migration.
- Corrected third-cycle CI assertion drift and the WP-CLI smoke-install URL; added WordPress 7.0.1/PHP 8.3 fresh-install and plugin lifecycle smoke evidence.
- Completed two separate fresh post-final-code review rounds with no new source defects.
- Final automated run `31454206508` passed PHP 7.4/8.3 lint, all invariant suites, deterministic packaging and WordPress runtime smoke.
- Staging/live/operational states remain explicitly unverified.

## 2.4.1 — Second fresh 80-round hardening candidate

- Added File 06-native editor knowledge-type assignments while preserving File 00 identity/current-claim authority.
- Added explicit entry and research reviewer assignments with scope, expiry and independent-review restrictions.
- Closed object-scope gaps across integrity, research, dataset, Future claims, translations and external evidence operations.
- Closed wp-admin and universal-composer knowledge-type scope bypasses.
- Required research integrity actions to reach `accepted` before correction/retraction apply.
- Disabled the inherited unverified scheduled-publication fallback; secure fingerprint-revalidated scheduling is authoritative.
- Serialized core and Future maintenance workers with expiring leases.
- Made Future preflight and postflight migration bounded, resumable and fail-closed until all completion markers are satisfied.
- Extended canonical public-ID/no-internal-ID policy to core entry/version/reference/graph/integrity DTOs.
- Added privacy export/erasure/legal-hold handling for File 06 editor/reviewer assignment metadata.
- Refreshed PHP 7.4/8.3, first-80, Future-18 and second-80 automated checks and deterministic packaging.
- Corrected stale repository status/readme/release metadata; staging/live status remains explicitly unverified.

## 2.4.0 — First 80-round Future-18 hardening

- Added hardened claim-evidence graph and current-version publication gates.
- Added hashed provenance, public DTO sanitization and canonical public Future routes.
- Added bounded scholarly adapters, identifier validation and human external-evidence review.
- Added durable impact acknowledgement/retry/dead-letter, freshness/radar, citation exports, watchlists and governed translations.
- Added Future privacy lifecycle, migration preflight and fail-closed Future readiness.

## 2.0.0 — Complete baseline implementation candidate

- Replaced the 1.0.0 foundation with canonical UUID concepts, structured evidence, immutable versions, integrity workflows, knowledge graph, research/dataset governance, versioned REST contracts, privacy/operations controls and deterministic packaging.
