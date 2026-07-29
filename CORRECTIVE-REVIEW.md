# File 06 Corrective Review — v1.0.0 RC1

This corrective candidate addresses the blocker classes recorded against the immutable File 06 v0.1.0 baseline.

## Remediation map

1. **Dedicated capabilities:** `he_entry` uses a complete custom capability map; ordinary post editors receive no automatic access.
2. **Authoritative identity:** Founder and doctor eligibility are resolved through File 00 and corrected File 05 contracts, without role-only or File 03 fallbacks.
3. **Dependency gates:** activation/runtime require Files 00, 01, 05, and 20 and verify the Foundation-owned Encyclopedia page.
4. **Transactional activation:** activation records state, catches failures, rolls back created pages/posts, pauses the plugin, and uses an idempotent schema version.
5. **Managed page ownership:** stored ID, ownership meta, key, and exact shortcode are verified; unrelated slug conflicts are not adopted.
6. **Scoped comments:** no site-wide comment option filter remains; rules apply only to governed public encyclopedia entries.
7. **Unified shell boundary:** File 06 emits module sections and a recognized `sabri_encyclopedia` shortcode; it does not render a parallel platform navigation or `<main>` landmark.
8. **True pagination and A–Z:** catalog filtering occurs in a persistent index before pagination, with exact letter/type/system filters.
9. **Structured search:** title, summary, content, taxonomy labels, and governed metadata are indexed and searchable.
10. **Controlled body systems:** free-text filtering is replaced by a governed body-system taxonomy with legacy migration.
11. **Server validation:** title, summary, content, classifications, references, declarations, and type-specific safety fields are mandatory server-side.
12. **Language governance:** authors declare American English; all text fields receive script checks; publication additionally requires independent editorial review metadata.
13. **File 05 privacy:** connected books and lessons must be publicly available, including File 05 lesson-governance checks.
14. **Image hardening:** MIME, upload status, size, dimensions, pixel count, processing, attachment, and rollback are checked.
15. **Moderation state machine:** allowed transitions, self-review prevention, row-version locking, mandatory notes, error checking, reviewer identity, and audit events are implemented.
16. **Feedback resolution:** corrections/reports have pagination, disposition, resolution note, resolver, resolution time, row version, and audit history.
17. **Atomic/idempotent interactions:** view counters use atomic upsert; bookmark requests set an explicit desired state.
18. **Cache privacy:** public HTML contains no private bookmark state; state hydrates by AJAX; private pages emit no-cache controls.
19. **Privacy coverage:** export/erasure covers bookmarks, feedback, all authored workflow states, comments, and audit events with explicit retention messages.
20. **Guarded uninstall:** destructive removal requires two explicit controls and handles tables, entries, owned pages, taxonomies, capabilities, options, transients, cron, and multisite.

## Automated evidence

- Exact corrected-source checksums.
- Exact source file, PHP file, and byte counts.
- Deterministic source-tree hashing with a documented algorithm.
- PHP syntax on 7.4 and 8.3.
- Deterministic source/security invariants.
- Two byte-identical release builds with a fixed expected SHA-256 and size.

## Remaining acceptance boundary

Automated source checks do not replace real WordPress staging. Activation, migration from v0.1.0, File 00 suspension/revocation, File 05 relationship privacy, File 20 layout, role assignment, moderation concurrency, feedback resolution, cache behavior, accessibility, responsive behavior, rollback, multisite, and guarded uninstall must be tested on authenticated staging before merge or deployment.
