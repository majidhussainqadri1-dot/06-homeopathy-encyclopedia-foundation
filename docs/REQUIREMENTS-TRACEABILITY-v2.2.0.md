# File 06 v2.2.0 — Requirements Traceability Against the Two New Governing Plans

**Frozen source coding head:** `a245bff73dac1877e0b859f81725f6396b543031`  
**Runtime:** `2.2.0`  
**Schema:** `8`  
**Contract:** `2.2`

## Governing documents

1. Sabri Social Homeopathy Platform — Three Central Plans Consolidated Governing Master Plan 2026.
2. SSH-F06-PLAN-2026-v1.0 — File 06 Homeopathy Encyclopedia Complete Master Plan 2026.

The matrix below records **source-level implementation**, not Hostinger staging or production acceptance.

## Functional Requirements — 19/19 source-implemented

| ID | Requirement | v2.2 source evidence | Verification evidence |
|---|---|---|---|
| F06-FR-001 | Fixed 16-type knowledge taxonomy | `HE_V2_Domain::types()`, `HE_V22_Type_Schemas::schemas()` | `tests/v2-invariants.php` fixed-type/type-schema counts |
| F06-FR-002 | Type-specific schemas | `class-he-v22-type-schemas.php`: per-type required/optional fields, body-system rules, editor save reconciliation and lifecycle validation | Type-schema validation/source invariants |
| F06-FR-003 | Canonical concept identity | schema `concepts.public_id`, aliases, UUIDs, canonical slug, merge lineage/redirect contracts | UUID/alias/merge invariants |
| F06-FR-004 | A–Z and faceted browse | search index facets for type/body system/language/source/review/safety/letter; bounded cursor pagination | `HE_V22_Search::search()` and source invariants |
| F06-FR-005 | Knowledge search | exact/phrase/token/alias/transliteration-alias search, bounded spelling recovery, safe autocomplete, public-only index | `class-he-v22-search.php`, search invariants |
| F06-FR-006 | Reference management | structured references table, evidence grades, bibliographic fields, rights state, quotation-word count, reference APIs | `class-he-v2-domain.php`, `class-he-v2-api.php`, reference invariants |
| F06-FR-007 | Clinical safety | required safety/limitations/emergency-boundary fields, safety status gates, public disclaimers, fail-closed publication | type-schema/public/research guards |
| F06-FR-008 | Entry authoring/review | draft/validation/review/approval/schedule/publish lifecycle, reviewer records, conflict flags, exact-content review binding | governance/review/schedule modules |
| F06-FR-009 | Versioning | immutable version snapshots, hashes, effective version, version history and diff support | domain version functions/API and invariants |
| F06-FR-010 | Correction/retraction | explicit integrity state machine, public notices, replacement IDs, appeal state, accepted-only transactional apply and events | `class-he-v22-integrity.php`, public guard |
| F06-FR-011 | Duplicate detection/merge | scoped duplicate finder, both-row optimistic versions, row locks, third-party alias collision stop, relation reconciliation | `HE_V22_Governance::find_duplicates/secure_merge` |
| F06-FR-012 | Knowledge relationships | typed relation list, bounded graph traversal, reference-provenance ownership validation | domain graph + v2.2 provenance guard |
| F06-FR-013 | Bookmarks and corrections | account-owned bookmarks, sourced correction/integrity submissions, consumer cannot directly overwrite canonical record | API/domain/privacy coverage |
| F06-FR-014 | Structured data/SEO | canonical entry URLs/version/integrity metadata, research permanent UUID URL, canonical link and JSON-LD, retracted noindex behavior | public/public-guard modules |
| F06-FR-015 | Research proposals | question/type/investigators/protocol/ethics/consent/conflicts/data class, wp-admin and REST governance | `class-he-v22-research-guard.php`, governance REST validation |
| F06-FR-016 | Research lifecycle | proposal → ethics review → approved/rejected → active → analysis → peer review → published → corrected/retracted; publish reauthorization and review gates | domain lifecycle + rest/research guards |
| F06-FR-017 | Successful cases | exact `کامیاب کیس` tag, observation label, consent, anonymization, baseline/intervention/follow-up/adverse events/limitations, PII scan | research guard + public safe rendering |
| F06-FR-018 | Dataset metadata/access | description/de-identification/lawful basis/access policy, restricted-by-default, purpose/expiry access grants, requester recheck | research/governance/data-access modules |
| F06-FR-019 | Consumer APIs | explicit read-only contracts for Files 05/12/15/16/21/26; File 26 v2.2 search/visibility/rebuild; freshness fields; no consumer write authority | `class-he-v22-consumers.php` |

