# File 06 — Homeopathy Encyclopedia 2.4.1

This branch is the second fresh 80-round review/fix candidate for the File 06 v1.1 **Future Knowledge Intelligence 18** governing plan. Repository evidence is not staging or live evidence.

## Canonical ownership

File 06 owns permanent canonical knowledge concepts/IDs, the fixed sixteen-type taxonomy, aliases/transliterations, references/evidence, immutable versions, reviews, corrections/retractions, merge lineage, typed knowledge graph edges, research records, successful-case governance and dataset-access metadata. File 00 remains identity/current-claim authority; File 19 owns notification delivery; File 20 owns shell/layout; File 24 owns assurance; File 25 owns visual rendering; File 26 owns global search/ranking. Files 05/12/15/16/21/26 consume File 06 contracts without copying canonical truth.

## Runtime candidate

- Plugin folder: `homeopathy-encyclopedia`
- Plugin version: `2.4.1`
- Schema: `10`
- Contract: `2.4.1`
- REST namespace: `sabri/v2/file-06`
- PHP: `7.4+`
- Target revalidation baseline: WordPress `7.0.1`, PHP `8.3`
- Future enhancements: `F06-FUT-001..018`

## Second-80 hardening

- native object/type scope on protected writes in addition to File 00 claims;
- explicit File 06 editor-type and entry/research reviewer assignments;
- conflict/self-review separation preserved;
- admin and universal-composer scope bypasses closed;
- research integrity requires explicit `accepted` state before apply;
- core and Future maintenance workers serialized;
- insecure legacy due-publication fallback disabled in favor of fingerprint-revalidated scheduling;
- bounded/resumable Future preflight and postflight migration with fail-closed readiness;
- canonical public-ID DTO policy extended to core endpoints;
- File 06 local governance metadata added to privacy export/erasure/legal-hold lifecycle;
- deterministic package and PHP 7.4/8.3 CI gates maintained.

## Verification

Run:

```bash
bash tests/run-all.sh
python3 scripts/build-release.py --source homeopathy-encyclopedia --output dist/06-homeopathy-encyclopedia-foundation-2.4.1.zip
```

The GitHub workflow performs PHP 7.4/8.3 lint, core/first-80/Future-18/second-80 invariants, JavaScript/Python syntax checks, and two deterministic package builds. Exact package/source hashes are recorded in `STATUS.md` and `V2-MANIFEST.md` after the final exact-head run.

## Release truth

`Specified`, `Coded`, `Packaged`, `Automated-QA Green`, `Staging-Accepted`, `Live-Deployed`, and `Operational` are distinct states. This audit branch/PR is not a staging or live deployment claim. Target Hostinger/WordPress migration, real companion contracts, browser/RTL/accessibility/cache, backup/restore, rollback rehearsal and Founder acceptance remain external release gates.
