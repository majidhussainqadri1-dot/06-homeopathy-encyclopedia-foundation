# File 06 v2.4.17 — Eighteenth Fresh Twenty-Round Review

Repository-only corrective record. Staging/live/operational status is not established by this review.

1. **DEFECT** — The v2.4.16 relation command had moved to opaque reference tokens, but the earlier graph guard still absint-cast the token and rejected every valid request. The guard now decodes only the scope-bound reference token and rejects raw numeric identifiers.
2. **DEFECT** — The canonical public-translation route accepted a known concept UUID without enforcing approved/safe/published domain state or authoritative WordPress publish state. Public translations now require both governed eligibility and a published File 06 WordPress object.
3. **DEFECT** — The older Third-Audit meta hook mutated canonical concept language before the newer CAS/invalidation owner ran, allowing the stronger language-governance path to be bypassed. The stale pre-emptive meta hooks were removed; Language Surfaces is now the sole meta-to-domain synchronization owner.
