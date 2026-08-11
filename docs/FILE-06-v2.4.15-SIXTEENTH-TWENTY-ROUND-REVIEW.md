# File 06 v2.4.15 — Sixteenth Fresh Twenty-Round Review

Repository-only corrective record. It does not establish staging or live deployment.

Defect rounds: 1–20. No clean rounds in this cycle.

1. Public graph UUID edges survive DTO hardening.
2. WordPress hard deletes are vetoable on canonical lifecycle failure and events follow confirmed deletion.
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
20. Final cross-cutting audit found a remaining raw numeric dataset-access approval route; it was replaced by a signed opaque request token, then runtime, contract, current QA, SBOM/manifest and repository documentation were aligned to 2.4.15.

Schema remains 10; Future schema remains 2. Staging, live and operational status remain unverified.
