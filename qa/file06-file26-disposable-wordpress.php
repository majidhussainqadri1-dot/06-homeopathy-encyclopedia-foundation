<?php
/**
 * Disposable WordPress 7.0.1 / MariaDB 11.4 integration check.
 * Runs in CI via wp eval-file after File00, File06 and File26 activation.
 * Does NOT use live content, and does NOT approve public search cutover.
 */
defined('ABSPATH') || exit;
global $wpdb;
$failures = [];
$passes = 0;
$check = static function($ok,$label) use (&$passes,&$failures) {
    if ($ok) { $passes++; echo "PASS File06/File26 real WP: {$label}\n"; }
    else { $failures[]=$label; fwrite(STDERR,"FAIL File06/File26 real WP: {$label}\n"); }
};
$check(class_exists('HE_File26_Connector'), 'Proposed owner class loaded');
$health=HE_File26_Connector::health();
$check(($health['approval_status_flags_checked']??false)===true
    &&($health['review_coverage_attested']??null)===false
    &&!isset($health['review_and_safety_verified']),
    'Proposed source health distinguishes code-gate enforcement from unverified human review');
$check(function_exists('he_contract_descriptor'), 'File06 runtime contract present');
$check(defined('HE_SCHEMA_VERSION')&&HE_SCHEMA_VERSION===10,'File06 core schema target 10');
$check(class_exists('HE_V2_Schema') && 'active'===HE_V2_Schema::runtime_status(),
       'File06 DB runtime ready (not source-only)');
$check(class_exists('HE_V24_Migration_Safety')&&HE_V24_Migration_Safety::ready(),
       'File06 Future DB schema ready');
