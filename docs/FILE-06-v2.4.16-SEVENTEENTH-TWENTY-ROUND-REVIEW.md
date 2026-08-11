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
