# File 06 v2.4.16 — Seventeenth Fresh Twenty-Round Review

Repository-only corrective record. Staging/live/operational status is not established by this review.

1. **DEFECT** — Legacy v2.2 research review/integrity/apply REST contracts exposed raw numeric database IDs. They now use canonical research/integrity UUIDs, canonical replacement IDs and object-scoped apply authorization.
2. **DEFECT** — The research-create after-callback rewrote conflicts and incremented row_version after the idempotent response had been finalized. It is now verification-only, eliminating post-success replay/state drift.
3. **DEFECT** — Core readiness treated table existence as schema completeness and skipped repair when the version option was current. Required column-shape verification now participates in health/upgrade readiness.
