# File 06 — Twenty distinct source-code review rounds and root-cause corrections

**Date:** 2026-11-08 (Pakistan). **Plan:** user-supplied Sabri Website Master Plan v12, F06-FR/F06-NFR/F06-FUT, central permissions/consumer contracts. **Corrective starting source HEAD:** `b72f9326074e46d3ebec567f9b7d618c3a111457`. **Review branch:** `audit/file06-v12-twenty-code-review-2026-11-08` (stacked on PR #12, itself stacked on File 06 ↔ File 15 PR #11). These are repository realities, NOT Hostinger deployed code.

This report distinguishes **20 specific human-directed code-review scopes** from the existing **20 sequential repetitions of the complete automated regression suite**. Static code indicators and isolated mocks are not live WordPress/DB acceptance.

## Twenty independent review scopes

| Round | Subject / root-cause observation | Review decision and committed source-level correction |
|---|---|---|
| 01 | Frozen repository HEAD, owner/source/installed-state isolation | Verified candidate is unmerged; GitHub `main` remains README-only, no live claim. No new source defect. |
| 02 | Research consent was accepted on a user-supplied boolean, locale/policy alone | **Corrected:** require opaque evidence receipt and matching versioned independent File 00 verifier, fail closed when absent. `HE_V12_Completion::consent_attestation()`. |
| 03 | Old `case_consent_verified` records could remain eligible without current governed provenance | **Corrected:** private/public eligibility requires policy, locale, evidence, independent authority, state and verification; legacy boolean-only cases excluded. `HE_V22_Research_Guard`. |
| 04 | A previously verified case could outlive later subject consent revocation | **Corrected:** require a **current** File 00 receipt validation on case approval/public reads; missing/revoked provider fails closed. `he_v12_consent_receipt_current_v1`. |
| 05 | Withdrawal route could proceed without Future consumer schema and accepted overly broad identifiers | **Corrected:** require Future migration readiness and strict canonical public UUID before mutation. `HE_V12_Research_Consent`. |
| 06 | Legacy consent had no locale/purpose and was unwithdrawable via strict new matching | **Corrected:** authorized evidence-based `legacy-unverified` removal path, explicit acknowledgement; never upgrades legacy consent or medical eligibility. |
| 07 | Failed consent transaction could leave a patient case eligible while recovery was uncertain | **Corrected:** enter fail-closed safe mode on 503, with research guard excluding public patient cases until privacy state reconciliation. |
| 08 | A linked claim could transition to pending without a durable provenance/consumer queue write | **Corrected:** per-claim SQL transaction performs CAS, append-only provenance and six consumer queue records as one commit; failed substep rolls back. `HE_V12_Evidence_Impact`. |
| 09 | DOI with more than 100 linked approved claims could block permanently | **Corrected:** 40-claim bounded batches; processed approved claims leave the next-page selection; external cursor stays for next scan until all approved impacts are processed. |
| 10 | Retraction scanner could advance the Crossref cursor despite incomplete consumer fan-out | **Corrected:** require exact acknowledgable persisted consumer queue count before cursor advancement. `HE_V24_Future_Schema::scan_retractions()`. |
| 11 | Post-commit notification callback could throw after the full transaction committed | **Corrected:** isolate optional hook exceptions; preserve durable queue and report repairable runtime failure rather than implying rollback. |
| 12 | One arbitrary HEAD 404 was labeled a confirmed broken source | **Corrected:** two-observation `suspect`→`broken` model; never delete citations based on HEAD checks. |
| 13 | Temporary HEAD failure/403/405/429/5xx could clear a previously confirmed broken link | **Corrected:** preserve confirmed status through ambiguous transient failures; confirmed 2xx restores `ok`. |
| 14 | Reference-link cursor persistence was not verified | **Corrected:** verify checkpoint changes; stop processing and emit runtime diagnostic on failed persistence. |
| 15 | Persisted freshness state `current` could remain misleading after review due date passed | **Corrected:** recompute stale/urgent status at read time using UTC review-due timestamp; missing human review defaults to unknown, retracted never presented current. |
| 16 | Unrestricted reference URL metadata could publish private query parameters/credentials | **Corrected:** parsed public URL is restricted to safe http(s) authority/path; userinfo, query parameters, fragments are not exported. |
| 17 | Import matching accepted same canonical UUID/version without equivalent source or claim proof | **Corrected:** digest includes current reviewed claim evidence, summary and bibliographic metadata; conflicting digest forces `source-content-conflict`. Reimport remains preview-only. |
| 18 | Companion link booleans alone were accepted; generic regex markup replacement could interpolate replacement tokens | **Corrected:** independent owner attestation, current version + rights + safe URL checks; insert only into owned entry markup using literal string insertion. |
| 19 | WordPress sitemap can enumerate publish-status entries absent canonical scientific/safety approval, and private research | **Corrected:** canonical schema/approval/version/merge filtered entry sitemap SQL; remove consent-dependent private research from generic post-type sitemap. |
| 20 | Baseline mocks/static review could miss negative failure modes or misstate release evidence | **Corrected:** add distinct 20-focus static review gate and isolated consent/sitemap/link/claim-failure tests, retain syntax/legacy regressions and deterministic packaging. No separate unresolved source defect claimed from this round. |

**Defect accounting for this review:** 18 materially distinct corrective review focal areas (rounds 02–19), with several finer-grained safeguards inside those same areas. All **18/18 have candidate repository modifications**. This count is NOT a claim of complete semantic v12 code, real WordPress user acceptance or production resolution. Source-level acceptance depends on **the final exact HEAD CI** and independent second source review. Failures of historical remediation workflows with zero jobs must not be reported as new application defects without logs.

## Automated verification and negative-path matrix

- `tests/v12-twenty-source-review.php`: twenty distinct static source contract checks; **do not confuse with 20 human testers**.
- `tests/v12-completion-contract.php`: current independent consent, token-safe export, claim-linked fingerprint, two-observation link integrity, URL safety, stale review display, owner-attested cross-file links, Crossref provenance/queue transaction failures and bounded resume.
- `tests/v12-consent-withdrawal.php`: forbidden roles, stale row, wrong policy, session nonce, private/retracted transition, rollback and legacy withdrawal.
- `tests/v12-case-consent-visibility.php`: independently governed current receipt, revoked/legacy/expired/scope-deviant consent and direct identifier denial.
- `tests/v12-sitemap-privacy.php`: canonical review/safety/publish/type/version criteria and stale schema negative results.
- `tests/file15-bridge-contract.php`: stable File06 ↔ File15 canonical remedy/outbox identity.
- `tests/run-all.sh` runs the full inherited File 06 suite and deterministic double package comparison. The current `File 06 20-Part Governing Plan Parity` GitHub workflow executes **20 sequential identical full suite runs on one frozen SHA**. Actual CI is the sole source for pass/fail of the final SHA; no static document can certify itself.

## External blockers — not silently counted as fixed defects

1. **File 00 verified subject consent owner** must implement `he_v12_consent_receipt_verification_v1` at creation and `he_v12_consent_receipt_current_v1` for each release/read. Fallback intentionally denies publication of case records. A File 06 mock returning true is not a real File 00 contract.
2. **Real File 05/10/12 document/lesson/video owners** must sign `he_v12_related_resource_owner_attested_v1` and provide live URL, source version, rights and click-time eligibility. Do not invent cross-file content.
3. **Original source text, translation and independent review** of side-by-side editions remain absent where rights/sources were not supplied. Scholarly re-import is explicitly non-writing preview only.
4. **File 06 ↔ 15 ↔ 26 and File 00/19** installed versions, idempotent old-event reconciliation, cache/index redaction, clinical/consent negative tests and rights withdrawal need end-to-end staging.
5. **Hostinger deployment reality:** active plugin/ZIP SHA-256, real DB core 10 + Future 2, wp_options/migration/logs and production runtime have not been inspected; backup/restore/rollback and p75/p95/a11y/RTL acceptance remain mandatory.
6. **The 69 central catalogue requirements** have earlier static review only; they are not 69 proven missing code items nor semantically verified as all satisfied.

## Mandatory live-incident truth fields

- Repository `main` HEAD: `c6f44e923f2a6f2ed82d967d18422624169b2770` (README only at freeze).
- Prior candidate/PR #12 HEAD: `b72f9326074e46d3ebec567f9b7d618c3a111457`.
- Corrective review HEAD: record the final hash from Github PR after QA closes, never paste an older green hash here.
- Deployed Version: **UNVERIFIED**.
- DB Version: **UNVERIFIED**.
- Migration State: **UNVERIFIED**.
- Live Verification Status: **UNVERIFIED**.
- Stage/Founder Acceptance: **NOT VERIFIED**.

**Exact deployed code ابھی unverified ہے؛ repository-based diagnosis provisional ہے۔** Source corrections remain **DRAFT, UNMERGED, UNDEPLOYED** until owner signoff, companion parity, stage tests, and live retest.
