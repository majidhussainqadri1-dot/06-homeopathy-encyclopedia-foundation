# Review/Fix Round 1 — Requirements, Architecture, Security and Privacy

Date: 2026-08-06

## Scope

File 06 v1.0 master requirements, canonical ownership, schema, state machines, REST contracts, cross-file boundaries, authorization, medical safety, privacy lifecycle, migration and deterministic release evidence.

## Defects found and corrected

1. RC1 had no canonical UUID concept identity, aliases, merge lineage or permanent research IDs — implemented schema 7 and owner commands.
2. References were free text — replaced by structured bibliographic/evidence/rights records.
3. Publication versions were not immutable — implemented snapshot hash/version history/diff and effective version.
4. Corrections/retractions lacked a public integrity ledger and consumer events — implemented integrity actions and transparent notices.
5. Relationships were untyped/simple IDs — implemented provenance-bearing typed graph edges and bounded traversal.
6. Research, successful cases and dataset access were absent — implemented ethics/consent/data-class state machines and PII gates.
7. Native WordPress REST/publication could bypass canonical governance — disabled native REST and added direct-publish guard.
8. Mutations lacked uniform safe mode, rate limit and mandatory idempotency — enforced centrally.
9. Merge relation updates could collide with unique constraints — changed to replay/deduplicate/delete reconciliation under transaction.
10. Alias/reference failures during creation could leave a false successful draft — added compensating rollback.
11. Privacy coverage omitted bookmarks — exporter/eraser extended.
12. Local orange design tokens conflicted with current visual law — replaced by shared green-first tokens and icon-based accessible UI.

## Result

All discovered Round-1 defects were corrected and covered by deterministic source invariants. No known blocker remained for the fresh adversarial round.
