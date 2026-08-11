# File 06 — Homeopathy Encyclopedia 2.4.5

This branch is the sixth fresh ten-round review/fix candidate for the File 06 v1.1 **Future Knowledge Intelligence 18** governing plan. It also applies the later ten-language public-publishing constitution to governed knowledge translations. Repository evidence is not staging or live evidence.

## Canonical ownership

File 06 owns permanent canonical knowledge concepts/IDs, the fixed sixteen-type taxonomy, source-language identity, aliases/transliterations, structured references/evidence, immutable versions, reviews, corrections/retractions, merge lineage, typed knowledge graph edges, research records, successful-case governance, dataset-access metadata and governed knowledge translation records. File 00 remains identity/current-claim authority; File 19 owns notification delivery; File 20 owns shell/layout; File 24 owns assurance; File 25 owns visual rendering; File 26 owns global search/ranking.

## Runtime candidate

- Branch: `audit/file-06-sixth-ten-round-v2.4.5`
- Plugin version: `2.4.5`
- Global schema: `10`
- V24 Future internal schema: `2`
- Contract: `2.4.5`
- REST namespace: `sabri/v2/file-06`
- PHP: `7.4+`
- Target CI baseline: WordPress `7.0.1`, PHP `8.3`, MySQL `8`
- Future enhancements: `F06-FUT-001..018`
- Multilingual policy: original/source language + nine governed translations

## Sixth ten-round corrective review

1. Added canonical UUID public routes for claims, graph, time-machine, freshness and citations while legacy numeric public reads remain blocked.
2. Canonicalized idempotency fingerprints and added compare-and-swap recovery for objectively stale unfinished reservations.
3. Serialized Future maintenance in the V24 owning layer.
4. Made core-maintenance stale takeover and release compare-and-delete safe.
5. Made reviewer-assignment privacy erasure progress by immutable post-ID cursor so unrelated rows cannot stall a user erasure.
6. Removed redundant V241 Future-maintenance ownership; V24 is now the sole Future maintenance worker/lease owner.
7. Normalized explicit watchlist `active` values through WordPress boolean sanitization.
8. Re-reviewed multilingual/source-language/translation governance; no new product/source defect was established.
9. Made retraction scanning retry-safe so a transient provider failure cannot be skipped by advancing the cursor to the end of the batch.
10. Aligned runtime/contract/readme/current-version invariants, inherited regression semantics and aggregate QA to `2.4.5`, including mandatory execution of the sixth-cycle regression suite.

Defect rounds: `1, 2, 3, 4, 5, 6, 7, 9, 10`. Clean round: `8`.

## Verification

Run:

```bash
bash tests/run-all.sh
python3 scripts/build-release.py --source homeopathy-encyclopedia --output dist/06-homeopathy-encyclopedia-foundation-2.4.5.zip
```

The final exact package SHA-256, byte count and source-tree SHA-256 are emitted by the final exact-HEAD v2.4.5 workflow. They are deliberately not embedded here as self-referential current hashes.

## Release truth

`Specified`, `Coded`, `Packaged`, `Automated-QA Green`, `Staging-Accepted`, `Live-Deployed`, and `Operational` are distinct states. This audit branch is not a staging or live deployment claim. Target Hostinger/WordPress upgrade behavior, real companion contracts, browser/RTL/accessibility/cache, backup/restore, rollback rehearsal, Founder staging acceptance and live deployment parity remain external release gates.
