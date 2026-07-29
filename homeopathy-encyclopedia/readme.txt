=== Homeopathy Encyclopedia Foundation ===
Contributors: sabrihomeopathy
Tags: encyclopedia, knowledge graph, remedies, symptoms, pathology
Requires at least: 6.1
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

A governed encyclopedia and knowledge-relationship foundation for the Sabri Social Homeopathy Platform.

== Description ==

File 06 provides a searchable and moderated American English encyclopedia foundation integrated with File 00 membership authority, File 01 platform pages, File 05 connected learning, and File 20 unified navigation and layout.

Core controls:
* Sixteen governed knowledge types and a controlled body-system taxonomy.
* Dedicated custom post type capabilities; ordinary WordPress post editors cannot manage encyclopedia entries by default.
* File 00-authoritative Founder and verified-doctor eligibility without role-only fallbacks.
* Dependency preflight, idempotent schema migrations, managed-page ownership, activation rollback, and guarded uninstall.
* Server-side title, summary, content, classification, references, red-flag, safety, language-declaration, relationship, and image validation.
* Image size, MIME, dimension, and pixel-count checks with cleanup when publication fails.
* Versioned moderation state transitions, self-review prevention, optimistic locking, mandatory rejection/hiding notes, reviewer identity, and visible audit history.
* True catalog pagination, exact A–Z and taxonomy filters, structured-field search indexing, and atomic view metrics.
* Cache-safe bookmark state, non-cacheable private pages, rate limits, corrections, reports, and versioned resolution records.
* Privacy export/erasure coverage for bookmarks, feedback, entries, comments, and audit records with explicit retention messages.
* Article and MedicalWebPage structured data emitted only for governed public entries.

== Mandatory dependencies ==

* File 00 — Sabri Membership Core 1.0.1 or later.
* File 01 — Sabri Platform Foundation.
* File 05 — Learn Sabri Classical Homeopathy 1.0.0 or later.
* File 20 — Sabri Unified Application Shell 1.0.0 or later.

The plugin safely pauses when a mandatory contract is unavailable. File 02 authentication, File 03 profiles, and File 04 publishing may integrate through their owning platform contracts but are not duplicated inside File 06.

== Installation ==

1. Confirm the mandatory dependencies are active and healthy.
2. Confirm File 01 still owns a valid Encyclopedia page containing the platform module shortcode.
3. Upload the File 06 ZIP through WordPress Admin > Plugins > Add New > Upload Plugin.
4. Activate Homeopathy Encyclopedia Foundation.
5. Review the automatically created starter drafts; they are not published automatically.
6. Use Encyclopedia Management for moderation, corrections, reports, and audit history.
7. Complete authenticated staging acceptance before production deployment.

== Privacy and retention ==

Private bookmark state is loaded after page display instead of being embedded in cacheable public HTML. Saved Knowledge and submission pages are marked non-cacheable. Destructive uninstall requires both the HE_PURGE_ON_UNINSTALL constant and the he_allow_destructive_uninstall option set to yes. Otherwise content and governance records are retained.

== Changelog ==

= 1.0.0 =
* Corrected the File 06 v0.1.0 audit blockers.
* Added authoritative dependency, permission, migration, moderation, privacy, caching, search, packaging, and lifecycle controls.

= 0.1.0 =
* Original immutable baseline release.
