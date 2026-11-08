# File 06 — Current Schema Manifest (v12 candidate, 2026-11-08)

**Current source targets:** File 06 plugin 2.4.20 (with an unmerged v12 corrective candidate); **core DB schema 10**, **Future DB schema 2**. These are GitHub code targets, not verified deployed schema versions.

## Canonical core domain

Core schema 10: concepts, aliases, versions, references, relations, reviews, integrity_actions, research, dataset_access, events, outbox, idempotency, bookmarks, rate_limits, search_index. Core ownership and required columns are defined by `HE_V2_Schema::required_tables()/required_columns()`. The v12 layer uses existing `research.consent_json`, `references.link_status`, `claims` and `impact_queue`; no new tables are introduced.

## Future schema 2

`HE_V24_Future_Schema::required_shape()` governs claims, claim_evidence, concept_mappings, similarity, provenance, external_records, researcher_ids, freshness, impact_queue, research_gaps, watchlists and translations. Do not infer deployed schema parity from the manifest.

## v12 additions and verification

- `he_v12_reference_link_cursor` and `he_v12_reference_link_last_checked`: scan progress/options, not schema columns.
- Consent attestation uses purpose, locale, policy_version, actor/time and consent state in governed `consent_json`; institutional withdrawal restricts public visibility and queues impact.
- File 06 ↔ File 15 canonical remedy adapter uses existing event and outbox UUID identity; no consumer table writes.
- No destructive migration, silent review elevation, or wholesale import of foreign scholarly content is permitted.

## Historical note

Earlier schema-7 manifest described the first `v2.0` baseline. Its table list still broadly applies to the core but the version is superseded by verified source declarations above. Further changes require exact staging schema inspection, backups, rollback and additive migration proof.
