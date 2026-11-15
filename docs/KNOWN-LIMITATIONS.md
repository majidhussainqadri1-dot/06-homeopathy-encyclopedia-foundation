# Known Limitations / External Acceptance Dependencies

## Source candidate — not a production certificate

File 06's unmerged V12.3 source candidate is under corrective review. The new 2026-11-15 search, public cache, unpublished-alias and browser race source fixes are documented in `docs/FILE06-V12.3-SEARCH-REMEDIATION-2026-11-15.md`. Source tests and a disposable WordPress database are **not** evidence of Hostinger deployed parity, accepted clinical content, or staging/live completion.

The current File26 connector is `proposed`; event tombstone/reconciliation, live provider integration, file owner permissions and Founder signoff are incomplete. See tracked live freeze issue #18 and merge event integration issue #19. Treat any source-only risk as open for real environment acceptance, even when the local reproduction and candidate fix pass.

Required external acceptance: actual installed plugin and ZIP SHA-256, core/Future DB schema and `wp_options`, real File 00/05/10/12/15/16/19/20/21/22/23/24/25/26 versions, real consent and quotation rights, browser/device/RTL/screen-reader, p75/p95, cache/retraction lifecycle, backup restore and rollback, independent human reviews and Founder approval. No source commit establishes site installation.

**Deployment: HOLD / NOT VERIFIED. Live-verified fixes: 0.**
