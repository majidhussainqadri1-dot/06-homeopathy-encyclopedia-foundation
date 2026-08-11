# Changelog

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
