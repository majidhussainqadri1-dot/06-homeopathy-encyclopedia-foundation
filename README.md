# File 06 — Homeopathy Encyclopedia 2.4.6

This branch is the seventh fresh ten-round review/fix candidate for the File 06 v1.1 **Future Knowledge Intelligence 18** governing plan. It also applies the later ten-language public-publishing constitution to governed knowledge translations. Repository evidence is not staging or live evidence.

## Runtime candidate

- Branch: `audit/file-06-seventh-ten-round-v2.4.6`
- Plugin version: `2.4.6`
- Global schema: `10`
- V24 Future internal schema: `2`
- Contract: `2.4.6`
- REST namespace: `sabri/v2/file-06`
- PHP: `7.4+`
- Final CI target: WordPress `7.0.1`, PHP `8.3`, MySQL `8`
- Future enhancements: `F06-FUT-001..018`
- Multilingual policy: original/source language + nine governed translations

## Seventh ten-round corrective review

1. Research first-save state now maps only an actual WordPress `publish` state to domain `published`; all other new statuses remain `proposal`.
2. Public freshness GET computes missing freshness without writing to the database.
3. Protected health access is bound to File 00-backed File 06 authorization instead of a raw WordPress capability bypass.
4. File 06 taxonomies no longer expose an uncontrolled core REST mutation surface outside the governed File 06 API.
5. Public successful-case details require public data classification, published/corrected state, anonymization and verified consent; retracted/restricted cases do not render case payloads.
6. Non-public research singular pages fail closed for title, excerpt and robots metadata.
7. Replacement and graph-linked public concept IDs are emitted only for published, approved, safety-approved, unmerged concepts with a current version.
8. Future privacy export/erasure/legal-hold controls were freshly re-reviewed; no new product/source defect was established.
9. Freshness/research-gap maintenance cursors advance only after row work succeeds; failed persistence is propagated instead of silently skipping ahead.
10. Runtime, contract, aggregate QA, version invariants and release documentation were aligned to `2.4.6`.

**Defect rounds:** `1, 2, 3, 4, 5, 6, 7, 9, 10`  
**Clean round:** `8`

## Verification

Run:

```bash
bash tests/run-all.sh
python3 scripts/build-release.py --source homeopathy-encyclopedia --output dist/06-homeopathy-encyclopedia-foundation-2.4.6.zip
```

The final exact package SHA-256, byte count and source-tree SHA-256 are emitted by the final exact-HEAD v2.4.6 workflow. They are deliberately not embedded here because changing this file would change the source-tree digest.

## Release truth

`Specified`, `Coded`, `Packaged`, `Automated-QA Green`, `Staging-Accepted`, `Live-Deployed`, and `Operational` are distinct states. This audit branch is a repository candidate only. Target Hostinger staging upgrade/migration behavior, real companion contracts, browser/RTL/accessibility/cache, backup/restore, rollback rehearsal, Founder staging acceptance and live deployment parity remain external release gates.