$check(class_exists('\\Sabri\\File26\\DB'), 'File26 runtime loaded');
$manifests=apply_filters('sabri_file26_connector_manifests',[]);
$matches=array_values(array_filter((array)$manifests,static function($m) {
    return is_array($m)&&($m['slug']??'')==='file06-public-encyclopedia';
}));
$check(count($matches)===1,'Exactly one owner File06 manifest from connector filter');
if($matches){
    $m=$matches[0];
    $check($m['status']==='proposed','Owner connector stays proposed');
    $check(($m['deletion_semantics']??'')==='owner_integrity_tombstone_reconciliation_pending',
           'Tombstone and reconciliation remain explicitly pending');
    $check(is_callable($m['list_batch']??null)&&is_callable($m['can_view']??null),
           'Source/list and authorization callables registered');
}
if(class_exists('\\Sabri\\File26\\DB')){
    $table=\Sabri\File26\DB::table('connectors');
    $row=$wpdb->get_row($wpdb->prepare(
        "SELECT owner_file,status,contract_version FROM {$table} WHERE slug=%s",
        'file06-public-encyclopedia'
    ),ARRAY_A);
    $check(is_array($row)&&$row['owner_file']==='File 06',
           'File26 MariaDB connector registration persisted');
    $check(is_array($row)&&$row['status']==='proposed',
           'File26 MariaDB search activation not silently enabled');
}
if(class_exists('HE_File26_Connector')){
    $r=HE_File26_Connector::list_batch('',50,[]);
    $check(is_array($r)&&isset($r['items'],$r['done']),
           'Real owner batch can read existing fresh canonical schema');
$check(is_array(HE_File26_Connector::list_batch('',50,[
        'connector'=>'file06-public-encyclopedia'
    ])), 'Actual File26 worker connector scope returns a complete owner snapshot');
$check(is_wp_error(HE_File26_Connector::list_batch('',50,[
        'connector'=>'unrelated-owner'
    ])), 'Foreign connector scope is rejected');
$check(is_wp_error(HE_File26_Connector::list_batch('',50,[
        'connector'=>'file06-public-encyclopedia','type'=>'research'
    ])), 'Unimplemented partial scope fails closed');
    $check(HE_File26_Connector::can_view([
        'connector_slug'=>'file06-public-encyclopedia',
        'object_id'=>'00000000-0000-4000-8000-000000000000',
        'object_version'=>1,'entity_type'=>'encyclopedia_entry',
        'visibility'=>'public','canonical_url'=>home_url('/encyclopedia/'),
        'locale'=>'en-US'
    ],[])===false, 'Unknown canonical object fails closed');
}
// Synthetic fixture only in the disposable CI database. The test deliberately
// seeds eligibility and does NOT assert real editorial/reviewer approval.
if(class_exists('HE_V2_Domain') && class_exists('HE_File26_Connector')) {
    $post_id=wp_insert_post([
        'post_type'=>HE_V2_Domain::ENTRY_TYPE,
        'post_status'=>'draft',
        'post_title'=>'Disposable CI encyclopedia source',
        'post_excerpt'=>'Synthetic owner-approved test metadata; not medical evidence.',
        'post_content'=>'Synthetic text never intended for production.',
        'post_author'=>1
    ],true);
    $check(!is_wp_error($post_id)&&is_numeric($post_id)&&$post_id>0,
        'Synthetic File06 WordPress knowledge post created');
    if(!is_wp_error($post_id) && (int)$post_id>0) {
        $concept=HE_V2_Domain::concept_by_post_id((int)$post_id);
        $check(is_array($concept),'WordPress entry has canonical File06 concept row');
        if(is_array($concept)) {
            $ver=HE_V2_Domain::snapshot_version((int)$concept['id'],
                'Synthetic disposable CI owner metadata','published',1);
            $check($ver>0,'Immutable File06 version was created');
            if($ver>0) {
                $table=HE_V2_Schema::table('concepts');
                $saved=$wpdb->update($table,[
                    'current_version'=>(int)$ver,'status'=>'published',
                    'review_status'=>'approved','safety_status'=>'approved',
                    'row_version'=>2
                ],['id'=>(int)$concept['id']]);
                $check($saved!==false,'Synthetic review eligibility fixture saved');
                $publish=wp_update_post(['ID'=>(int)$post_id,'post_status'=>'publish'],true);
                $check(!is_wp_error($publish)&&get_post_status((int)$post_id)==='publish',
                    'Synthetic approved WordPress post published');
                $owner=HE_V2_Domain::concept_by_post_id((int)$post_id);
                $projected=HE_File26_Connector::fetch_object('encyclopedia_entry',$concept['public_id']);
                $check(is_array($projected),
                    'File26 source projects an eligible canonical File06 document');
                if(is_array($projected)) {
                    $check(HE_File26_Connector::can_view($projected,[]),
                        'File26 click-time authorization accepts current owner version');
                    $check(!HE_File26_Connector::can_view(array_merge($projected,[
                        'object_version'=>(string)$projected['object_version'].'spoof'
                    ]),[]), 'String-suffixed indexed object version rejected');
                    $check(!HE_File26_Connector::can_view(array_merge($projected,[
                        'object_version'=>((float)$projected['object_version'])+.5
                    ]),[]), 'Fractional indexed object version rejected');
                    $scan=HE_File26_Connector::list_batch('',50,[]);
                    $hits=is_array($scan)?array_values(array_filter(
                        $scan['items']??[],static function($doc) use($concept) {
                            return ($doc['object_id']??'')===$concept['public_id'];
                        }
                    )):[];
                    $check(count($hits)===1,
                        'Real owner batch contains the single synthetic public concept');
                    $check(!isset($projected['body'])&&!isset($projected['patient']),
                        'Public search projection never contains raw clinical/private body');
                    $check(!HE_File26_Connector::can_view(array_merge($projected,[
                        'canonical_url'=>'https://attacker.example/canonical/'
                    ]),[]),
                        'Off-origin canonical search destination rejected at click time');
                    $check(!HE_File26_Connector::can_view(array_merge($projected,[
                        'locale'=>'ar'
                    ]),[]),
                        'Mismatched locale is rejected at click time');
                    $check(!HE_File26_Connector::can_view(array_merge($projected,[
                        'entity_type'=>'concept'
                    ]),[]),
                        'Mismatched entity type is rejected at click time');
                    // Adversarial source owner check: a raw WP edit must not
                    // change an approved article while retaining the original
                    // immutable reviewed version and public status.
                    $before_version=(int)$wpdb->get_var($wpdb->prepare(
                        "SELECT current_version FROM {$table} WHERE id=%d",
                        (int)$concept['id']
                    ));
                    $unchecked=wp_update_post([
                        'ID'=>(int)$post_id,
                        'post_title'=>'UNREVIEWED new public title',
                        'post_content'=>'UNREVIEWED replacement body'
                    ],true);
                    $after_version=(int)$wpdb->get_var($wpdb->prepare(
                        "SELECT current_version FROM {$table} WHERE id=%d",
                        (int)$concept['id']
                    ));
                    $check(is_wp_error($unchecked)||get_post_status((int)$post_id)!=='publish'
                        ||$after_version!==$before_version,
                        'Unreviewed direct content update cannot retain old approval and public state');
                    $restored=wp_update_post([
                        'ID'=>(int)$post_id,
                        'post_title'=>'Disposable CI encyclopedia source',
                        'post_excerpt'=>'Synthetic owner-approved test metadata; not medical evidence.',
                        'post_content'=>'Synthetic text never intended for production.',
                        'post_status'=>'publish'
                    ],true);
                    $check(!is_wp_error($restored)&&get_post_status((int)$post_id)==='publish',
                        'Restoring exact immutable approved fields permits safe republication');
                    $original_slug=get_post_field('post_name',(int)$post_id);
                    $slug_edit=wp_update_post([
                        'ID'=>(int)$post_id,
                        'post_name'=>'unreviewed-canonical-url-rewrite'
                    ],true);
                    $slug_version=(int)$wpdb->get_var($wpdb->prepare(
                        "SELECT current_version FROM {$table} WHERE id=%d",
                        (int)$concept['id']
                    ));
                    $check(is_wp_error($slug_edit)||get_post_status((int)$post_id)!=='publish'
                        ||$slug_version!==$before_version,
                        'Unreviewed direct canonical slug mutation cannot retain public approval');
                    $changed=$wpdb->update($table,[
                        'status'=>'retracted','row_version'=>3
                    ],['id'=>(int)$concept['id']]);
                    $check($changed!==false,'Synthetic retraction persisted');
                    $check(!HE_File26_Connector::can_view($projected,[]),
                        'File26 excludes retracted owner version on next view');
                }
            }
        }
    }
}
if($failures){
    fwrite(STDERR,count($failures)." File06/File26 real WP checks failed.\n");
    exit(1);
}
echo "File06 File26 real WordPress/MariaDB: {$passes} checks PASS; search remains unapproved and live unverified.\n";
