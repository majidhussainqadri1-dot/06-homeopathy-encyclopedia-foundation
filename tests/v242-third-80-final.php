<?php
/** Corrected evidence checks for the third fresh File 06 80-round review. */
$R=dirname(__DIR__);$P=$R.'/homeopathy-encyclopedia';$F=array();
foreach(array(
'b'=>'homeopathy-encyclopedia.php','d'=>'includes/class-he-v2-domain.php','a'=>'includes/class-he-v2-auth.php','s'=>'includes/class-he-v2-schema.php','api'=>'includes/class-he-v2-api.php','pr'=>'includes/class-he-v2-privacy.php',
'g'=>'includes/class-he-v22-governance.php','i'=>'includes/class-he-v22-integrity.php','sc'=>'includes/class-he-v22-schedule.php','se'=>'includes/class-he-v22-search.php','ts'=>'includes/class-he-v22-type-schemas.php','rg'=>'includes/class-he-v22-research-guard.php','op'=>'includes/class-he-v22-operations.php',
'f23'=>'includes/class-he-v23-future.php','f24s'=>'includes/class-he-v24-future-schema.php','f24a'=>'includes/class-he-v24-future-api.php','f24m'=>'includes/class-he-v24-migration-safety.php','f24p'=>'includes/class-he-v24-public-provenance.php',
'g241'=>'includes/class-he-v241-governance.php','rg241'=>'includes/class-he-v241-research-governance.php','rt241'=>'includes/class-he-v241-runtime-guard.php','dt241'=>'includes/class-he-v241-public-dto-guard.php',
't242'=>'includes/class-he-v242-third-audit.php','rc242'=>'includes/class-he-v242-runtime-corrections.php','ml242'=>'includes/class-he-v242-multilingual.php','rb242'=>'includes/class-he-v242-research-browse.php','ra242'=>'includes/class-he-v242-research-authoring.php','ls242'=>'includes/class-he-v242-language-surfaces.php','wl242'=>'includes/class-he-v242-watchlist.php','ref242'=>'includes/class-he-v242-reference-graph.php','ri242'=>'includes/class-he-v242-research-immutability.php','pt242'=>'includes/class-he-v242-public-translation-guard.php','lm242'=>'includes/class-he-v242-language-migration.php','css'=>'assets/css/encyclopedia-v2.css','un'=>'uninstall.php') as $k=>$v){$F[$k]=file_get_contents($P.'/'.$v);}
$F['wf']=file_get_contents($R.'/.github/workflows/file06-v2-complete.yml');$bad=array();$n=0;
function H($k,$x){global $F;return false!==strpos($F[$k],$x);}function Q($round,$ok,$name){global $bad,$n;$n++;if(!$ok)$bad[]=sprintf('Round %02d — %s',$round,$name);}
Q(1,preg_match("/HE_VERSION', '2\\.4\\.[0-9]+/",$F['b'])&&preg_match("/HE_CONTRACT_VERSION', '2\\.4\\.[0-9]+/",$F['b']),'v2.4.x version/contract family');
Q(2,H('b',"HE_SCHEMA_VERSION', 10"),'schema 10');
Q(3,preg_match("/'staging_accepted'\s*=>\s*false/",$F['b'])&&preg_match("/'live_deployed'\s*=>\s*false/",$F['b'])&&preg_match("/'operational'\s*=>\s*false/",$F['b']),'release truth');
Q(4,H('t242','repair_canonical_alias_language')&&H('lm242',"canonical_locale' => 'ur'"),'canonical language/alias');
Q(5,H('t242','he_alias_ambiguous'),'ambiguous alias fail closed');
Q(6,H('d','wp_generate_uuid4')&&H('d','canonical_slug'),'canonical identity');
Q(7,H('rc242','composer_rollback_safe')&&H('rc242','clean_post_cache'),'atomic entry compensation');
Q(8,H('g241','editor_type_allowed')&&H('g241','META_EDITOR_TYPES'),'editor type scope');
Q(9,16===substr_count($F['ts'],"'body_system_required' =>"),'16 type schemas');
Q(10,H('d','validate_for_review')&&H('d','reference-required'),'reference/review minimums');
Q(11,H('t242','he_reference_version_scope'),'reference-version provenance');
Q(12,H('d','red_flags')&&H('d','emergency_boundary'),'clinical safety');
Q(13,H('g241','reviewer_assigned')&&H('g241','META_REVIEW_ASSIGNMENTS'),'entry reviewer assignment');
Q(14,H('g','content_hash')&&H('g','reviewed_row_version'),'review-content binding');
Q(15,H('d','he_version_conflict')&&H('d','row_version'),'entry concurrency');
Q(16,H('sc','content-or-review-changed-before-publication'),'schedule revalidation');
Q(17,H('t242','integrity_object_guard')&&H('t242','he_reviewer_assignment_required'),'integrity transition object scope');
Q(18,H('t242','early_rest_guard')&&H('i','enforce_apply_gate'),'early apply short-circuit defense');
Q(19,H('t242','he_invalid_replacement'),'replacement validation');
Q(20,H('t242','file06-merge-object'),'merge both-object auth');
Q(21,H('t242','he_merge_reason_required'),'merge reason');
Q(22,H('ref242','he_relation_provenance_required')&&H('ref242','he_relation_provenance_invalid'),'relationship provenance');
Q(23,H('dt241','public_graph_edges')&&H('dt241','internal numeric identifiers'),'public graph IDs');
Q(24,H('f23','Semantic duplicate intelligence')&&H('f23','advisory only')&&H('f23',"'state'=>'candidate'"),'duplicate intelligence advisory');
Q(25,H('se','spelling-recovery')&&H('se','exact-phrase-token-alias'),'search recovery');
Q(26,H('api','autocomplete'),'autocomplete');
Q(27,H('api','bookmark')&&H('s',"table( 'bookmarks' )"),'bookmarks');
Q(28,H('t242','ResearchStateFailClosed.v1')&&H('t242','research_post_is_public'),'research post/domain parity');
Q(29,H('rb242','MAX_SCAN = 500')&&H('rb242','governance_filtered'),'research browse bounded pagination');
Q(30,(H('rc242','research_conflict_normalization_failed')||H('rc242','research_conflict_postsuccess_invariant_failed'))&&H('ra242','none_declared'),'research conflict shape');
Q(31,H('ra242','Investigators')&&H('ra242','he_v242_de_identification'),'research authoring completeness');
Q(32,H('t242','he_v242_expected_research_version')&&H('t242','stale overwrite'),'research admin concurrency');
Q(33,H('rg241','research-reviewer-assignment')&&H('rg241','he_reviewer_assignment_required'),'research reviewer assignment');
Q(34,H('g','research_release_gate'),'research release gate');
Q(35,H('g','rest_create_research_integrity')&&H('g','ResearchIntegritySubmitted.v1'),'research integrity submission');
Q(36,H('i','integrity/(?P<id>')&&H('i','he_integrity_transition_forbidden')&&H('t242','integrity_object_guard'),'research/general integrity transition');
Q(37,H('rg241',"'accepted'!==\$action['status']")&&H('rg241','he_integrity_acceptance_required'),'accepted-only research apply');
Q(38,H('t242','dataset_post_gate'),'dataset request state parity');
Q(39,H('t242','dataset-access')&&H('g241','file06-dataset-approval'),'dataset approval state parity');
Q(40,H('rg','he_dataset_private_by_default')&&H('rg','access_policy'),'dataset privacy');
Q(41,H('d','کامیاب کیس')&&H('ra242','adverse_events')&&H('ra242','limitations'),'successful-case governance');
Q(42,H('g','serve_research_permanent_id')&&H('t242','guard_public_research_route'),'permanent research route');
Q(43,H('pr','wp_privacy_personal_data_exporters'),'privacy export');
Q(44,H('pr','he_v2_privacy_legal_hold'),'legal hold');
Q(45,H('b','editor_scope_export_erase')&&H('b','reviewer_assignment_export_erase'),'governance privacy');
Q(46,H('a','SMC_Contracts::assertions')&&H('a','he_identity_provider_unavailable'),'File00 fail closed');
Q(47,H('a','suspended')&&H('a','membership_allowed'),'suspension denial');
Q(48,H('d','idempotent_begin')||H('api','Idempotency-Key'),'idempotency');
Q(49,H('d','rate_allow')&&H('ml242','he_rate_limited'),'rate limiting');
Q(50,H('s','OPTION_SAFE_MODE')&&(H('rc242','safe mode')||H('rc242','OPTION_SAFE_MODE')),'safe mode');
Q(51,H('op',"status='dead-letter'")&&H('b','outbox_reconciliation'),'dead-letter/outbox');
Q(52,H('b',"'owner'=>'file-06'")&&H('d',"'file-06'"),'canonical ownership');
Q(53,H('rt241','CORE_LEASE_OPTION')&&H('rt241','core_maintenance_serialized'),'core maintenance lease');
Q(54,H('f24s','OPTION_MAINTENANCE_LEASE')&&H('f24s','acquire_maintenance_lease')&&H('f24s','release_maintenance_lease'),'Future maintenance lease');
Q(55,H('g','upgrade_lock')&&H('g','add_option'),'migration lock');
Q(56,H('g','migration_quarantine'),'migration quarantine');
Q(57,H('f24m','postflight')&&H('f24m','const BATCH = 100'),'bounded postflight');
Q(58,H('t242','guard_hard_delete')&&H('un','he_v241_core_maintenance_lease'),'delete/uninstall/rollback');
Q(59,H('f24s','wp_safe_remote_get'),'safe provider fetch');
Q(60,H('f24s','limit_response_size'),'provider response bound');
Q(61,H('t242','research_external_assignment_guard'),'research external evidence assignment');
Q(62,H('g','he_independent_review_required')||H('f24a','independent'),'independent review');
Q(63,H('f24a','claims_without_evidence')&&H('f24s','claim_evidence'),'claim evidence gate');
Q(64,H('f24p','he_canonical_public_id_required'),'canonical public Future routes');
Q(65,H('f24a','mesh'),'MeSH');
Q(66,H('f24a','researcher-identities')&&H('f24s','valid_orcid'),'ORCID');
Q(67,H('f23','duplicates/scan'),'duplicate scan');
Q(68,H('f23','future/graph'),'Future graph');
Q(69,H('f23','time-machine'),'time machine');
Q(70,H('f23','impact_queue'),'impact propagation');
Q(71,H('f23','freshness'),'freshness');
Q(72,H('ref242','citation-only')&&H('ref242','he_reference_quote_limit'),'citation/rights');
Q(73,H('wl242',"array( 'concept','topic','research' )")&&H('wl242','excluded_from_public_provenance'),'private validated watchlists');
Q(74,H('ml242','SSH-XPLAN-MLSEO-2026-v1.0')&&H('ml242',"'ur', 'en-US', 'ar', 'zh-Hans', 'hi', 'es', 'fr', 'bn', 'pt'")&&H('lm242',"legacy_locale' => 'ur-PK'"),'ten-language policy/migration');
Q(75,H('pt242','source_version_number')&&H('pt242',"unset( \$data['source_version'] )"),'public translation ID guard');
Q(76,H('t242','nocache_headers')&&H('rb242',"p.post_status='publish'"),'public research cache/state');
Q(77,H('css','--he-primary:var(--sabri-color-primary')&&H('css','[dir="rtl"]'),'shared tokens/RTL');
Q(78,H('wf','reproducible-package')&&H('wf','source-tree-hash.py')&&H('wf','wordpress-smoke'),'package + runtime smoke evidence');
Q(79,is_file($R.'/docs/REVIEW-V242-ROUND-1.md'),'fresh review 1');
Q(80,is_file($R.'/docs/REVIEW-V242-ROUND-2.md'),'fresh review 2');
if($n!==80)$bad[]='matrix count '.$n;if($bad){fwrite(STDERR,"File 06 inherited third-80 matrix FAILED under current v2.4.x:\n- ".implode("\n- ",$bad)."\n");exit(1);}echo "File 06 inherited third fresh 80-round matrix passed under current v2.4.x (80/80).\n";
