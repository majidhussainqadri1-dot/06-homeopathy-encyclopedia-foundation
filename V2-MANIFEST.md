# File 06 v2.4.2 Release Manifest

## Release identity

- Runtime version: `2.4.2`
- Schema: `10`
- Contract: `2.4.2`
- Package top-level folder: `06-homeopathy-encyclopedia-foundation/`
- Canonical plugin folder: `homeopathy-encyclopedia/`
- REST namespace: `sabri/v2/file-06`
- Future requirement set: `F06-FUT-001..018`
- Third fresh review matrix: `80/80` source/control themes PASS
- Two post-final-code fresh reviews: PASS / PASS

## Reproducible package evidence

Automated run `31454206508` built the canonical package twice and compared the byte streams successfully.

- ZIP file: `06-homeopathy-encyclopedia-foundation-2.4.2.zip`
- ZIP bytes: `183423`
- ZIP SHA-256: `b031e5bfec3130713fe812cf14614a83c43d35ed92c130f02e98b0c98fd7975a`
- Source-tree SHA-256: `4e36b9f8ecd6346861b17f44b5eded0fa0d2210bbb16178030d8ff111100829a`

## Runtime QA evidence

Run `31454206508`:

- PHP 7.4 syntax: PASS
- PHP 8.3 syntax: PASS
- core/current invariants: PASS
- first 80-round regression invariants: PASS
- Future-18 invariants: PASS
- second 80-round regression invariants: PASS
- third fresh 80-round corrected matrix: PASS
- deterministic package double-build: PASS
- WordPress 7.0.1 / PHP 8.3 fresh install: PASS
- plugin activation: PASS
- runtime/schema/contract check: PASS
- deactivate/reactivate lifecycle: PASS
- schema option persistence check: PASS

## Required third-cycle hardening modules in the package

- `includes/class-he-v242-third-audit.php`
- `includes/class-he-v242-runtime-corrections.php`
- `includes/class-he-v242-multilingual.php`
- `includes/class-he-v242-research-browse.php`
- `includes/class-he-v242-research-authoring.php`
- `includes/class-he-v242-language-surfaces.php`
- `includes/class-he-v242-translation-compat.php`
- `includes/class-he-v242-watchlist.php`
- `includes/class-he-v242-reference-graph.php`
- `includes/class-he-v242-research-immutability.php`
- `includes/class-he-v242-public-translation-guard.php`
- `includes/class-he-v242-language-migration.php`

The deterministic-package job verifies these modules are referenced by the packaged bootstrap and that runtime/schema/contract are exactly `2.4.2 / 10 / 2.4.2`.

## Defect register

Defects or repository/QA discrepancies were found and corrected in rounds:

`4, 5, 7, 11, 17, 18, 19, 20, 21, 22, 28, 29, 30, 31, 32, 38, 39, 58, 61, 72, 73, 74, 75, 78`

The remaining 56 rounds were clean. Rounds 79 and 80 are the two separate fresh post-final-code reviews and both found no new source defect.

## Release status

This manifest is repository/package/runtime-CI evidence only. `Staging-Accepted`, `Live-Deployed`, and `Operational` remain false/unverified until the target environment gates are completed.
