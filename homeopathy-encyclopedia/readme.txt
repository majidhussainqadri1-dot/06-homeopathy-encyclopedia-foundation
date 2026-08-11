=== Homeopathy Encyclopedia Foundation ===
Contributors: sabrihomeopathy
Tags: encyclopedia, knowledge graph, research, homeopathy, evidence
Requires at least: 6.1
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.4.7
License: GPLv2 or later

Canonical, versioned and governed homeopathy encyclopedia, research registry and knowledge graph for the Sabri Social Homeopathy Platform.

== Description ==

File 06 owns permanent canonical knowledge entries, the fixed sixteen-type taxonomy, aliases and transliterations, structured references and evidence grades, immutable published versions, corrections and retractions, duplicate merges and redirects, typed knowledge relations, the public research registry, successful-case governance, restricted dataset-access requests and versioned consumer contracts.

Public reading is available without an account where a record is published, independently reviewed and safety-approved. Creation, review, publication, merge, research and dataset operations require File 00-aware capabilities plus File 06 native object/state/type scope. The 2.4.7 candidate also requires explicit editor knowledge-type assignment and reviewer assignment for governed review decisions.

== Principal controls ==

* Stable UUID public IDs and canonical URLs; raw numeric database IDs are not a public API contract.
* Sixteen governed knowledge types and controlled body systems.
* Dynamic source-language identity plus nine governed translation targets, with RTL support where applicable.
* Structured sources: source type, author, edition, volume/page, URL/DOI, evidence grade, rights and quotation count.
* Immutable published snapshots, historical-version viewing, diffs and supersession notices.
* Correction, retraction, replacement, appeal and consumer-event records.
* Alias collision protection, duplicate detection, merge lineage and redirect reconciliation.
* Typed, bounded knowledge graph with provenance.
* Research proposal-to-publication state machine, ethics and consent gates.
* Successful cases require verified consent, anonymization, baseline, intervention, follow-up, adverse-event reporting, limitations and the tag “کامیاب کیس”.
* Dataset metadata is public only when approved; access grants are purpose-bound, expiring and audited.
* Future-18 scholarly metadata is staged, bounded and human-reviewed before evidence elevation.
* Governed translations bind to the current source version and require independent review before publication.
* REST contract namespace: /wp-json/sabri/v2/file-06/.
* Reliable event outbox, idempotency, rate limiting, safe mode, health, bounded repair and serialized maintenance workers.
* Future-18 migrations use bounded resumable preflight/postflight reconciliation and remain fail-closed until ready.
* Privacy export/erasure/legal-hold coverage and guarded non-destructive uninstall.
* File 19/20/22/23/24/25/26 integration boundaries remain explicit without duplicating companion ownership.

== Installation ==

1. Back up the database and files.
2. Install/activate File 00 and the platform foundation contracts used in the target environment.
3. Upload the ZIP and activate the plugin on staging.
4. Open Encyclopedia > Operations and verify schema, migration readiness, cron leases, outbox and contract health.
5. Run fresh-install and supported upgrade/migration acceptance, then reindex.
6. Test real Founder, assigned editor, assigned reviewer, researcher, verified-doctor, member and guest journeys.
7. Complete browser/RTL/accessibility/cache, backup/restore and rollback rehearsal before production approval.

== Privacy ==

Public DTOs are allowlisted. Drafts, rejected records, private research, dataset grants, conflicts, consent records and private notes are not public-index data. Published institutional knowledge and integrity history may be retained after account erasure in de-identified form under the governed retention/legal-hold rules.

== Changelog ==

= 2.4.7 =
* Eighth ten-round corrective candidate: research external-review assignment binding, authoritative public research privacy, provenance-safe graph merges, current-snapshot search evidence, stale-review concurrency guards, native dashboard scope, and accepted-state monotonic research-integrity apply.

= 2.4.6 =
* Seventh ten-round corrective candidate: fail-closed research state/public surfaces, governed taxonomy REST ownership, successful-case privacy, publication-gated public identifiers, and retry-safe maintenance cursors.

= 2.4.5 =
* Sixth ten-round corrective candidate: canonical UUID Future reads, stable/recoverable idempotency reservations, V24-owned serialized Future maintenance, CAS-safe core leases, progressive reviewer privacy erasure, normalized watchlist booleans, retry-safe retraction cursors, and exact release/QA truth alignment.

= 2.4.4 =
* Fifth fresh ten-round corrective candidate: fail-closed Future routes during migration, minimized public research DTOs, deterministic research save concurrency, canonical source-language ownership, research reviewer privacy lifecycle coverage, unconditional published-research admin immutability, and refreshed exact-head QA.

= 2.4.1 =
* Second fresh 80-round hardening candidate: native object/type authorization, explicit editor/reviewer assignments, admin/composer scope enforcement, serialized core/Future maintenance, secure scheduled-publication ownership, bounded postflight migration readiness, canonical public-ID DTO enforcement and refreshed automated QA.

= 2.4.0 =
* First 80-round Future-18 hardening: claim evidence graph, provenance, scholarly adapters, retraction watch, impact queue, freshness/radar, citations, watchlists, governed translations, command center, privacy and fail-closed migration controls.

= 2.0.0 =
* Implemented the complete File 06 v1.0 plan baseline: canonical identities, versions, evidence, integrity, graph, research, datasets, integrations, security, privacy, operations and deterministic packaging.

= 1.0.0 =
* Prior corrective foundation release candidate.
