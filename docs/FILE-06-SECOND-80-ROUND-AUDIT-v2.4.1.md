# File 06 — Second Fresh 80-Round Review/Fix Report — v2.4.1

## Governing basis

- File 06 plan: v1.1 — Future Knowledge Intelligence 18
- Requirement set: `F06-FR-001..019`, `F06-NFR-001..010`, `F06-FUT-001..018`
- Audit branch: `audit/file-06-second-80-round-v2.4.1`
- Reviewed source/package head: `a317c034ac76e87504c7b61d01021046abe66e77`
- Runtime / schema / contract: `2.4.1 / 10 / 2.4.1`
- Exact-head automated run: `31449216962`
- Deterministic ZIP SHA-256: `f54719fac6f3f973850848ab449a3ab8f715f463ffe4121b78d5e97305ce7956`
- ZIP bytes: `147923`
- Source-tree SHA-256: `4d4324ddbfbfefb6f2196b85603768b7676604b1ace41a8c0946ba5e99dcfcf3`

This is repository/source/automated-QA evidence only. It is not Hostinger staging or live-deployment evidence.

## 80 rounds

| Round | Review focus | Result |
|---:|---|---|
| 1 | Plugin/repository version identity | **DEFECT** — plugin readme/release identity was stale; aligned to 2.4.1. |
| 2 | Status/manifest/SBOM/checksum metadata | **DEFECT** — release metadata still described old 2.0/2.3 candidates; replaced with current evidence. |
| 3 | CI branch/version/package assertions | **DEFECT** — workflow was not the complete v2.4.1 second-80 gate; updated. |
| 4 | Local aggregate test runner | **DEFECT** — stale self-referential manifest verification and incomplete suite coverage; corrected. |
| 5 | Core invariant test freshness | **DEFECT** — stale 2.2/release-format assumptions and warnings; rebaselined. |
| 6 | Canonical File 06 ownership | Clean. |
| 7 | Fixed sixteen knowledge types | Clean. |
| 8 | Editor assigned-type enforcement | **DEFECT** — coarse edit capability did not enforce File 06 assigned knowledge types; added native type scope. |
| 9 | Entry reviewer assignment | **DEFECT** — review capability existed but explicit assignment/scope/expiry was absent; added. |
| 10 | Entry conflict/self-review disclosure | Clean; existing independent-review/conflict controls preserved. |
| 11 | File 00 current-claim hard dependency | Clean. |
| 12 | Existing entry owner/state authorization | Clean at existing core layer. |
| 13 | Future claim object scope | **DEFECT** — Future claim writes/reviews needed parent-concept object scope and assignment checks; added. |
| 14 | External scholarly metadata object scope | **DEFECT** — staging/review was capability-heavy and not fully bound to governed object scope; corrected. |
| 15 | Research transition object scope | Clean after exact object permission verification. |
| 16 | Dataset approval object scope | **DEFECT** — approval needed binding to governed research object; corrected. |
| 17 | Integrity apply object scope | **DEFECT** — application needed target concept/research object permission, not global capability alone; corrected. |
| 18 | wp-admin entry type-scope bypass | **DEFECT** — native admin save could bypass assigned-type policy; fail-closed guard added. |
| 19 | Universal composer type-scope bypass | **DEFECT** — composer draft/rollback needed the same File 06 scope; wrapped and enforced. |
| 20 | Core numeric public identifier enumeration | **DEFECT** — raw DB numeric identifiers remained accepted on public/core routes; blocked. |
| 21 | Public reference internal IDs | **DEFECT** — core entry DTO leaked reference DB IDs; removed. |
| 22 | Public version internal IDs/actors | **DEFECT** — version DTO exposed internal version IDs/creator IDs; removed. |
| 23 | Core graph internal IDs | **DEFECT** — graph edges used internal concept IDs; converted to canonical public IDs. |
| 24 | Integrity replacement internal ID | **DEFECT** — replacement object DB ID could leak; converted to public replacement ID. |
| 25 | Future canonical UUID public routes | Clean. |
| 26 | Future provenance public minimization | Clean. |
| 27 | Claim evidence fail-closed gate | Clean. |
| 28 | Human review of external evidence | Clean. |
| 29 | Claim current-version binding | Clean. |
| 30 | Translation atomic review/publish | Clean. |
| 31 | Migration preflight boundedness | Clean. |
| 32 | Migration postflight boundedness | **DEFECT** — ORCID/legacy impact reconciliation used unbounded postflight operations; made 100-row resumable batches. |
| 33 | Migration readiness after schema version | **DEFECT** — schema version alone could mark Future ready before postflight finished; `ready()` now requires all completion markers. |
| 34 | Uninstall postflight migration state | **DEFECT** — new cursor/done state was omitted from explicit purge; added. |
| 35 | Uninstall local editorial scope state | **DEFECT** — File 06-owned editor scope usermeta/leases required cleanup on explicit destructive purge; added without touching File 00 identity. |
| 36 | Future maintenance concurrency | **DEFECT** — Future worker lacked an exclusive lease; serialized with expiry-safe lease. |
| 37 | Core maintenance concurrency | **DEFECT** — core housekeeping/scheduler lacked one owner lease; serialized. |
| 38 | Scheduled publication fallback | **DEFECT** — inherited maintenance could call legacy `publish_due()` after secure fingerprint scheduling; unsafe fallback removed. |
| 39 | Scheduled content/review fingerprint invalidation | Clean. |
| 40 | Outbox retry/dead-letter/reconciliation | Clean. |
| 41 | Mutation nonce/rate/idempotency | Clean. |
| 42 | Provider URL/SSRF safety | Clean. |
| 43 | Provider-specific identifier validation | Clean. |
| 44 | Structured Crossref correction/retraction signals | Clean. |
| 45 | PubMed adapter boundary | Clean. |
| 46 | ClinicalTrials claim/research binding | Clean. |
| 47 | ORCID researcher-identity boundary | Clean. |
| 48 | DataCite metadata adapter | Clean. |
| 49 | MeSH concept-mapping scope | Clean. |
| 50 | Duplicate intelligence/human merge boundary | Clean. |
| 51 | Knowledge graph relation schema/DTO | Clean after round 23 DTO correction. |
| 52 | Knowledge time machine | Clean. |
| 53 | Consumer impact queue | Clean. |
| 54 | Freshness engine | Clean. |
| 55 | Research-priority gap radar | Clean. |
| 56 | Citation laboratory/rights-safe export | Clean. |
| 57 | Watchlists/File 19 delivery boundary | Clean. |
| 58 | Translation source-version invalidation | Clean. |
| 59 | Integrity command center | Clean. |
| 60 | Existing Future privacy lifecycle | Clean. |
| 61 | New editorial-governance privacy lifecycle | **DEFECT** — new editor/reviewer assignment metadata initially lacked dedicated export/erase handling; added with legal-hold support. |
| 62 | Legal-hold behavior | Clean after round 61. |
| 63 | Successful-case governance | Clean. |
| 64 | Dataset privacy/de-identification | Clean. |
| 65 | Structured data/SEO | Clean. |
| 66 | A–Z/faceted/search behavior | Clean at source/invariant level. |
| 67 | RTL/localization/accessibility source controls | Clean at source level; real browser/a11y staging evidence remains external. |
| 68 | Performance/bounded query policy | Clean after bounded migration corrections. |
| 69 | Observability/health/diagnostics | Clean at source level. |
| 70 | PHP/WordPress compatibility declarations | Clean; PHP 7.4 and 8.3 syntax jobs pass. |
| 71 | Schema verification | Clean. |
| 72 | Cross-file canonical ownership | Clean. |
| 73 | File 20 shell/layout boundary | Clean. |
| 74 | File 24 assurance boundary | Clean. |
| 75 | File 25 visual/graph-render boundary | Clean. |
| 76 | Automated invariant integrity | **DEFECT** — CI exposed brittle whitespace/version assumptions in test code; tests were corrected and rerun rather than ignored. |
| 77 | REST pre-callback execution semantics | **DEFECT** — successful object-scope guards returning literal `true` could short-circuit callbacks; normalizer restores allow-and-continue semantics. |
| 78 | Research reviewer/integrity workflow | **DEFECT** — research reviews lacked explicit assignment and research integrity apply accepted pre-acceptance states; assignment added and apply restricted to `accepted`. |
| 79 | Release checksum/local verification closure | **DEFECT** — committed checksum register was stale and local runner depended on it circularly; replaced by CI-generated package/source digests and deterministic local builds. |
| 80 | Fresh final source + CI/package closure | Clean — exact reviewed source head passed core, first-80, Future-18, second-80 invariants, PHP 7.4/8.3 and deterministic packaging. |

## Defect-round register

Defects were found and corrected in **30 of 80 rounds**:

`1, 2, 3, 4, 5, 8, 9, 13, 14, 16, 17, 18, 19, 20, 21, 22, 23, 24, 32, 33, 34, 35, 36, 37, 38, 61, 76, 77, 78, 79`

No new defect was found in the other **50 rounds**.

## Closing status

| Release state | Status |
|---|---|
| Specified | Yes |
| Coded | Yes — audit branch candidate |
| Packaged | Yes — deterministic |
| Automated-QA Green | Yes — exact reviewed source run `31449216962` |
| Staging-Accepted | No / unverified |
| Live-Deployed | No / unverified |
| Operational | No / unverified |

### Live-reality register

- Repository reviewed source HEAD: `a317c034ac76e87504c7b61d01021046abe66e77`
- Deployed Version: **unverified**
- Live DB Version: **unverified**
- Live Migration State: **unverified**
- Live Verification Status: **not performed**

Exact deployed code is unverified; repository-based diagnosis/completion evidence remains separate from staging/live truth.
