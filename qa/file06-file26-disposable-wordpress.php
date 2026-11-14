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
    $table=\\Sabri\\File26\\DB::table('connectors');
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
    $check(HE_File26_Connector::can_view([
        'connector_slug'=>'file06-public-encyclopedia',
        'object_id'=>'00000000-0000-4000-8000-000000000000',
        'object_version'=>1,'entity_type'=>'encyclopedia_entry',
        'visibility'=>'public','canonical_url'=>home_url('/encyclopedia/'),
        'locale'=>'en-US'
    ],[])===false, 'Unknown canonical object fails closed');
}
if($failures){
    fwrite(STDERR,count($failures)." File06/File26 real WP checks failed.\n");
    exit(1);
}
echo "File06 File26 real WordPress/MariaDB: {$passes} checks PASS; search remains unapproved and live unverified.\n";
