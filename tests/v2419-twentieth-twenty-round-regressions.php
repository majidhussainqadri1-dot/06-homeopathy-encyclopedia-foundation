<?php
/** File 06 v2.4.19 twentieth fresh twenty-round regression controls. */
$root=dirname(__DIR__);$fail=[];
function f06r20_read($p){return file_exists($p)?file_get_contents($p):'';}
function f06r20_ok($ok,$m){global $fail;if(!$ok)$fail[]=$m;}
$status=f06r20_read($root.'/STATUS.md');$readme=f06r20_read($root.'/README.md');$plugin_readme=f06r20_read($root.'/homeopathy-encyclopedia/readme.txt');
f06r20_ok(strpos($status,'2.4.19 Twentieth Fresh Twenty-Round Candidate')!==false,'R20 status candidate truth is not aligned');
f06r20_ok(strpos($status,'audit/file-06-twentieth-twenty-round-v2.4.19-finalwork')!==false,'R20 status branch truth is not aligned');
f06r20_ok(strpos($status,'`1, 2, 3, 6, 7, 8, 10, 12, 15, 16, 17, 18, 19, 20`')!==false&&strpos($status,'`4, 5, 9, 11, 13, 14`')!==false,'R20 defect/clean ledger truth is not aligned');
f06r20_ok(strpos($readme,'audit/file-06-twentieth-twenty-round-v2.4.19-finalwork')!==false,'R20 README branch truth is not aligned');
f06r20_ok(strpos($plugin_readme,'= 2.4.19 =')!==false,'R20 plugin changelog missing current candidate');
$manifest=f06r20_read($root.'/V2-MANIFEST.md');$sbom=f06r20_read($root.'/SBOM.json');$runall=f06r20_read($root.'/tests/run-all.sh');$boot=f06r20_read($root.'/homeopathy-encyclopedia/homeopathy-encyclopedia.php');
f06r20_ok(strpos($manifest,'File 06 v2.4.19 Candidate Manifest')!==false&&strpos($manifest,'Runtime version: `2.4.19`')!==false&&strpos($manifest,'Contract: `2.4.19`')!==false,'R20 manifest release truth not aligned');
f06r20_ok(strpos($sbom,'"version": "2.4.19"')!==false&&strpos($sbom,'homeopathy-encyclopedia@2.4.19')!==false&&strpos($sbom,'2.4.19.zip')!==false,'R20 SBOM release truth not aligned');
f06r20_ok(strpos($runall,'file06-v2.4.19-a.zip')!==false&&strpos($runall,'All File 06 v2.4.19 automated checks')!==false,'R20 aggregate package/release truth not aligned');
f06r20_ok(strpos($boot,"HE_VERSION', '2.4.19'")!==false&&strpos($boot,"HE_CONTRACT_VERSION', '2.4.19'")!==false&&strpos($boot,"future_hardening_version'=>'2.4.19'")!==false,'R20 runtime/contract hardening truth not aligned');

$future=f06r20_read($root.'/homeopathy-encyclopedia/includes/class-he-v24-future-api.php');
f06r20_ok(strpos($future,"duplicate_candidate_persistence_failed")!==false&&strpos($future,"he_future_duplicate_write_failed")!==false&&strpos($future,"START TRANSACTION")!==false,'R2 duplicate intelligence persistence is not fail-closed/atomic');


foreach(array('class-he-v2-api.php','class-he-v22-governance.php','class-he-v22-integrity.php','class-he-v24-future-api.php','class-he-v24-future-review-guard.php','class-he-v242-multilingual.php','class-he-v241-governance.php','class-he-v241-research-governance.php','class-he-v242-watchlist.php') as $guard_file){$g=f06r20_read($root.'/homeopathy-encyclopedia/includes/'.$guard_file);f06r20_ok(strpos($g,'strlen( $key ) < 8')!==false||strpos($g,'strlen($key)<8')!==false,'R6 idempotency key minimum inconsistent in '.$guard_file);}


$third=f06r20_read($root.'/homeopathy-encyclopedia/includes/class-he-v242-third-audit.php');
f06r20_ok(strpos($third,'canonical_alias_language_reconciliation_failed')!==false&&strpos($third,'no partial language update was applied')!==false&&strpos($third,"START TRANSACTION")!==false,'R7 canonical language/alias reconciliation can partially persist');


$langmig=f06r20_read($root.'/homeopathy-encyclopedia/includes/class-he-v242-language-migration.php');
f06r20_ok(strpos($langmig,'language_translation_migration_write_failed')!==false&&strpos($langmig,'language_concept_migration_write_failed')!==false&&strpos($langmig,'COUNT(*) FROM {$aliases} WHERE language=\'ur-PK\'')!==false,'R8 language migration can advance/complete after partial write failure');


$research_domain=f06r20_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
$research_guard=f06r20_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-research-guard.php');
f06r20_ok(strpos($research_domain,'HE_V22_Research_Guard::validate_row( $row )')!==false&&strpos($research_domain,"'approved', 'active', 'analysis', 'peer_review', 'published'")!==false,'R10 research transitions do not enforce the complete governance validator');
f06r20_ok(strpos($research_guard,"WHERE public_id=%s")!==false&&strpos($research_guard,'$uuid = \'[0-9a-fA-F]{8}-')!==false,'R10 research transition guard still targets obsolete numeric routes');