## Non-Functional Requirements — 10/10 source-implemented

| ID | Requirement | v2.2 source evidence | Verification evidence |
|---|---|---|---|
| F06-NFR-001 | Object/field authorization | File 00 current assertions hard dependency, fail-closed provider behavior, native capability/object checks, non-enumerating 404, sensitive-route reauthorization | `class-he-v2-auth.php`, `class-he-v22-rest-guard.php` |
| F06-NFR-002 | Privacy lifecycle | explicit public DTO allowlists, no-cache/private surfaces, bounded export/erasure, de-identification, legal hold, dataset access privacy | `class-he-v2-privacy.php`, public guards |
| F06-NFR-003 | Reliability | idempotency keys, rate limiting, optimistic row versions, scheduled-content revalidation, outbox retry/dead-letter/reconciliation, safe mode | governance/schedule/operations modules |
| F06-NFR-004 | Performance | bounded query limits/cursors, bounded spelling candidates, bounded graph, conditional assets, background migration/reindex/repair | search/domain/governance/public modules |
| F06-NFR-005 | Accessibility | semantic forms/states, visible focus, keyboard-compatible controls, RTL, zoom-friendly responsive CSS, reduced motion, forced-colors support | CSS/JS source invariants |
| F06-NFR-006 | Observability | trace IDs, health, safe counts/status evidence, outbox/dead-letter status, File 24 assurance evidence provider | operations/governance/API hooks |
| F06-NFR-007 | Migration/rollback | atomic upgrade lock, idempotent extensions, resumable cursor migration, quarantine, bounded reindex/reconciliation, non-destructive uninstall and rollback docs | governance/uninstall/tests |
| F06-NFR-008 | Operability | v2.2 Operations page, health, safe mode, migration/quarantine visibility, bounded repair/reindex and assurance evidence | `class-he-v22-operations.php` |
| F06-NFR-009 | Compatibility | plugin declares WP >=6.1 / target project WP 7.0.x, PHP >=7.4; CI lints PHP 7.4 and 8.3; contract version 2.2 | bootstrap/workflow |
| F06-NFR-010 | Localization | WordPress i18n calls, Unicode normalization/search, Urdu/Arabic normalization, RTL CSS, user-visible strings translatable | domain/CSS/source invariants |

## Central-plan constraints explicitly preserved

- File 00 remains identity/roles/current-claim authority; File 06 cannot invent a parallel membership truth.
- File 20 remains global shell/layout owner.
- File 25 remains visual-system/design-token owner; File 06 consumes tokens with local fallbacks only.
- File 24 remains cross-cutting assurance owner; native File 06 enforcement remains native.
- File 26 remains global search/discovery owner; File 06 provides a read-only bounded connector.
- File 21 remains feed/post truth; File 06 provides knowledge/correction projections only.
- File 16 receives retrieval/education data only and is explicitly denied clinical authority by File 06.
- The candidate contains no paid/donor feature gate and introduces no donor ranking/support advantage.
- Public knowledge can be read without login; protected mutations require current authentication/authorization.

## Source-level completion decision

**Functional requirements:** 19/19 implemented and traced.  
**Non-functional source controls:** 10/10 implemented and traced.  
**Primary File 06 requirement set:** **29/29 source-implemented = 100% source-level plan coverage**.

This percentage does **not** mean production completion. The following remain separate external evidence gates: real Hostinger staging install/upgrade/migration, real companion-provider versions, browser/device/screen-reader execution, LiteSpeed/cache behavior, backup restore, rollback rehearsal, Founder acceptance, production deployment, live smoke verification and operational monitoring.