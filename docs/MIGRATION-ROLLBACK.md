# Migration and Rollback

## Supported source

The upgrader recognizes legacy `he_entry` posts and maps them idempotently to schema-7 concepts. It does not invent review evidence. Legacy published content remains non-public in the v2 query contract until references, review and safety gates are satisfied.

## Preflight

1. Record exact source/target versions, row counts, media counts and privacy classes.
2. Create database and files backup and prove restore in isolation.
3. Run staging fresh installation and upgrade from 1.0.0.
4. Keep a cutover freeze while assigning UUIDs, aliases and redirects.

## Migration properties

- Activation lock prevents concurrent schema migration.
- `dbDelta` migrations are additive/idempotent.
- Legacy mapping is checkpointed by option and canonical `post_id` uniqueness.
- Search is derivative and fully rebuildable.
- Errors are retained as a runtime failure instead of silently claiming success.

## Rollback

1. Enter File 06 safe mode.
2. Preserve all post-migration new records and event/outbox state.
3. Restore database/files snapshot or deploy previous package according to the tested compatibility window.
4. Purge caches, restore routes and reindex the selected owner version.
5. Execute guest, Founder, editor, reviewer and research smoke journeys.
6. Record recovery time, counts/checksums and the final decision.

Destructive uninstall is prohibited by default.
