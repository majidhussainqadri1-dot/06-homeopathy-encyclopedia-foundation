# File 06 — Homeopathy Encyclopedia 2.0.0

This branch is the complete source candidate for the File 06 master specification.

## Canonical ownership

File 06 owns canonical knowledge concepts and IDs, fixed taxonomy, aliases, structured references, published versions, review and integrity history, typed knowledge relations, research records and dataset-access governance. It does not own social-feed ranking, course progress, PDF storage, repertory data, AI output, authentication, global navigation or visual-system governance.

## Runtime package

- Plugin folder: `homeopathy-encyclopedia`
- Plugin version: `2.0.0`
- Schema: `7`
- REST namespace: `sabri/v2/file-06`
- Public routes: `/encyclopedia/`, `/encyclopedia/entry/{slug}/`, `/research/`
- PHP: 7.4+
- WordPress target: 7.0.x; minimum declared 6.1

## Release evidence

Run:

```bash
bash tests/run-all.sh
python3 scripts/build-release.py --source homeopathy-encyclopedia --output dist/06-homeopathy-encyclopedia-2.0.0.zip
```

The CI workflow repeats linting, invariant checks and two deterministic package builds. Staging and production evidence remain environment-specific and are recorded using `docs/STAGING-ACCEPTANCE.md`.
