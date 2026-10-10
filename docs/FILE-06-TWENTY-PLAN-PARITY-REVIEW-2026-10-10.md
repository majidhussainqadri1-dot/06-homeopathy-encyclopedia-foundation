# File 06 — Twenty-Part Plan-to-Code and Companion Parity Review
Date: 2026-10-10 (PKT)
Repository: `majidhussainqadri1-dot/06-homeopathy-encyclopedia-foundation`
Baseline source: `f1bf8555071fd1e9481218c80f7e9294a05932ec` (original v2.4.20 development branch)
Corrective branch: `audit/file-06-v2420-plan-parity-20-review-fix`

## Governing comparison
- File 06 Homeopathy Encyclopedia Master Plan **v1.1**, including **19 F06-FR**, **10 F06-NFR** and **18 F06-FUT** requirements.
- Three Central Plans Consolidated Governing Master Plan, including the central File 06 addendum, related 69 catalog requirements, F06-CEN-01/02 and relevant acceptance journeys.
- Consumer/owner boundaries from companion Files 00, 05, 12, 15, 16, 17, 19, 20, 21, 22, 23, 24, 25 and 26.

## Evidentiary distinction
The 20 checks below are **20 separately identified static review focal areas**, now enforced through `tests/v2420-governing-plan-parity.php`. A static indicator or green automated test is **not** proof of complete semantic plan fulfillment, actual WordPress interactions, a deployed schema, or the correctness of remote companion implementations. The subsequent CI also executes the full inherited regression suite, syntax checks and reproducible-build checks.

| Round | Subject and boundary | Source-level result | Interpretation |
|---|---|---|---|
| 01 | 16 governed types and canonical taxonomy | STATIC-PASS | Presence/source indicators corroborated by code; full business behavior still needs runtime acceptance. |
| 02 | Type-specific fields and validation | STATIC-PASS | Presence/source indicators corroborated by code; full business behavior still needs runtime acceptance. |
| 03 | Stable canonical IDs, atomic aliases and redirects | STATIC-PASS | Presence/source indicators corroborated by code; full business behavior still needs runtime acceptance. |
| 04 | A–Z, cursor search, autocomplete, spelling recovery and publication filtering | STATIC-PASS | Presence/source indicators corroborated by code; full business behavior still needs runtime acceptance. |
| 05 | Bibliographic references, evidence/rights/version and graph provenance | STATIC-PASS | Presence/source indicators corroborated by code; full business behavior still needs runtime acceptance. |
| 06 | Red flags, clinical-safety limitations and review | STATIC-PASS | Presence/source indicators corroborated by code; full business behavior still needs runtime acceptance. |
| 07 | Authoring, editor/reviewer assignments and publish permissions | STATIC-PASS | Presence/source indicators corroborated by code; full business behavior still needs runtime acceptance. |
| 08 | Immutable versions and published research snapshots | STATIC-PASS | Presence/source indicators corroborated by code; full business behavior still needs runtime acceptance. |
| 09 | Correction, retraction, notices and accepted-only apply | STATIC-PASS | Presence/source indicators corroborated by code; full business behavior still needs runtime acceptance. |
| 10 | Duplicate candidates, merge policy and relationship reconciliation | STATIC-PASS | Presence/source indicators corroborated by code; full business behavior still needs runtime acceptance. |
| 11 | Research ethics/protocols/conflicts and reviewer ownership | STATIC-PASS | Presence/source indicators corroborated by code; full business behavior still needs runtime acceptance. |
| 12 | Successful-case consent, anonymization and adverse events | STATIC-PASS | Presence/source indicators corroborated by code; full business behavior still needs runtime acceptance. |
| 13 | Dataset purpose/access/de-identification and private-by-default | STATIC-PASS | Presence/source indicators corroborated by code; full business behavior still needs runtime acceptance. |
| 14 | 18 Future Knowledge Intelligence requirements and provenance | STATIC-PASS | Presence/source indicators corroborated by code; full business behavior still needs runtime acceptance. |
| 15 | Read-only PubMed/ORCID/DataCite/MeSH metadata boundaries | STATIC-PASS | Presence/source indicators corroborated by code; full business behavior still needs runtime acceptance. |
| 16 | Ten-language governance, published translations, legacy language migration | STATIC-PASS | Presence/source indicators corroborated by code; full business behavior still needs runtime acceptance. |
| 17 | File 00 current claims and native object authorization | STATIC-PASS | Presence/source indicators corroborated by code; full business behavior still needs runtime acceptance. |
| 18 | Read-only consumer contracts: Files 05/12/15/16/21/26 | STATIC-PASS | Presence/source indicators corroborated by code; full business behavior still needs runtime acceptance. |
| 19 | Companion boundaries: Files 19/20/22/23/24/25/26 | STATIC-PASS | Presence/source indicators corroborated by code; full business behavior still needs runtime acceptance. |
| 20 | Version/contract/schema/release labels, legacy tests, package and QA | STATIC-PASS | Presence/source indicators corroborated by code; full business behavior still needs runtime acceptance. |

