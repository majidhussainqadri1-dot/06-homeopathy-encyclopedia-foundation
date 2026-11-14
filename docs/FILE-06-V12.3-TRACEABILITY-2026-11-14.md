# File 06 — V12.3 Source-to-Acceptance Traceability (14 November 2026)

**Source baseline:** `bee1462c0591f7281d3cd8182c6d9563a16dc078` (PR #16, draft) plus a separately gated File26 candidate on this integration branch.
**Default `main`:** `c6f44e923f2a6f2ed82d967d18422624169b2770` (README-only).
**Master authority:** Sabri-Website-Master-Plan-V12.3.docx, File 06 section and its central standards.

**Evidence labels:** `SOURCE_CANDIDATE` means code or a relevant path appears in development. It does **not** assert implemented semantics, external compatibility, end-to-end tests, activated installation, staging, database parity, or live behavior. All 49 acceptance results remain **UNVERIFIED** until individual positive/negative runtime evidence is recorded.

## Requirement inventory: 49 IDs

| Requirement | Scope | Candidate source path (not acceptance proof) | Mandatory distinct acceptance evidence | Status |
|---|---|---|---|---|
| `F06-FR-001` | 16 fixed types | `homeopathy-encyclopedia/includes/class-he-v22-type-schemas.php` | All 16 live type/validator fixtures | UNVERIFIED |
| `F06-FR-002` | Per-type field schemas | `homeopathy-encyclopedia/includes/class-he-v22-type-schemas.php` | Every type required/optional fields and negative mutations | UNVERIFIED |
| `F06-FR-003` | Canonical IDs and aliases | `homeopathy-encyclopedia/includes/class-he-v2-domain.php` | UUID, merge redirect, collision, ambiguous alias | UNVERIFIED |
| `F06-FR-004` | A-Z and facets | `homeopathy-encyclopedia/includes/class-he-v2-domain.php` | Public-only filters and cursor paging | UNVERIFIED |
| `F06-FR-005` | Exact/phrase/Urdu search | `homeopathy-encyclopedia/includes/class-he-v22-search.php` | Owner-visible search and private exclusion | UNVERIFIED |
| `F06-FR-006` | Bibliographic sources | `homeopathy-encyclopedia/includes/class-he-v12-completion.php` | Licensed edition/page, links, exact source origin | UNVERIFIED |
| `F06-FR-007` | Clinical safety | `homeopathy-encyclopedia/includes/class-he-v2-public.php` | Clinical red-flag and non-diagnostic view | UNVERIFIED |
| `F06-FR-008` | Authored review/workflow | `homeopathy-encyclopedia/includes/class-he-v241-governance.php` | Independent reviewer, conflict, schedule | UNVERIFIED |
| `F06-FR-009` | Immutable version history | `homeopathy-encyclopedia/includes/class-he-v2-domain.php` | Snapshot/diff/time-scoped citations | UNVERIFIED |
| `F06-FR-010` | Correction/retraction | `homeopathy-encyclopedia/includes/class-he-v22-integrity.php` | Owner events, notice, withdrawal, dependent consumers | UNVERIFIED |
| `F06-FR-011` | Duplicate/merge | `homeopathy-encyclopedia/includes/class-he-v22-governance.php` | Review-safe merge, redirect, graph rewriting | UNVERIFIED |
| `F06-FR-012` | Typed relationships | `homeopathy-encyclopedia/includes/class-he-v2-domain.php` | Reference-bound graph edge and author permissions | UNVERIFIED |
| `F06-FR-013` | Bookmarks and proposals | `homeopathy-encyclopedia/includes/class-he-v242-watchlist.php` | Private account bookmark and sourced correction | UNVERIFIED |
| `F06-FR-014` | Schema and SEO | `homeopathy-encyclopedia/includes/class-he-v12-sitemap.php` | Canonical sitemap and protected research exclusion | UNVERIFIED |
| `F06-FR-015` | Research proposals | `homeopathy-encyclopedia/includes/class-he-v242-research-authoring.php` | Protocol/ethics/data class/roles | UNVERIFIED |
| `F06-FR-016` | Research lifecycle | `homeopathy-encyclopedia/includes/class-he-v241-research-governance.php` | State transitions, immutable publication, retraction | UNVERIFIED |
| `F06-FR-017` | Case observations/consent | `homeopathy-encyclopedia/includes/class-he-v12-case-consent-owner.php` | Real File00 subject proof, revoke, public erasure | UNVERIFIED |
| `F06-FR-018` | Dataset privacy | `homeopathy-encyclopedia/includes/class-he-v22-research-guard.php` | De-id and access approval; deny by default | UNVERIFIED |
| `F06-FR-019` | Versioned consumer APIs | `homeopathy-encyclopedia/includes/class-he-v22-consumers.php` | Real Files 05/12/15/16/21/26 and click-time ACL | UNVERIFIED |
| `F06-NFR-001` | Object/field authorization | `homeopathy-encyclopedia/includes/class-he-v2-auth.php` | IDOR/expired claim and tenant isolation | UNVERIFIED |
| `F06-NFR-002` | Privacy/retention/consent | `homeopathy-encyclopedia/includes/class-he-v2-privacy.php` | Purpose, withdrawal, export/erase/legal hold | UNVERIFIED |
| `F06-NFR-003` | Outbox/idempotency/retries | `homeopathy-encyclopedia/includes/class-he-v2-integrations.php` | Failure/duplicate/dead-letter/reconciliation | UNVERIFIED |
| `F06-NFR-004` | p75/p95 performance | `homeopathy-encyclopedia/includes/class-he-v22-search.php` | Real device and MariaDB bounded-query measurements | UNVERIFIED |
| `F06-NFR-005` | WCAG 2.2 AA/RTL | `homeopathy-encyclopedia/assets/css/encyclopedia-v2.css` | 320px, zoom, keyboard, readers and RTL | UNVERIFIED |
| `F06-NFR-006` | Observability | `homeopathy-encyclopedia/includes/class-he-v22-operations.php` | Privacy-safe logs/metrics and alert owner | UNVERIFIED |
| `F06-NFR-007` | Migration/rollback | `homeopathy-encyclopedia/includes/class-he-v24-migration-safety.php` | Schema 10/2, install/upgrade/repeat/restore | UNVERIFIED |
| `F06-NFR-008` | Operational health | `homeopathy-encyclopedia/includes/class-he-v22-operations.php` | Health/queue/reindex/safe repair | UNVERIFIED |
| `F06-NFR-009` | WordPress/PHP compatibility | `homeopathy-encyclopedia/homeopathy-encyclopedia.php` | Staging WordPress 7.0.1 and PHP 8.3 | UNVERIFIED |
| `F06-NFR-010` | Localization | `homeopathy-encyclopedia/includes/class-he-v242-multilingual.php` | en-US/ur/ar, mixed-script and dates/numerals | UNVERIFIED |
| `F06-CEN-01` | Canonical owner/search projection | `homeopathy-encyclopedia/includes/class-he-file26-connector.php` | Real File26 connector active only after owner and release approval | UNVERIFIED |
| `F06-CEN-02` | Write command and read/caches reauthorization | `homeopathy-encyclopedia/includes/class-he-v2-integrations.php` | Publish/revoke/delete and consumer visibility replay | UNVERIFIED |
| `F06-FUT-001` | Claim-level evidence | `homeopathy-encyclopedia/includes/class-he-v24-future-api.php` | Per-claim approved source grade, contradiction and review | UNVERIFIED |
| `F06-FUT-002` | Provenance ledger | `homeopathy-encyclopedia/includes/class-he-v24-public-provenance.php` | Immutable identity/transformation/version evidence | UNVERIFIED |
| `F06-FUT-003` | Crossref retraction watch | `homeopathy-encyclopedia/includes/class-he-v12-evidence-impact.php` | Bounded claim-specific update and queue atomicity | UNVERIFIED |
| `F06-FUT-004` | PubMed literature | `homeopathy-encyclopedia/includes/class-he-v23-future.php` | Read-only identifiers/provider outage/human review | UNVERIFIED |
| `F06-FUT-005` | NCT clinical trial linker | `homeopathy-encyclopedia/includes/class-he-v23-future.php` | No automatic treatment efficacy, source context | UNVERIFIED |
| `F06-FUT-006` | ORCID mapping | `homeopathy-encyclopedia/includes/class-he-v23-future.php` | Verified identity not authorization | UNVERIFIED |
| `F06-FUT-007` | DataCite metadata | `homeopathy-encyclopedia/includes/class-he-v23-future.php` | Rights, dataset privacy and importer dry run | UNVERIFIED |
| `F06-FUT-008` | MeSH mapping | `homeopathy-encyclopedia/includes/class-he-v23-future.php` | No canonical taxonomy overwrite | UNVERIFIED |
| `F06-FUT-009` | Semantic duplicate candidates | `homeopathy-encyclopedia/includes/class-he-v23-future.php` | No automatic merges; ambiguous safety | UNVERIFIED |
| `F06-FUT-010` | Graph explorer | `homeopathy-encyclopedia/includes/class-he-v24-future-api.php` | Bounded graph projection, keyboard fallback | UNVERIFIED |
| `F06-FUT-011` | Knowledge time-machine | `homeopathy-encyclopedia/includes/class-he-v24-future-api.php` | Immutable as-of version and provenance | UNVERIFIED |
| `F06-FUT-012` | Cross-platform impact | `homeopathy-encyclopedia/includes/class-he-v12-evidence-impact.php` | 6 consumers replay, absent provider fails closed | UNVERIFIED |
| `F06-FUT-013` | Freshness review | `homeopathy-encyclopedia/includes/class-he-v12-completion.php` | UTC stale calculation, human review vs scan | UNVERIFIED |
| `F06-FUT-014` | Research gap radar | `homeopathy-encyclopedia/includes/class-he-v24-future-api.php` | Explicit unknown/insufficient evidence | UNVERIFIED |
| `F06-FUT-015` | Citation export | `homeopathy-encyclopedia/includes/class-he-v24-future-api.php` | JSON/CSL/JSON-LD/BibTeX/RIS no secrets | UNVERIFIED |
| `F06-FUT-016` | Private watchlists | `homeopathy-encyclopedia/includes/class-he-v242-watchlist.php` | User ACL and notification consent | UNVERIFIED |
| `F06-FUT-017` | Governed translations | `homeopathy-encyclopedia/includes/class-he-v242-multilingual.php` | Version/rights/reviewer/canonical edition | UNVERIFIED |
| `F06-FUT-018` | Integrity command center | `homeopathy-encyclopedia/includes/class-he-v24-future-api.php` | Operator scoped health and safe repair | UNVERIFIED |

## Independent cross-file and governance gates
- **File 00:** actual deployed/current consent receipt with locale, purpose, subject link, withdrawal and authorization; an isolated WordPress mock is not the deployed provider.
- **File 05, 10, 12:** original owner attestations, immutable version/edition, rights and click-time eligibility. Do not invent untranslated excerpts.
- **File 15:** current remedy DTO and durable `KnowledgeConceptMerged.v1` consumption with `review_required`, never automatic clinical evidence transfer.
- **File 26:** candidate connector is `proposed`; no automatic activation, tombstone cutover, or source-version parity assertion. Missing source/invalid safety or rights must fail closed.
- **File 16/19/20/21/22/23/24/25:** read/write and UI boundary parity only after exact installed versions are checked.
- **Central:** CEN-PUB-001, CEN-REL-001, WCAG 2.2 AA, real device p75 / p95, WordPress 7.0.1 / PHP 8.3 and privacy requirements are distinct release blockers.
- The historical central 69-item catalogue requires further applicability/overlap mapping and is not a substitute for the 49 File06 IDs.

## Current repository/CI facts
- PRs #9 -> #11 -> #12 -> #13 -> #14 -> #16 are a chained set of **draft** sources. PR #15 diverges from PR #12 and is NOT merged into #16; this branch stages its connector only as a proposed additive integration.
- PR #16 exact-head `bee1462c0591f7281d3cd8182c6d9563a16dc078` passed the **File 06 20-Part Governing Plan Parity** workflow (run 42747006610). This is source QA only.
- Two other historical workflow runs (42747004798, 42747003441) had zero jobs. Do not classify those status labels as application-code failures without separate logs.
- PR #13 documented 18 code-corrective focus areas (candidate changes), not 18 live-resolved defects. PR #16 adds an exact callback-arity candidate. Live-verified fixes = **0**.
- On 14 November, WordPress.com Jetpack site metadata showed **site_disconnected**; WPVibe had no connected sites and connection to `www.sabrihomeopathy.com` returned HTTP 530. These are observed access blockers, **not** proof of database corruption or site downtime.

## Live-First incident fields
| Field | Evidence status |
|---|---|
| Repository main HEAD | `c6f44e923f2a6f2ed82d967d18422624169b2770` |
| Source candidate HEAD | Obtain from this branch at each run (never reuse historical success) |
| Deployed Version | UNVERIFIED |
| DB Version | UNVERIFIED |
| Migration State | UNVERIFIED |
| Live Verification Status | UNVERIFIED |
| Staging sign-off / backup restoration | UNVERIFIED |
| Founder approval | UNVERIFIED |

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔**

## Gate definitions
- **G0:** Frozen plan and all 49 IDs mapped to a candidate path and required evidence — documented here.
- **G1:** Read-only live freeze of active plugin/runtime, deployed bytes, schema/options/migrations and parity — BLOCKED (site access).
- **G2:** 49/49 semantic source-path tests and 69 historical catalogue applicability — IN PROGRESS; this ledger is the starting inventory only.
- **G3:** PR integration and independent source tests with full exact-HEAD CI — IN PROGRESS, no merge authority.
- **G4–G9:** Real WordPress/MariaDB, provider contracts, safety, 2 reviews, staging, backup/rollback, Founder decision and live retest — NOT COMPLETE.

Only after live re-test may any defect be labeled `Resolved`. Failed/stale/optional external functions remain visibly tracked, even if a separate active scope can be approved.
