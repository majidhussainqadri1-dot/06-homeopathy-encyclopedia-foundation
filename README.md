# File 06 — Homeopathy Encyclopedia 2.4.4

This branch is the fifth fresh ten-round review/fix candidate for the File 06 v1.1 **Future Knowledge Intelligence 18** governing plan. It also applies the later ten-language public-publishing constitution to governed knowledge translations. Repository evidence is not staging or live evidence.

## Canonical ownership

File 06 owns permanent canonical knowledge concepts/IDs, the fixed sixteen-type taxonomy, source-language identity, aliases/transliterations, structured references/evidence, immutable versions, reviews, corrections/retractions, merge lineage, typed knowledge graph edges, research records, successful-case governance, dataset-access metadata and governed knowledge translation records. File 00 remains identity/current-claim authority; File 19 owns notification delivery; File 20 owns shell/layout; File 24 owns assurance; File 25 owns visual rendering; File 26 owns global search/ranking. Localized public URL/SEO/sitemap orchestration remains with the approved cross-file publishing/search owners.

## Runtime candidate

- Plugin folder: `homeopathy-encyclopedia`
- Plugin version: `2.4.4`
- Schema: `10`
- Contract: `2.4.4`
- REST namespace: `sabri/v2/file-06`
- PHP: `7.4+`
- Target revalidation baseline: WordPress `7.0.1`, PHP `8.3`
- Future enhancements: `F06-FUT-001..018`
- Multilingual policy: original/source language + nine governed translations

## Third-80 hardening

- canonical source-language/alias repair, legacy Urdu normalization and ambiguous cross-language aliases fail closed;
- references bind only to a version of the same concept, reference rights use a bounded vocabulary, and knowledge relations require source provenance;
- integrity transition/apply authorization is bound to the governed object before early REST short-circuits; replacement targets are validated;
- merges require authorization on both source and target plus a documented reason;
- research public visibility requires WordPress/domain-state parity; public browse is bounded without invalid-row starvation;
- research authoring includes investigators, explicit conflict disclosure and dataset governance fields with stale-form concurrency protection;
- published/corrected/retracted research is immutable through normal wp-admin editing and must use integrity workflows;
- dataset access/approval is state-bound; research external-evidence decisions require an assigned research reviewer;
- entry/research composer compensation is restricted to pristine governed drafts and is transactional with rollback cache repair;
- direct governed hard-delete is blocked and destructive uninstall includes v2.4.2 migration options/locks;
- watchlists validate concept/topic/research objects, remain private, and delegate notification transport to File 19;
- ten-language translations bind to the current source version, remain human-review gated, expose a canonical public read surface, hide internal source-version IDs, and support bounded `ur-PK` → `ur` migration/compatibility reads;
- PHP 7.4/8.3 lint, deterministic packaging and WordPress 7.0.1/PHP 8.3 fresh-install + activation/deactivation/reactivation CI smoke are enforced.

## Verification

Run:

```bash
bash tests/run-all.sh
python3 scripts/build-release.py --source homeopathy-encyclopedia --output dist/06-homeopathy-encyclopedia-foundation-2.4.4.zip
```

Final reviewed package evidence: **pending the round-10 exact-head reproducible-package gate.**

## Release truth

`Specified`, `Coded`, `Packaged`, `Automated-QA Green`, `Staging-Accepted`, `Live-Deployed`, and `Operational` are distinct states. This audit branch/PR is not a staging or live deployment claim. Target Hostinger/WordPress migration, real companion contracts, browser/RTL/accessibility/cache, backup/restore, rollback rehearsal and Founder staging acceptance remain external release gates.
