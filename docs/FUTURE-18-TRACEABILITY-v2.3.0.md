# File 06 v2.3.0 — Future Knowledge Intelligence 18 Traceability

| ID | Capability | Primary implementation | Data / event / boundary | Automated evidence |
|---|---|---|---|---|
| F06-FUT-001 | Claim-Level Evidence Graph | `HE_V23_Future_Intelligence::claims_*` | `he_claims`; evidence states supporting/contradicting/mixed/uncertain/historical | `tests/v23-future-invariants.php` |
| F06-FUT-002 | Universal Provenance Ledger | `provenance()` / `provenance_get()` | `he_provenance`; parent-hash chained records | Future-18 invariants + PHP lint |
| F06-FUT-003 | Retraction/Correction Watch | `scheduled_evidence_watch()` / `sync_external_row()` | `KnowledgeEvidenceReviewRequired.v1`; never auto-publish | Future-18 invariants |
| F06-FUT-004 | PubMed/NCBI Connector | external-provider adapter | provider=`pubmed`; read/review adapter only | provider allowlist invariant |
| F06-FUT-005 | Clinical-Trial Linker | external-provider adapter | provider=`clinicaltrials`; external mapping only | provider allowlist invariant |
| F06-FUT-006 | ORCID Researcher Identity Adapter | external-provider adapter | provider=`orcid`; File 00 remains authorization authority | ownership invariants |
| F06-FUT-007 | DataCite DOI/Dataset Intelligence | external-provider adapter | provider=`datacite`; metadata linkage only | provider allowlist invariant |
| F06-FUT-008 | MeSH Mapping | external-provider adapter | provider=`mesh`; File 06 taxonomy remains canonical | ownership invariants |
| F06-FUT-009 | Semantic Duplicate Intelligence | `semantic_duplicates()` | candidate scores only; `auto_merge=false` | explicit no-auto-merge invariant |
| F06-FUT-010 | Interactive Knowledge Graph Explorer | `graph()` | delegates to `HE_V2_Domain::graph()`; presentation owner File 25 | File 25 boundary invariant |
| F06-FUT-011 | Knowledge Time Machine | `time_machine()` | immutable `he_versions` chronology/as-of | schema alignment + lint |
| F06-FUT-012 | Cross-Platform Impact Propagation | `queue_impact()` / `impact()` | `he_impact_queue`; Files 05/12/15/16/21/26 | complete consumer-set invariant |
| F06-FUT-013 | Living Knowledge Freshness Engine | `freshness()` / `freshness_for()` | last review/scan, review due, current/review-due/stale | Future-18 invariants |
| F06-FUT-014 | Evidence-Gap & Research-Priority Radar | `scan_gaps()` / `gaps()` | `he_evidence_gaps`; human-governed priority | Future-18 invariants |
| F06-FUT-015 | Citation Laboratory | `citations()` | CSL-JSON/JSON-LD, RIS, BibTeX | Future-18 invariants |
| F06-FUT-016 | Knowledge Watchlists | `watch_*()` / `notify_watchers()` | `he_watches`; File 19 owns delivery | File 19 ownership invariant |
| F06-FUT-017 | Governed Multilingual Editions | `translation_*()` / `mark_translations_outdated()` | `he_translations`; source-version binding; outdated state | translation-outdated invariant |
| F06-FUT-018 | Encyclopedia Integrity Command Center | `command_center()` | File 06 cockpit; File 24 security, File 25 UI, File 19 delivery | ownership + no autonomous high-risk invariant |

## Versioned runtime evidence

- Functional source head: `759dccad88e29e48a92bb450c221f71e327cfca1`
- Exact CI-tested descendant: `de46ce4bba5c04423415041720732d447b532320`
- Runtime: `2.3.0`
- Schema: `9`
- Contract: `2.3`
- GitHub Actions run: `31402694660` — all four jobs passed.
- Package bytes: `99887`
- Package SHA-256: `4411dbf2899bda47745c7796c842da233d9415be9faab05b15f2c6668032fdf1`
- Source-tree SHA-256: `8083a242e6f998e059293a77c679725e8eb0b53258fecf6e90fd1430050e5c9e`

## Release-truth boundary

This trace establishes source-level requirement presence and exact automated package evidence. Hostinger/WordPress staging acceptance, real external-provider integration, database migration on the deployed environment, live deployment and operational acceptance remain separate gates.
