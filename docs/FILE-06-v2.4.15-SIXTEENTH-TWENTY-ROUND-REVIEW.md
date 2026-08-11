# File 06 v2.4.15 — Sixteenth Fresh Twenty-Round Review

Repository-only corrective record. It does not establish staging or live deployment.

Defect rounds: 1–20. No clean rounds in this cycle.

1. Public graph UUID edges survive DTO hardening.
2. The lower-level owner hard-delete lifecycle became vetoable/fail-closed with post-delete event ordering; generic WordPress hard delete remains subject to the earlier high-priority governance block.
3. Dataset-access requests accept canonical dataset UUIDs only.
4. Research transition REST uses canonical research UUIDs with object-scoped authorization.
5. Integrity apply REST uses integrity-action UUIDs.
6. Integrity replacement targets reject raw numeric public IDs.
7. Entry/research public pagination uses signed opaque cursors.
8. Autocomplete requires a live published WordPress entry.
9. Direct research first-save projection failure enters safe mode.
10. Existing entry projection and search-index persistence fail closed.
11. Health verifies every required core table and reports schema completeness.
12. Legacy migration paginates beyond 200 entries.
13. Legacy migration does not mark completion after projection failure.
14. Bulk reindex propagates individual failures.
15. Repair clears failure state only after verified schema/index health.
16. New concept taxonomy/alias/published-baseline projection is transactionally verified.
17. Singular WordPress entry rendering/merge redirect binds by authoritative post ID.
18. Legacy unsafe scheduled publisher path is eliminated in favor of the secure schedule owner.
19. Recovery audit found that the repair command itself was blocked by safe mode; repair reservation now remains authenticated/idempotent while permitting governed recovery from safe mode.
20. Final cross-cutting audit found two remaining integration defects: the dataset-access approval route still exposed a raw numeric request ID, and pristine composer rollback still carried stale pre-v2.4.15 deletion-hook wiring that conflicted with the current hard-delete governance/lifecycle stack. Dataset approval now uses a signed opaque request token, while pristine rollback narrowly suppresses both the high-priority hard-delete block and lower-level archive/retraction hooks during its compensation transaction and restores them afterward. Runtime, contract, current QA, SBOM/manifest and repository documentation remain aligned to 2.4.15.

Schema remains 10; Future schema remains 2. Staging, live and operational status remain unverified.
