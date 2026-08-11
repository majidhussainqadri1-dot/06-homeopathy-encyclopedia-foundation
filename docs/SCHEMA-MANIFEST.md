# Schema Manifest — version 7

| Table suffix | Canonical purpose |
|---|---|
| `concepts` | Stable UUID concept identity, state, owner post, merge lineage and row version |
| `aliases` | Language-aware primary names, synonyms, transliterations, former names and redirects |
| `versions` | Immutable published snapshots, hashes, change reasons and effective times |
| `references` | Structured bibliographic/evidence/rights/link-integrity facts |
| `relations` | Typed owner-sourced knowledge graph edges with row version |
| `reviews` | Scientific, clinical, source, language, Sharīʿah and privacy review decisions |
| `integrity_actions` | Correction/retraction/replacement/appeal workflow |
| `research` | Proposals, protocols, publications, successful cases and dataset metadata |
| `dataset_access` | Purpose-bound, lawful-basis, expiring access grants |
| `events` | Append-only local event/audit facts |
| `outbox` | Reliable event delivery, retry and dead-letter state |
| `idempotency` | Mutation replay protection and stored response |
| `bookmarks` | Account-owned saved canonical concepts |
| `rate_limits` | Atomic fixed-window abuse throttling |
| `search_index` | Rebuildable public derivative index and facets |

All mutable canonical rows carry state/version and timestamps. Public IDs are opaque UUIDs. Internal database IDs are not public identity. Uninstall is non-destructive unless both explicit purge gates are enabled.
