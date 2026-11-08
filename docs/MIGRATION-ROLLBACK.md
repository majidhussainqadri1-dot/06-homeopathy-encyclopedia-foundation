# File 06 — Migration, Reconciliation and Rollback (v12 source candidate)

**Governing current source:** File 06 2.4.20, core schema **10**, Future schema **2**; installed/deployed versions and DB schema remain unverified. Historical schema 7 refers only to the old baseline, not the current release target.

## Preflight — Live Reality Freeze
Record live plugin identity and exact files/ZIP checksum, active WordPress/PHP/MySQL versions, File 00/05/12/15/16/19/20/24/25/26 companion builds, `wp_options` versions and locks, canonical row counts, pending outbox/dead-letter, migration quarantine, reference link checks and error logs. GitHub HEAD is not deployed parity.

## Safe upgrade
1. Capture complete DB + wp-content backup and prove a restore in isolated staging.
2. Verify 10 core tables and 12 Future tables including field types/indexes; never assume `update_option` means migration success.
3. Run guarded/idempotent incremental installer/upgrade; preserve post IDs, public UUIDs, aliases, URLs, source versions, provenance and all review decisions.
4. Rebuild derivative search and cache projections only after authoritative status/visibility validation.
5. Inspect `he_v24_future_version`, `he_schema_version`, migration locks, safety options and outbox receipts. A mismatch prevents release.

## v12 specific reconciliation
- Existing research consent JSON that lacks policy/version/locale/purpose must be marked **legacy-unverified** for any *new* use, not converted silently into accepted consent. New verified consents require exact policy version and locale.
- Withdrawn case records must become private/retracted before delivering downstream `ResearchRecordRetracted.v1`; replay and File 26 visibility must be tested.
- Crossref corrections/retractions invalidate only directly evidence-linked, current approved claims, queue review and preserve historical provenance.
- Link-check cursor is derived and may be reset after backup; link-status results never auto-delete knowledge.
- Reimport preview never overwrites canonical File 06 facts. Companion mappings require owner acceptance.

## Rollback
Enter safe mode; preserve events/outbox/consent-withdrawal records; restore the exact verified backup or compatible prior package only after schema compatibility check; clear and rebuild search/caches; retest representative published/retracted/private records; reconcile duplicate/retried consumer events; verify hash/row counts/permissions. If any review, withdrawal or consumer receipt is unresolved, do not classify rollback as complete.

**Acceptance:** two dated corrective reviews, negative paths, real roles, backup/restore rehearsal, Founder decision and live parity retest. Source QA alone cannot authorize deployment.
