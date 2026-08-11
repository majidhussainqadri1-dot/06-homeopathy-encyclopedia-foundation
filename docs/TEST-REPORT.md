# File 06 v2 Automated Test Report

Date: 2026-08-06
Candidate: 2.0.0 / schema 7 / contract 2.0

## Executed locally

- PHP syntax: all plugin and test PHP files passed on PHP 8.4 CLI; CI separately targets PHP 7.4 and 8.3.
- JavaScript syntax: `node --check` passed.
- Architecture/security/source invariants: passed.
- Legacy-class exclusion and secret-pattern checks: passed.
- Green-token, RTL, focus, forced-colors and reduced-motion invariants: passed.
- Requirement/contract tokens for FR-001–019 and NFR-001–010: passed.
- Checksum manifest verification: passed.
- Deterministic build: two independently produced ZIP files were byte-identical.

## Exact package evidence

- File: `06-homeopathy-encyclopedia-2.0.0.zip`
- Bytes: `50,090`
- SHA-256: `36893ae7b4dcb9e8e33ef7e77c62c393e92765e03de2a859d23827449999b165`
- Plugin source-tree SHA-256: `526e64e5794f4259d6a62677b9868ee3fa40dc03911046940686198dbad95721`

## CI matrix

The committed workflow runs PHP 7.4 and 8.3 syntax, source/security invariants, manifest verification, JavaScript syntax and deterministic double-build verification against the SBOM release hash.

## Manual target-environment matrix

The environment-specific test cases are fixed in `docs/STAGING-ACCEPTANCE.md`; they require the actual Hostinger staging instance, real companion versions, users, theme, cache and backup/restore facilities.
