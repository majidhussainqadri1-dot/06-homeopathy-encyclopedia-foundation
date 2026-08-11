# Requirements Traceability Matrix

| ID | Implementation evidence | Automated evidence |
|---|---|---|
| F06-FR-001 | `HE_V2_Domain::types()` and seeded controlled taxonomy | `tests/v2-invariants.php` type count |
| F06-FR-002 | structured fields, body system, medical schema validators | required-field/source/safety tokens |
| F06-FR-003 | `concepts.public_id`, aliases, language and merge lineage | schema/UUID/alias invariant |
| F06-FR-004 | indexed facets, A–Z, cursor and bounded limit | search/filter/cursor invariant |
| F06-FR-005 | normalized exact-word/alias search, multilingual index and autocomplete | normalization/private-exclusion invariant |
| F06-FR-006 | structured `references` table and validation | evidence/rights/quotation invariant |
| F06-FR-007 | red flags, safety, limitations, emergency boundary and no-cure copy | clinical-gate invariant |
| F06-FR-008 | draft/review/approval/schedule/publish state machine | transition and authorization invariant |
| F06-FR-009 | immutable version snapshots, hashes, history and diff | versions/diff route invariant |
| F06-FR-010 | integrity actions, public notice, replacement and events | correction/retraction invariant |
| F06-FR-011 | duplicate candidates, alias uniqueness, merge and redirect reconciliation | duplicate/merge invariant |
| F06-FR-012 | typed relations, provenance and bounded graph traversal | relation-type/limit invariant |
| F06-FR-013 | account bookmarks and sourced correction requests | bookmark/privacy invariant |
| F06-FR-014 | canonical URLs, Article/MedicalWebPage JSON-LD and integrity markup | SEO/public-state invariant |
| F06-FR-015 | research proposal fields, investigators, ethics, consent, conflicts and data class | research-schema invariant |
| F06-FR-016 | proposal→ethics→active→analysis→peer review→publication lifecycle | research-transition invariant |
| F06-FR-017 | successful-case consent, anonymization, PII scan, complete fields and `کامیاب کیس` | case-governance invariant |
| F06-FR-018 | dataset metadata plus purpose/lawful-basis/expiry access table | dataset-access invariant |
| F06-FR-019 | `sabri/v2/file-06` DTOs plus Files 05/20/21/22/23/24/25/26 contracts | contract-route invariant |
| F06-NFR-001 | native auth/object/state checks and direct-publish guard | auth/IDOR token checks |
| F06-NFR-002 | explicit DTO/privacy exporter/eraser/retention handling | public/private invariant |
| F06-NFR-003 | idempotency, outbox, retry/dead-letter, safe mode | reliability invariant |
| F06-NFR-004 | bounded cursor queries, indexes and lazy route assets | query-bound invariant |
| F06-NFR-005 | semantic UI, green tokens, icons, RTL, focus, zoom/reduced-motion CSS | CSS/JS invariants |
| F06-NFR-006 | trace IDs, health, event log and assurance evidence | observability invariant |
| F06-NFR-007 | schema lock, idempotent migration, repair/reindex and rollback guide | migration invariant |
| F06-NFR-008 | operations page, health, safe mode and bounded repair | operability invariant |
| F06-NFR-009 | declared WP/PHP compatibility and CI lint matrix | PHP 7.4/8.3 CI |
| F06-NFR-010 | en-US/ur-PK/ar, Unicode search and RTL presentation | localization invariant |
