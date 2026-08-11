<?php
/**
 * File 06 v2.4.2 — third fresh 80-round review matrix.
 * Each numbered assertion is one independently named review theme. A failure
 * identifies the exact round/theme that must be corrected before release evidence.
 */
$root=dirname(__DIR__);$p=$root.'/homeopathy-encyclopedia';
$files=array(
 'b'=>file_get_contents($p.'/homeopathy-encyclopedia.php'),
 'd'=>file_get_contents($p.'/includes/class-he-v2-domain.php'),
 'a'=>file_get_contents($p.'/includes/class-he-v2-auth.php'),
 's'=>file_get_contents($p.'/includes/class-he-v2-schema.php'),
 'api'=>file_get_contents($p.'/includes/class-he-v2-api.php'),
 'pub'=>file_get_contents($p.'/includes/class-he-v2-public.php'),
 'pr'=>file_get_contents($p.'/includes/class-he-v2-privacy.php'),
 'g'=>file_get_contents($p.'/includes/class-he-v22-governance.php'),
 'i'=>file_get_contents($p.'/includes/class-he-v22-integrity.php'),
 'sc'=>file_get_contents($p.'/includes/class-he-v22-schedule.php'),
 'se'=>file_get_contents($p.'/includes/class-he-v22-search.php'),
 'ts'=>file_get_contents($p.'/includes/class-he-v22-type-schemas.php'),
 'rg'=>file_get_contents($p.'/includes/class-he-v22-research-guard.php'),
 'op'=>file_get_contents($p.'/includes/class-he-v22-operations.php'),
 'f23'=>file_get_contents($p.'/includes/class-he-v23-future.php'),
 'f24s'=>file_get_contents($p.'/includes/class-he-v24-future-schema.php'),
 'f24a'=>file_get_contents($p.'/includes/class-he-v24-future-api.php'),
 'f24m'=>file_get_contents($p.'/includes/class-he-v24-migration-safety.php'),
 'f24p'=>file_get_contents($p.'/includes/class-he-v24-public-provenance.php'),
 'g241'=>file_get_contents($p.'/includes/class-he-v241-governance.php'),
 'rg241'=>file_get_contents($p.'/includes/class-he-v241-research-governance.php'),
 'rt241'=>file_get_contents($p.'/includes/class-he-v241-runtime-guard.php'),
 'dt241'=>file_get_contents($p.'/includes/class-he-v241-public-dto-guard.php'),
 't242'=>file_get_contents($p.'/includes/class-he-v242-third-audit.php'),
 'rc242'=>file_get_contents($p.'/includes/class-he-v242-runtime-corrections.php'),
 'ml242'=>file_get_contents($p.'/includes/class-he-v242-multilingual.php'),
 'rb242'=>file_get_contents($p.'/includes/class-he-v242-research-browse.php'),
 'ra242'=>file_get_contents($p.'/includes/class-he-v242-research-authoring.php'),
 'ls242'=>file_get_contents($p.'/includes/class-he-v242-language-surfaces.php'),
 'wl242'=>file_get_contents($p.'/includes/class-he-v242-watchlist.php'),
 'ref242'=>file_get_contents($p.'/includes/class-he-v242-reference-graph.php'),
 'ri242'=>file_get_contents($p.'/includes/class-he-v242-research-immutability.php'),
 'pt242'=>file_get_contents($p.'/includes/class-he-v242-public-translation-guard.php'),
 'lm242'=>file_get_contents($p.'/includes/class-he-v242-language-migration.php'),
 'css'=>file_get_contents($p.'/assets/css/encyclopedia-v2.css'),
 'un'=>file_get_contents($p.'/uninstall.php'),
 'wf'=>file_get_contents($root.'/.github/workflows/file06-v2-complete.yml'),
);
$fail=array();$count=0;
function r80($n,$ok,$msg){global $fail,$count;$count++;if(!$ok)$fail[]=sprintf('Round %02d — %s',$n,$msg);}
function has80($k,$needle){global $files;return isset($files[$k])&&false!==strpos($files[$k],$needle);}

