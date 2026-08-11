# Review/Fix Round 2 — Fresh Adversarial and Failure-Mode Review

Date: 2026-08-06

## Attack/failure perspectives

Unauthorized object guessing, stale row version, repeated requests, duplicate clicks, safe-mode mutation, alias collision, merge edge collision, self-review, source-free publication, retracted/deleted cache exposure, scheduled-publication delay, research PII, dataset overexposure, optional companion outage, queue retry/dead-letter, RTL/zoom/reduced motion and rollback integrity.

## Defects found and corrected

1. Relation and merge API callbacks could evaluate domain writes even when reservation guards failed — short-circuited all guarded writes.
2. Error handling assumed WP_Error data was always an array — normalized safe status extraction.
3. Scheduled state lacked a bounded future timestamp — added validation, persistent schedule fact, due publisher and event.
4. Public historical DTO lacked explicit current-version warning — added historical/current fields and visible banner.
5. Retraction made the canonical citation page disappear — retained a clearly marked retracted page while excluding it from search.
6. Merged canonical source URLs could remain on a restricted page — added singular and 404 alias redirects to the surviving concept.
7. Public source-grade filtering was missing from the REST facet contract — added filter and index use.
8. Bookmark mutation did not propagate a domain error correctly — corrected error-preserving response logic.
9. Research admin workflow did not expose complete successful-case/dataset fields — added baseline, intervention, follow-up, adverse events, limitations and metadata persistence.
10. Activation registered post types after migration — reordered registration before schema migration.
11. Native entry REST remained enabled despite the custom versioned owner API — disabled it to remove a bypass surface.
12. Duplicate similarity used an uninitialized output variable — initialized deterministically.

## Result

All Round-2 findings were corrected, PHP/JS lint and source/security/architecture invariants passed, and the package was prepared for deterministic double-build verification.
