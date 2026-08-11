# File 06 v2.4.1 Release Manifest

## Release identity

- Runtime version: `2.4.1`
- Schema: `10`
- Contract: `2.4.1`
- Package top-level folder: `06-homeopathy-encyclopedia-foundation/`
- Canonical plugin folder: `homeopathy-encyclopedia/`
- REST namespace: `sabri/v2/file-06`
- Future requirement set: `F06-FUT-001..018`

## Reproducible package evidence

Automated run `31449216962` built the canonical package twice and compared the byte streams successfully.

- ZIP file: `06-homeopathy-encyclopedia-foundation-2.4.1.zip`
- ZIP bytes: `147923`
- ZIP SHA-256: `f54719fac6f3f973850848ab449a3ab8f715f463ffe4121b78d5e97305ce7956`
- Source-tree SHA-256: `4d4324ddbfbfefb6f2196b85603768b7676604b1ace41a8c0946ba5e99dcfcf3`

## Required hardening modules in the package

- `includes/class-he-v24-future-schema.php`
- `includes/class-he-v24-migration-safety.php`
- `includes/class-he-v24-future-api.php`
- `includes/class-he-v24-future-privacy.php`
- `includes/class-he-v24-future-review-guard.php`
- `includes/class-he-v24-public-provenance.php`
- `includes/class-he-v241-governance.php`
- `includes/class-he-v241-governance-privacy.php`
- `includes/class-he-v241-research-governance.php`
- `includes/class-he-v241-runtime-guard.php`
- `includes/class-he-v241-before-callback-normalizer.php`
- `includes/class-he-v241-public-dto-guard.php`

The deterministic-package job verifies that these modules are referenced by the packaged bootstrap and that runtime/schema/contract are exactly `2.4.1 / 10 / 2.4.1`.

## Release status

This manifest is repository/package evidence only. `Staging-Accepted`, `Live-Deployed`, and `Operational` remain false/unverified until the target environment gates are completed.