$multi=f06r20_read($root.'/homeopathy-encyclopedia/includes/class-he-v242-multilingual.php');
f06r20_ok(strpos($multi,'multilingual_translation_save_atomic_failed')!==false&&strpos($multi,'if ( ! $provenance )')!==false&&strpos($multi,'FOR UPDATE')!==false&&strpos($multi,'START TRANSACTION')!==false,'R12 multilingual translation override can persist state without governed provenance/serialization');

// R15 — impact propagation/outbox reliability.
$future_schema = f06r20_read( $root . '/homeopathy-encyclopedia/includes/class-he-v24-future-schema.php' );
$future_api = f06r20_read( $root . '/homeopathy-encyclopedia/includes/class-he-v24-future-api.php' );
$integrations = f06r20_read( $root . '/homeopathy-encyclopedia/includes/class-he-v2-integrations.php' );
f06r20_ok( false !== strpos( $future_schema, 'impact_queue_persistence_incomplete' ), 'R15 impact queue must fail visibly when all required consumer records cannot be verified.' );
f06r20_ok( false !== strpos( $future_schema, '$ensured++' ), 'R15 existing dedupe rows must count as already-ensured consumer impact records.' );
f06r20_ok( false !== strpos( $future_api, 'he_future_impact_queue_failed' ), 'R15 manual impact mutation must fail closed on incomplete consumer fan-out.' );
f06r20_ok( false !== strpos( $integrations, 'outbox_stale_recovery_write_failed' ) && false !== strpos( $integrations, 'outbox_failure_transition_write_failed' ), 'R15 outbox recovery/failure state writes must be checked.' );


// R16 — Knowledge Watchlists must produce a File 19-owned delivery event contract.
$watch = f06r20_read( $root . '/homeopathy-encyclopedia/includes/class-he-v242-watchlist.php' );
f06r20_ok( false !== strpos( $watch, 'KnowledgeWatchTriggered.v1' ) && false !== strpos( $watch, 'sabri_notification_event_catalog' ), 'R16 watchlists must publish the governed KnowledgeWatchTriggered.v1 File 19 event contract.' );
f06r20_ok( false !== strpos( $watch, "add_action( 'he_v2_event'" ) && false !== strpos( $watch, "'audience_query' => array( __CLASS__, 'audience' )" ), 'R16 watch relations must be connected to knowledge-change events without creating a duplicate notification backend.' );
f06r20_ok( false !== strpos( $watch, 'HE_V2_Auth::membership_allowed( $uid )' ), 'R16 watch delivery audience must fail closed for suspended/ineligible accounts.' );


// R17 — privacy erasure must not advance beyond unverified governance-meta writes.
$gov_privacy = f06r20_read( $root . '/homeopathy-encyclopedia/includes/class-he-v241-governance-privacy.php' );
f06r20_ok( false !== strpos( $gov_privacy, 'privacy_editor_scope_erasure_failed' ) && false !== strpos( $gov_privacy, 'privacy_reviewer_assignment_erasure_failed' ), 'R17 governance privacy erasure must surface failed metadata writes.' );
f06r20_ok( false !== strpos( $gov_privacy, '$processed_cursor' ) && false !== strpos( $gov_privacy, 'never advance past a failed assignment write' ), 'R17 privacy erasure cursor must not skip unverified reviewer-assignment mutations.' );


// R18 — research composer compensation must suppress the normal canonical hard-delete lifecycle.
$research_authoring = f06r20_read( $root . '/homeopathy-encyclopedia/includes/class-he-v242-research-authoring.php' );
f06r20_ok( false !== strpos( $research_authoring, 'remove_filter( \'pre_delete_post\', $domain_pre_delete, 10 )' ), 'R18 research rollback must suppress the canonical pre-delete lifecycle inside its compensation transaction.' );
f06r20_ok( false !== strpos( $research_authoring, 'remove_action( \'deleted_post\', $domain_deleted, 10 )' ), 'R18 research rollback must suppress the normal post-delete lifecycle event during compensation.' );
f06r20_ok( false !== strpos( $research_authoring, 'add_filter( \'pre_delete_post\', $domain_pre_delete, 10, 3 )' ) && false !== strpos( $research_authoring, 'add_action( \'deleted_post\', $domain_deleted, 10, 2 )' ), 'R18 research rollback must restore canonical deletion hooks after compensation.' );


// R19 — public UI instances/accessibility and extreme-text reflow.
$public_ui = f06r20_read( $root . '/homeopathy-encyclopedia/includes/class-he-v2-public.php' );
$public_css = f06r20_read( $root . '/homeopathy-encyclopedia/assets/css/encyclopedia-v2.css' );
f06r20_ok( false !== strpos( $public_ui, "wp_unique_id( 'he-v2-results-' )" ) && false !== strpos( $public_ui, 'aria-controls="<?php echo esc_attr( $results_id ); ?>"' ) && false !== strpos( $public_ui, 'id="<?php echo esc_attr( $results_id ); ?>"' ), 'R19 repeated encyclopedia shortcodes must not emit duplicate result IDs or broken ARIA references.' );
f06r20_ok( false !== strpos( $public_css, 'overflow-wrap:anywhere' ), 'R19 public knowledge surfaces must reflow long multilingual/unbroken text at narrow/high-zoom layouts.' );

if($fail){fwrite(STDERR,"File 06 twentieth-review regressions FAILED:\n- ".implode("\n- ",$fail)."\n");exit(1);}echo "File 06 twentieth-review regressions through current round: PASS\n";
