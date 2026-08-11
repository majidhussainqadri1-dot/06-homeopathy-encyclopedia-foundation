# File 06 Staging Acceptance

## Current status

**Not yet executed.** This document defines the evidence required before File 06 may leave Draft status.

## Mandatory authenticated staging gates

1. Activation preflight with Files 00, 01, corrected File 05, and File 20 active.
2. Safe refusal and rollback when any mandatory dependency is absent or incompatible.
3. Schema migration, managed-page ownership, starter-draft creation, and repeat activation idempotency.
4. Founder, verified doctor, reviewer, safety reviewer, administrator, suspended doctor, patient, student, and guest permissions.
5. Submission validation, media rollback, moderation state transitions, concurrency protection, reviewer separation, and audit evidence.
6. Search index, A–Z filtering, structured-field search, exact body-system taxonomy, pagination, popular/latest ordering, and empty states.
7. Bookmark hydration, Saved Knowledge cache isolation, atomic metrics, corrections/reports resolution, and rate limiting.
8. Privacy export and erasure across bookmarks, feedback, entries in every status, comments, reviewer metadata, and retained audit records.
9. File 20 shell composition without duplicate navigation or nested main landmarks.
10. Mobile, tablet, desktop, keyboard, screen-reader, reduced-motion, caching, multisite, rollback, and guarded uninstall behavior.

No merge, production deployment, or live-site change is authorized until every gate is evidenced and accepted.
