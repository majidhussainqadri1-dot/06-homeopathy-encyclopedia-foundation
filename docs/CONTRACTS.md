# File 06 Contract Registry

Contract version: `2.0`.

## Commands

- `create_entry_draft`
- `submit_entry_review`
- `publish_entry_version`
- `merge_concepts`
- `submit_research`

Mutating REST calls require authentication, File 00-compatible membership state, native capability/object/state authorization, `X-WP-Nonce`, `Idempotency-Key`, validation, rate limiting and audit/outbox evidence.

## Queries

- `search_knowledge`
- `get_entry`
- `get_related_graph`
- `browse_research`
- entry versions and diff
- duplicate-candidate inspection
- bookmark state

Public queries emit allowlisted DTOs only and recheck publication/review/safety/merge state at request time.

## Events published

- `EncyclopediaEntryPublished.v1`
- `EncyclopediaEntryScheduled.v1`
- `EncyclopediaEntryCorrected.v1`
- `EncyclopediaEntryRetracted.v1`
- `KnowledgeConceptMerged.v1`
- `ResearchPublicationPublished.v1`
- `ResearchRecordRetracted.v1`

Events are past-tense facts. Delivery is at-least-once through a local outbox; consumers must deduplicate.

## Companion boundaries

- File 00: identity, suspension, institutional Founder and capability assertions.
- File 05: learning links consume canonical IDs; no lesson duplication.
- File 20: route/shell presentation only.
- File 21: discovery/engagement projections only.
- File 22: creation orchestration through native File 06 commands.
- File 23: federated inventory and native links; no duplicate backend.
- File 24: assurance evidence only; native enforcement stays in File 06.
- File 25: public component and token contract; File 06 owns semantic content.
- File 26: derivative search connector; click-time visibility remains File 06.