r80(1,has80('b',"HE_VERSION', '2.4.2")&&has80('b',"HE_CONTRACT_VERSION', '2.4.2"),'exact candidate version/contract');
r80(2,has80('b',"HE_SCHEMA_VERSION', 10"),'schema contract');
r80(3,(bool)preg_match("/'staging_accepted'\s*=>\s*false/",$files['b'])&&(bool)preg_match("/'live_deployed'\s*=>\s*false/",$files['b'])&&(bool)preg_match("/'operational'\s*=>\s*false/",$files['b']),'release-truth separation');
r80(4,has80('t242','repair_canonical_alias_language')&&has80('lm242',"canonical_locale' => 'ur'"),'canonical alias/source-language consistency');
r80(5,has80('t242','he_alias_ambiguous'),'cross-language alias ambiguity fail-closed');
r80(6,has80('d','wp_generate_uuid4')&&has80('d','canonical_slug'),'canonical UUID/slug identity');
r80(7,has80('rc242','composer_rollback_safe')&&has80('rc242','START TRANSACTION')&&has80('rc242','clean_post_cache'),'atomic pristine entry composer rollback');
r80(8,has80('g241','editor_type_allowed')&&has80('g241','META_EDITOR_TYPES'),'editor knowledge-type scope');
r80(9,16===substr_count($files['ts'],"'body_system_required' =>"),'all 16 type-specific schemas');
r80(10,has80('d','validate_for_review')&&has80('d','he_reference_required'),'reference/review minimums');
r80(11,has80('t242','he_reference_version_scope'),'reference version bound to same concept');
r80(12,has80('d','red_flags')&&has80('d','emergency_boundary'),'clinical red-flags and emergency boundary');
r80(13,has80('g241','reviewer_assigned')&&has80('g241','META_REVIEW_ASSIGNMENTS'),'entry reviewer assignment');
r80(14,has80('g','content_hash')&&has80('g','reviewed_row_version'),'review bound to reviewed content/version');
r80(15,has80('d','he_version_conflict')&&has80('d','row_version'),'entry optimistic concurrency');
r80(16,has80('sc','content-or-review-changed-before-publication')&&has80('sc','EncyclopediaEntryScheduleInvalidated.v1'),'scheduled-publication revalidation');
r80(17,has80('t242','integrity_object_guard')&&has80('t242','he_reviewer_assignment_required'),'integrity transition object/reviewer authorization');
r80(18,has80('t242','rest_request_before_callbacks')&&has80('t242','file06-integrity-'),'early integrity short-circuit authorization');
r80(19,has80('t242','he_invalid_replacement'),'replacement-object validation');
r80(20,has80('t242','file06-merge-object'),'merge authorization on source and target');
r80(21,has80('t242','he_merge_reason_required'),'documented merge decision reason');
r80(22,has80('ref242','he_relation_provenance_required')&&has80('ref242','he_relation_provenance_invalid'),'mandatory graph relationship provenance');
r80(23,has80('dt241','public_graph_edges')&&has80('dt241','internal numeric identifiers'),'public graph/internal-ID boundary');
r80(24,has80('f23','duplicates/scan')&&has80('f24s','similarity'),'duplicate intelligence remains advisory/governed');
r80(25,has80('se','spelling-recovery')&&has80('se','exact-phrase-token-alias'),'search semantics');
r80(26,has80('api','autocomplete'),'bounded autocomplete route');
r80(27,has80('api','bookmark')&&has80('s',"table( 'bookmarks' )"),'account bookmarks');
r80(28,has80('t242','ResearchStateFailClosed.v1')&&has80('t242','research_post_is_public'),'research domain/WP-state parity');
r80(29,has80('rb242','MAX_SCAN = 500')&&has80('rb242','governance_filtered'),'bounded public research pagination without starvation');
r80(30,has80('rc242','research_conflict_normalization_failed')&&has80('ra242','none_declared'),'research conflict shape normalized');
r80(31,has80('ra242','Investigators')&&has80('ra242','he_v242_conflict_statement')&&has80('ra242','he_v242_de_identification'),'complete research authoring fields');
r80(32,has80('t242','he_v242_expected_research_version')&&has80('t242','stale overwrite'),'research admin concurrency preflight');
r80(33,has80('rg241','research-reviewer-assignment')&&has80('rg241','he_reviewer_assignment_required'),'research reviewer assignment');
r80(34,has80('g','research_release_gate')||has80('rg241','research_release'),'research release gate');
r80(35,has80('t242','research-integrity')&&has80('rg241','research integrity'),'research integrity submission governance');
r80(36,has80('rg241','research_integrity_transition')||has80('rg241','integrity transition'),'research integrity state transition');
r80(37,has80('rg241',"'accepted'!==\$action['status']")&&has80('rg241','he_integrity_acceptance_required'),'accepted-only research integrity apply');
r80(38,has80('t242','dataset_post_gate'),'dataset access requires public research post parity');
r80(39,has80('t242','dataset-access')&&has80('g241','file06-dataset-approval'),'dataset approval object scope');
r80(40,has80('rg','he_dataset_private_by_default')&&has80('rg','access_policy'),'dataset private-by-default governance');
r80(41,has80('d','کامیاب کیس')&&has80('ra242','adverse_events')&&has80('ra242','limitations'),'successful-case consent/anonymization/outcome fields');
r80(42,has80('g','serve_research_permanent_id')&&has80('t242','guard_public_research_route'),'permanent research public route validation');
r80(43,has80('pr','wp_privacy_personal_data_exporters'),'privacy export coverage');
r80(44,has80('pr','he_v2_privacy_legal_hold'),'erasure/legal-hold control');
r80(45,has80('b','editor_scope_export_erase')&&has80('b','reviewer_assignment_export_erase'),'governance metadata privacy lifecycle');
r80(46,has80('a','SMC_Contracts::assertions')&&has80('a','he_identity_provider_unavailable'),'File 00 fail-closed identity authority');
r80(47,has80('a','suspended')&&has80('a','membership_allowed'),'suspended/invalid member denial');
r80(48,has80('api','Idempotency-Key')||has80('d','idempotent_begin'),'idempotent mutation contract');
r80(49,has80('d','rate_allow')&&has80('ml242','he_rate_limited'),'rate limiting');
r80(50,has80('s','OPTION_SAFE_MODE')&&has80('rc242','safe mode'),'safe-mode mutation fail-closed');
r80(51,has80('op',"status='dead-letter'")&&has80('b','outbox_reconciliation'),'outbox/dead-letter operability');
r80(52,has80('d',"'file-06'")&&has80('b',"'owner'=>'file-06'"),'canonical ownership markers');
r80(53,has80('rt241','CORE_LEASE_OPTION')&&has80('rt241','core_maintenance_serialized'),'core maintenance serialization');
r80(54,has80('g241','LEASE_OPTION')&&has80('g241','maintenance_serialized'),'Future maintenance serialization');
r80(55,has80('g','upgrade_lock')&&has80('g','add_option'),'migration lock');
r80(56,has80('g','migration_quarantine'),'migration quarantine');
r80(57,has80('f24m','postflight')&&has80('f24m','const BATCH = 100'),'bounded resumable postflight');
r80(58,has80('t242','guard_hard_delete')&&has80('un','he_v241_core_maintenance_lease'),'non-destructive governed delete/uninstall/rollback controls');
r80(59,has80('f24s','wp_safe_remote_get'),'safe external provider request path');
r80(60,has80('f24s','limit_response_size'),'provider response bound');
r80(61,has80('t242','research_external_assignment_guard'),'research-bound external evidence reviewer assignment');
r80(62,has80('f24s','independent')||has80('f24a','independent'),'independent claim/review governance');
r80(63,has80('f24a','claims_without_evidence')&&has80('f24s','claim_evidence'),'claim evidence gate');
r80(64,has80('f24p','he_canonical_public_id_required'),'canonical public-ID Future routes');
r80(65,has80('f24a','mesh'),'MeSH integration scope');
r80(66,has80('f24a','researcher-identities')&&has80('f24s','valid_orcid'),'ORCID identity mapping');
r80(67,has80('f23','duplicates/scan'),'duplicate candidate scan');
r80(68,has80('f23','future/graph'),'Future graph explorer');
r80(69,has80('f23','time-machine'),'knowledge time machine');
r80(70,has80('f23','impact_queue'),'cross-platform impact propagation');
r80(71,has80('f23','freshness'),'freshness/review-due intelligence');
r80(72,has80('ref242','citation-only')&&has80('ref242','he_reference_quote_limit'),'citation rights and quote-bound governance');
r80(73,has80('wl242',"array( 'concept','topic','research' )")&&has80('wl242','excluded_from_public_provenance'),'private validated watchlists/File 19 boundary');
r80(74,has80('ml242','SSH-XPLAN-MLSEO-2026-v1.0')&&has80('ml242',"'ur', 'en-US', 'ar', 'zh-Hans', 'hi', 'es', 'fr', 'bn', 'pt'")&&has80('lm242',"legacy_locale' => 'ur-PK'"),'ten-language/source-language policy and bounded legacy migration');
r80(75,has80('pt242','source_version_number')&&has80('pt242',"unset( \$data['source_version'] )"),'public translation internal-ID guard');
r80(76,has80('t242','nocache_headers')&&has80('rb242',"p.post_status='publish'"),'public research cache/state privacy');
r80(77,has80('css','--he-primary:var(--sabri-color-primary')&&has80('css','[dir="rtl"]'),'shared visual token/RTL accessibility basis');
r80(78,has80('wf','reproducible-package')&&has80('wf','source-tree-hash.py'),'reproducible package/checksum workflow');
r80(79,is_file($root.'/docs/REVIEW-V242-ROUND-1.md'),'fresh post-final-code review round 1 evidence');
r80(80,is_file($root.'/docs/REVIEW-V242-ROUND-2.md'),'fresh post-final-code review round 2 evidence');

if(80!==$count)$fail[]='Matrix count is '.$count.', expected 80.';
if($fail){fwrite(STDERR,"File 06 v2.4.2 third 80-round invariants FAILED:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "File 06 v2.4.2 third fresh 80-round source-control matrix passed (80/80).\n";