## Defects found during the audit and corrected after the source review

1. **Release blocker (QA):** `tests/v2-source-invariants.sh` excluded runtime/contract `2.4.20`, so the first full regression gate failed before later tests ran. The historical acceptable candidate list was extended precisely to `2.4.20`, retaining all substantive safety predicates.
2. **Historical regression compatibility (QA):** tests for prior `v2.4.11–v2.4.17` reviews still explicitly rejected `2.4.20`, even though their security and research invariants remained applicable. Their version acceptance was extended without removing any substantive assertions.
3. **Historical release evidence (QA):** `tests/v2419-twentieth-twenty-round-regressions.php` required the **current** STATUS/README/manifest/SBOM to assert `2.4.19`, contrary to the actual current `2.4.20`. Corrected by preserving original `v2.4.19` defect/clean evidence in its historical ledger while checking current release metadata against the active candidate. Original source-safety predicates remain.
4. **Documentation/contract drift:** `docs/CONTRACTS.md` declared contract `2.0` and omitted subsequent Future-18 commands, events, and companion contract boundaries. Aligned the current registry with the `2.4.20` candidate and runtime `he_contract_descriptor()` as the authoritative dynamic descriptor.
5. **Traceability automation gap:** There was no single explicit 20-area File 06 + cross-file source parity test in the aggregate suite. Added `tests/v2420-governing-plan-parity.php` and attached it to `tests/run-all.sh`; the dedicated CI workflow executes exact-HEAD syntax, full inherited regression and deterministic-package verification.

## Scope still not proven by the repository

- The central-plan **69 relevant catalogue requirements** require complete requirement-by-requirement semantic traceability to actual call paths and negative-path runtime tests. Existing source-indicator checks do not independently certify all 69.
- The 18 Future enhancements have source indications and regression tests, but actual PubMed/NCBI/Crossref/ORCID/DataCite/MeSH provider responses, timeouts, quotas, credentials and rights require isolated integration evidence.
- Native application contracts must be exercised **with real deployed File 00/05/12/15/16/17/19/20/21/22/23/24/25/26 versions**; descriptor declarations alone do not certify parity.
- Source/runtime separation: repository `main` was README-only on inspection. Unmerged candidate code must not be called deployed or merged.
- Deployed plugin binary/package checksum, active configuration, `wp_options`, database schema/tables/rows, migration state, error logs and production behavior are not verified.
- Hostinger-equivalent WordPress install/upgrade and schema migration; real-role workflows; mobile/desktop/tablet, Urdu/Arabic/English, 200% zoom, screen reader, low-bandwidth, privacy erasure, load/performance, backup/restore and rollback rehearsal are all staging acceptance gates.
- Founder approval and post-deployment live verification are still outstanding.

## Decision

The reviewed source has no **proven missing implementation** in the 20 tested source-indicator areas after the corrections above. This is **not** a blanket claim that no source feature remains unfinished. The CI check on the exact final HEAD is the authoritative automated-QA result; a prior green HEAD does not automatically certify later documentation/workflow changes.

**No merge, staging-accepted, live-deployed, operational or resolved status is claimed.** The corrective PR remains Draft until source parity/CI, staging, backup/restore, rollback, Founder acceptance, live deployment and re-test are evidenced independently.

## Mandatory live incident/status fields
- Repository main HEAD at initial inspection: `c6f44e923f2a6f2ed82d967d18422624169b2770` (README-only).
- Candidate source baseline: `f1bf8555071fd1e9481218c80f7e9294a05932ec`.
- Runtime *source target*: `2.4.20`.
- Core schema *source target*: `10`; Future schema target `2`.
- Deployed Version: **UNVERIFIED**.
- DB Version: **UNVERIFIED**.
- Migration State: **UNVERIFIED**.
- Live Verification Status: **UNVERIFIED**.
