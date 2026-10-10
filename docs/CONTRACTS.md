# File 06 — Current Candidate Contract Registry

> Source-level reference for the **2.4.20 candidate**. This file is not a claim of staging acceptance, production deployment, DB migration success or live runtime parity.

- Canonical owner: **File 06** (knowledge entries, identities/aliases, versions, sources, relationships, research records and corrections).
- Runtime / contract: **2.4.20 / 2.4.20**.
- Core schema target: **10**; Future subsystem schema target: **2**.
- API namespace: `sabri/v2/file-06`.
- Runtime contract authority: `he_contract_descriptor()` in `homeopathy-encyclopedia/homeopathy-encyclopedia.php`; use its current output rather than this static registry for deployed-provider negotiation.

## Native commands

`create_entry_draft`, `assign_editor_type_scope`, `assign_entry_reviewer`, `assign_research_reviewer`, `submit_entry_review`, `publish_entry_version`, `merge_concepts`, `submit_research`, `submit_research_review`, `submit_integrity_action`, `transition_integrity_action`, `bounded_reindex`, `stage_external_metadata`, `review_external_metadata`, `scan_duplicate_candidates`, `queue_consumer_revalidation`, `save_governed_translation`, `review_governed_translation`, `publish_governed_translation`, `manage_watchlist`, `map_researcher_orcid`.

Mutations remain **File 06 native** and require current File 00-compatible identity/claims, per-object and per-state authorization, nonce/request validation, idempotency, concurrency protection, and audit/outbox controls where applicable. A descriptor-listed command is not an assertion that an external companion provider is available.

## Native queries

`search_knowledge`, `get_entry`, `get_related_graph`, `browse_research`, `health`, `get_type_schemas`, `get_claim_graph`, `get_provenance`, `get_time_machine`, `get_freshness`, `get_research_gaps`, `get_integrity_command_center`, `get_governed_public_translations`.

Also available through native guarded routes: entry versions/diff, duplicate candidate inspection, bookmark state, authorized research/dataset metadata views, governed public translations, and owner-native corrections. Only eligible approved data may appear in a public DTO; source/internal numeric identifiers, personal/clinical identifiers, restricted research and draft state must be excluded.

## Publishing/events

Events are past-tense facts with replay/idempotency requirements. The descriptor builds its events from `HE_V2_Integrations::published_events()` plus later explicit events, including:

- `ResearchPublicationCorrected.v1`
- `File06IntegrityStateChanged.v1`
- `EncyclopediaEntryScheduleInvalidated.v1`
- `KnowledgeEvidenceChanged.v1`
- `KnowledgeFreshnessDue.v1`
- `KnowledgeTranslationOutdated.v1`
- `KnowledgeTranslationUpdated.v1`
- `File06EditorScopeChanged.v1`
- `File06ReviewerAssigned.v1`
- `File06ResearchReviewerAssigned.v1`
- `ResearchStateFailClosed.v1`
- `EncyclopediaDraftRolledBack.v1`
- `ResearchDraftRolledBack.v1`

Original v2 published events retained through the integration provider include entry published/scheduled/corrected/retracted, concept merged, research published and research retracted events; inspect the provider output before subscribing.

## Canonical ownership and companion boundaries

| Companion | Contract / boundary |
|---|---|
| File 00 | Only identity/role/suspension/current-claims authority; File 06 revalidates native object scope. |
| File 05 | Course/lesson facts remain File 05; consume File 06 canonical knowledge IDs and versions only. |
| File 12 | PDF bytes/reader and bibliographic objects belong to File 12; cite/link approved records, never duplicate stored PDFs. |
| File 15 | Radar/repertory has its own owner; File 06 exposes versioned knowledge/research only. |
| File 16 | AI retrieval of reviewed references only; **no autonomous clinical diagnosis or prescription authority**. |
| File 17 | Research collaboration transport, never public sensitive datasets. |
| File 19 | Notification delivery, preferences, quiet hours and retries; File 06 owns only watchlist state and factual knowledge-change events. |
| File 20 | Shell, header/navigation/layout and route integration owner. |
| File 21 | Social/news posts, engagement and feed ranking are native to File 21; Encyclopedia supplies permanent knowledge projections and correction updates only. |
| File 22 | Composer orchestrates native File 06 create/edit, with no independent knowledge write store. |
| File 23 | Publishing dashboard consumes federated inventory/status and owner-authorized actions; no duplicate canonical records. |
| File 24 | Central privacy/security assurance; File 06 must still enforce access, retention and fail-closed behavior internally. |
| File 25 | Visual components, profile/timeline presentation and design tokens; File 06 owns knowledge semantics. |
| File 26 | Global search/discovery is derived; File 06 handles native searchable index and click-time publication/visibility checks. |

## Future-18 governance

File 06 v1.1 adds `F06-FUT-001..018`: claim evidence, provenance, retraction watch, bibliographic connectors, clinical trials, ORCID, DOI/DataCite, MeSH, advisory deduplication, graph exploration, time-machine, consumer impact, freshness, research gaps, citations, watchlists, governed translations and integrity command center. External scholarly metadata must be staged and human-reviewed: no automatic promotion to medical or scientific authority.

## Release truth

The 2.4.20 source candidate **must not** be equated with the default `main`, an installed plugin, a verified target database or a production system. Source review, exact-HEAD QA, reproducible artifact + checksums, controlled WordPress staging, verified companion contracts, backups, rollback rehearsal, Founder acceptance, deployment and live retest are separately required.
