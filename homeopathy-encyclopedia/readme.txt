=== Homeopathy Encyclopedia Foundation ===
Contributors: sabrihomeopathy
Tags: encyclopedia, knowledge graph, research, homeopathy, evidence
Requires at least: 6.1
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later

Canonical, versioned and governed homeopathy encyclopedia, research registry and knowledge graph for the Sabri Social Homeopathy Platform.

== Description ==

File 06 owns permanent canonical knowledge entries, the fixed sixteen-type taxonomy, aliases and transliterations, structured references and evidence grades, immutable published versions, corrections and retractions, duplicate merges and redirects, typed knowledge relations, the public research registry, successful-case governance, restricted dataset-access requests and versioned consumer contracts.

Public reading is available without an account where a record is published, independently reviewed and safety-approved. Creation, review, publication, merge, research and dataset operations require File 00-aware capabilities and native object/state checks.

== Principal controls ==

* Stable UUID public IDs and canonical URLs.
* Sixteen governed knowledge types and controlled body systems.
* English (US), Urdu and Arabic/RTL-ready data and presentation.
* Structured sources: source type, author, edition, volume/page, URL/DOI, evidence grade, rights and quotation count.
* Immutable published snapshots, historical-version viewing, diffs and supersession notices.
* Correction, retraction, replacement, appeal and consumer-event records.
* Alias collision protection, duplicate detection, merge lineage and redirect reconciliation.
* Typed, bounded knowledge graph with provenance.
* Research proposal-to-publication state machine, ethics and consent gates.
* Successful cases require verified consent, anonymization, baseline, intervention, follow-up, adverse-event reporting, limitations and the tag “کامیاب کیس”.
* Dataset metadata is public only when approved; access grants are purpose-bound, expiring and audited.
* REST contract namespace: /wp-json/sabri/v2/file-06/.
* Reliable event outbox, idempotency, rate limiting, safe mode, health and bounded repair.
* Privacy export/erasure coverage and guarded non-destructive uninstall.
* File 20/22/23/24/25/26 integration providers without duplicating companion ownership.

== Installation ==

1. Back up the database and files.
2. Install/activate File 00 and the platform foundation contracts used in the target environment.
3. Upload the ZIP and activate the plugin on staging.
4. Open Encyclopedia > Operations and verify schema, cron, outbox and contract health.
5. Run fresh-install and upgrade/migration acceptance, then reindex.
6. Test real Founder, editor, reviewer, researcher, verified-doctor, member and guest journeys.
7. Complete rollback and restore rehearsal before production approval.

== Privacy ==

Public DTOs are allowlisted. Drafts, rejected records, private research, dataset grants, conflicts, consent records and private notes are not public-index data. Published institutional knowledge and integrity history may be retained after account erasure in de-identified form.

== Changelog ==

= 2.0.0 =
* Implemented the complete File 06 v1.0 plan baseline: canonical identities, versions, evidence, integrity, graph, research, datasets, integrations, security, privacy, operations and deterministic packaging.

= 1.0.0 =
* Prior corrective foundation release candidate.
