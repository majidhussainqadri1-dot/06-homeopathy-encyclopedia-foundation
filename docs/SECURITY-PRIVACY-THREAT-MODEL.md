# Security, Privacy and Threat Model

## Assets

Canonical knowledge, source/evidence integrity, published version history, research ethics and consent facts, restricted dataset grants, capabilities, event/audit history and public/private separation.

## Principal threats and implemented controls

| Threat | Control |
|---|---|
| IDOR/BOLA or forged role | File 00-aware membership check plus native capability, object, author, state and purpose authorization |
| CSRF/replay/duplicate click | REST nonce, mandatory idempotency key, request hash and stored replay response |
| Concurrent lost update | Row-version precondition and conditional update |
| Source-free medical claim | Reference, red-flag, safety, limitations, emergency-boundary and review publication gates |
| Self-review/conflict | Author cannot independently approve own entry; conflict declarations block approval |
| Alias collision/duplicate concepts | Unique normalized alias/language, candidate similarity inspection and authorized merge |
| Historical citation breakage | Immutable versions, merge lineage, alias redirects and supersession notices |
| Private/retracted cache/index leak | Explicit public DTO, state/review/safety checks, derivative index removal/rebuild and noindex/no-cache paths |
| Successful-case PII | Consent and anonymization gates plus direct-identifier scanner and required limitations/adverse-event fields |
| Dataset exposure | Public metadata separated from purpose-bound, expiring, audited access grants |
| Queue/provider failure | Reliable outbox, bounded exponential retry, dead-letter and health/repair surfaces |
| Unsafe emergency repair | Read-first dry run, scoped repair, capability guard, audit event and safe mode |
| Sensitive diagnostics | Stable safe messages, trace IDs and redacted health output |

## Privacy lifecycle

Data is classified public, internal, restricted or highly restricted. Public DTOs are explicit allowlists. User bookmarks and dataset requests are exported and erased. Published institutional knowledge and integrity decisions may be retained in de-identified form to preserve citations and public correction history. Draft/private research is noindex/no-cache. Dataset grants expire and are purpose-limited.
