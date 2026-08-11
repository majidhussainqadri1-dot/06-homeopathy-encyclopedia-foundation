# File 06 — Homeopathy Encyclopedia Foundation

This repository governs **File 06** of the **Sabri Social Homeopathy Platform**.

## Immutable baseline

The branch `baseline/file-06-original-import` and Draft PR #1 preserve the exact supplied v0.1.0 source as evidence. The baseline is not a functional or production approval.

## Corrective candidate

The branch `audit/file-06-source-review` contains the v1.0.0 RC1 remediation candidate.

- Corrected source files: `19`
- PHP files: `15`
- Source bytes: `138,665`
- Database schema: `2`
- Source-tree SHA-256: `f336ce4f9996f64455ccae922b34379e3ae053a85f8d34cfde2784ff319f549b`
- Reproducible RC ZIP SHA-256: `14800cf27df796b0972164b0bb75922ce9d851c35f7a7e31a4a95943b1149fb1`
- Reproducible RC ZIP size: `44,284` bytes

## Evidence files

- `SOURCE-PROVENANCE.md`, `MANIFEST.md`, and `CHECKSUMS.sha256` preserve the original baseline identity.
- `CORRECTIVE-REVIEW.md` maps the recorded blockers to their remediation.
- `CORRECTIVE-MANIFEST.md` and `CORRECTIVE-CHECKSUMS.sha256` identify the corrected source.
- `scripts/` provides deterministic source hashing and release packaging.
- `tests/` provides deterministic source and security invariants.
- `.github/workflows/corrective-integrity.yml` verifies the corrective candidate.

## Change-control rule

The corrective Pull Request must remain Draft and unmerged until all automated checks pass and authenticated WordPress staging acceptance confirms activation, migration, dependencies, permissions, moderation, privacy, caching, accessibility, responsive behavior, rollback, multisite, and guarded uninstall. Production deployment is not authorized by source-level CI.
