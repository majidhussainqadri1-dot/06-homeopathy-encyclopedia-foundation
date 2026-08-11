# File 06 v2.4.16 — Seventeenth Fresh Twenty-Round Review

Repository-only corrective record. Staging/live/operational status is not established by this review.

1. **DEFECT** — Legacy v2.2 research review/integrity/apply REST contracts exposed raw numeric database IDs. They now use canonical research/integrity UUIDs, canonical replacement IDs and object-scoped apply authorization.
2. **DEFECT** — The research-create after-callback rewrote conflicts and incremented row_version after the idempotent response had been finalized. It is now verification-only, eliminating post-success replay/state drift.
3. **DEFECT** — Core readiness treated table existence as schema completeness and skipped repair when the version option was current. Required column-shape verification now participates in health/upgrade readiness.
4. **DEFECT** — The admin safe-mode toggle could clear protection without verified schema/index repair. Disabling safe mode now requires successful bounded repair and a healthy schema check.
5. **DEFECT** — Entry wp-admin editing displayed an expected row version but did not enforce or advance it. A stale-form preflight and CAS row-version/review invalidation now fence content/meta changes.
6. **DEFECT** — The legacy research admin writer used a freshly re-read row version, leaving a race after stale-form preflight. Its CAS now binds to the version loaded into the editor form.
7. **DEFECT** — Create compensation committed canonical-row deletion before WordPress object deletion, allowing a split orphan state. Entry/research compensation now performs both sides in one transaction with lifecycle guards narrowly suppressed and restored.
8. **DEFECT** — An existing same-concept synonym caused add_alias() to return success without promoting/updating a requested canonical primary alias. Same-concept canonical promotion is now persisted and other primary flags are cleared.
9. **DEFECT** — Public search paginated canonical/index rows without authoritative WordPress publish-state filtering, causing stale/short pages. The query now joins the live published entry post state before pagination.
10. **DEFECT** — The default/public research DTO could expose successful-case payloads for restricted records and returned entire dataset metadata blobs. Public output is now data-class-aware with an explicit dataset metadata allowlist.
11. **DEFECT** — Review validation and review submission dereferenced a missing authoritative WordPress entry and could fatal instead of fail closed. Both now return explicit unavailable-state errors.
12. **DEFECT** — A domain research row could remain “published” when its authoritative WordPress post was no longer published if an approval review existed. Reconciliation now always leaves published state, preserving peer-review progress where possible.
13. **DEFECT** — Privacy erasure attempted wp_delete_post() on canonical drafts even though File 06 hard-delete governance blocks that path, then claimed removal. Governed drafts are now ownership-de-identified and retained transparently rather than looping on a blocked delete.
14. **DEFECT** — Reference-create responses exposed raw reference row IDs and relation commands accepted those internal IDs as their public contract. The command surface now returns/accepts scope-bound opaque reference tokens.
15. **DEFECT** — The admin repair UI labeled WP_Error repair outcomes as success. It now renders failed verified repair as an error and no longer presents a false recovery signal.
16. **CLEAN** — Fresh authorization/IDOR review of corrected entry/research/dataset mutation routes found no additional repository-level defect after canonical-ID and object-scope corrections.
17. **CLEAN** — Fresh idempotency, rate-limit, event/outbox, retry/dead-letter and transaction-callsite audit found no new actionable repository defect beyond the corrected paths.
18. **CLEAN** — Fresh activation/upgrade/Future-schema/maintenance/deactivation and migration-safety audit found no new actionable repository defect after schema-shape readiness hardening.
19. **DEFECT** — Runtime, contract, stable tag, aggregate QA/package labels and repository release documentation still described v2.4.15 after corrective source changes. Candidate truth is aligned to v2.4.16; DB schema remains 10 and Future schema remains 2.
20. **CLEAN** — Final fresh cross-cutting source review after all corrections found no additional repository-level defect; exact-head final QA remains the release gate.
