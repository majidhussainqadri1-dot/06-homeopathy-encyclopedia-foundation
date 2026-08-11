# File 06 — Homeopathy Encyclopedia 2.4.8

Ninth fresh ten-round review/fix repository candidate for the File 06 governing plan. Repository evidence is not staging or live evidence.

## Candidate truth
- Branch: `audit/file-06-ninth-ten-round-v2.4.8`
- Plugin / contract: `2.4.8`
- Global schema: `10`
- V24 Future schema: `2`
- REST namespace: `sabri/v2/file-06`
- Defect rounds: `1, 2, 3, 4, 5, 6, 7, 8, 9, 10`

Corrections cover fail-closed rate limiting, idempotency fencing, outbox concurrency/reconciliation, atomic event+outbox pair persistence, retry-safe reindex cursors, File00-backed maintenance authority, consumed-event race safety, public research query eligibility, and v2.4.8 release/QA truth.

Run `bash tests/run-all.sh`. Final package/source hashes are authoritative only from the final exact-HEAD workflow. `Staging-Accepted`, `Live-Deployed`, and `Operational` remain unverified until target-environment evidence exists.
