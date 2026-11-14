<?php
/**
 * File 06 -> File 26 owner connector: bounded and fail-closed SOURCE checks.
 * This is an isolated test with mock WordPress objects, NOT staging/live proof.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');
define('HE_SCHEMA_VERSION', 10);

class WP_Error {
    public $code;
    public function __construct($code, $message = '') { $this->code = $code; }
}
class HE_V2_Schema {
    public static function runtime_status() { return $GLOBALS['runtime_status']; }
    public static function table($name) { return 'wp_he_' . $name; }
}
class HE_V24_Migration_Safety {
    public static function ready() { return $GLOBALS['migration_ready']; }
}
class HE_V2_Domain {
    public static function concept_by_id($id, $include_private = false) {
        foreach ($GLOBALS['concept_rows'] as $row) {
            if ($row['public_id'] === $id) { return $row; }
        }
        return null;
    }
    public static function is_public_concept($row) {
        return is_array($row) && ($row['status'] ?? '') === 'published'
            && ($row['dto']['review_status'] ?? '') === 'approved'
            && ($row['dto']['safety_status'] ?? '') === 'approved';
    }
    public static function public_dto($row) { return $row['dto']; }
}
class FakeWPDB {
    public $last_error = '';
    public function prepare($query, ...$args) { return vsprintf($query, $args); }
    public function get_results($query, $format) {
        if (!preg_match('/WHERE id>([0-9]+) ORDER BY id ASC LIMIT ([0-9]+)/', $query, $m)) {
            $this->last_error = 'unsupported_fixture_query';
            return null;
        }
        $rows = array_values(array_filter(
            $GLOBALS['concept_rows'],
            static function ($row) use ($m) { return $row['id'] > (int)$m[1]; }
        ));
        usort($rows, static function ($a,$b) { return $a['id'] <=> $b['id']; });
        return array_slice($rows,0,(int)$m[2]);
    }
}
function add_filter($name, $callback, $priority = 10) {
    $GLOBALS['hook_names'][] = $name;
    return true;
}
function add_action($name, $callback, $priority = 10) {
    $GLOBALS['hook_names'][] = $name;
    return true;
}
function esc_url_raw($url) { return (string)$url; }
function wp_http_validate_url($url) { return strpos((string)$url,'https://example.org/') === 0; }
function sanitize_text_field($value) { return trim(strip_tags((string)$value)); }
function wp_strip_all_tags($value, $remove_breaks = false) { return strip_tags((string)$value); }
function sabri_file26_register_connector($manifest) {
    $GLOBALS['direct_register_calls']++;
}
class MockFile26Registry {
    public $registered = 0;
    private $items = [];
    public function register($manifest) {
        $this->registered++;
        $this->items[$manifest['slug']] = $manifest;
    }
    public function get($slug) { return $this->items[$slug] ?? null; }
}
function check_file26($condition, $label) {
    $GLOBALS['checks']++;
    if (!$condition) { fwrite(STDERR, "FAIL: $label\n"); exit(1); }
}
$GLOBALS['checks'] = 0;
$GLOBALS['hook_names'] = [];
$GLOBALS['direct_register_calls'] = 0;
$GLOBALS['runtime_status'] = 'active';
$GLOBALS['migration_ready'] = true;
$GLOBALS['concept_rows'] = [[
    'id' => 1,'row_version' => 3, 'public_id'=>'11111111-1111-4111-8111-111111111111',
    'status'=>'published','merged_into_id'=>0,
    'dto'=>[
        'id'=>'11111111-1111-4111-8111-111111111111',
        'title'=>'Original source-backed remedy',
        'summary'=>'A reviewed educational entry.',
        'body'=>'Sensitive long clinical text must not be exported.',
        'patient'=>'private record',
        'canonical_url'=>'https://example.org/encyclopedia/entry/example/',
        'language'=>'ur','review_status'=>'approved',
        'safety_status'=>'approved','record_status'=>'published',
        'current_version'=>2,'version'=>2
    ]
],[
    'id'=>2,'row_version'=>1,'public_id'=>'22222222-2222-4222-8222-222222222222',
    'status'=>'draft','merged_into_id'=>0,
    'dto'=>[
        'id'=>'22222222-2222-4222-8222-222222222222',
        'title'=>'Hidden draft','canonical_url'=>'https://example.org/draft/',
        'review_status'=>'unreviewed','safety_status'=>'unreviewed',
        'record_status'=>'draft'
    ]
]];
$wpdb = new FakeWPDB();
require dirname(__DIR__).'/homeopathy-encyclopedia/includes/class-he-file26-connector.php';
HE_File26_Connector::register();
check_file26($GLOBALS['direct_register_calls']===0,
    'No eager direct registration ahead of File26 governed boot');
$registry = new MockFile26Registry();
HE_File26_Connector::ready($registry);
HE_File26_Connector::ready($registry);
check_file26($registry->registered===1,
    'Ready event does not duplicate filter-registered connector persistence');

$m=HE_File26_Connector::manifest();
check_file26($m['status']==='proposed' && strpos($m['deletion_semantics'],'pending')!==false,
    'No automatic connector or tombstone activation');
check_file26(in_array('sabri_file26_connector_manifests',$GLOBALS['hook_names'],true),
    'Proposed manifest registration');
check_file26(in_array('sabri_file26_connectors_ready',$GLOBALS['hook_names'],true),
    'Owner callback registration');
check_file26(HE_File26_Connector::health()['event_tombstone_cutover_approved']===false,
    'Health does not falsely approve cutover');
$r=HE_File26_Connector::list_batch('',1,[]);
check_file26(is_array($r) && count($r['items'])===1 && $r['done']===false &&
    $r['next_cursor']==='1','Bounded cursor includes current eligible public object');
$doc=$r['items'][0];
check_file26($doc['object_id']==='11111111-1111-4111-8111-111111111111',
    'Canonical public identifier');
check_file26($doc['object_version']===3 && $doc['visibility']==='public',
    'Monotonic owner row version and public-only scope');
check_file26(!isset($doc['body']) && !isset($doc['patient']) &&
    strpos(json_encode($doc),'Sensitive long clinical')===false,
    'No private or full clinical data in search projection');
check_file26(HE_File26_Connector::can_view($doc,[]),
    'Click-time owner-public visibility recheck');
check_file26(!HE_File26_Connector::can_view(array_merge($doc,['object_version'=>2]),[]),
    'Stale indexed version denied');
check_file26(!HE_File26_Connector::can_view(array_merge($doc,['canonical_url'=>'https://example.org/encyclopedia/stale/']),[]),
    'Stale canonical destination denied at click time');
check_file26(!HE_File26_Connector::can_view(array_merge($doc,['locale'=>'en-US']),[]),
    'Incorrect locale denied at click time');
check_file26(!HE_File26_Connector::can_view(array_merge($doc,['entity_type'=>'concept']),[]),
    'Incorrect entity type denied at click time');
check_file26(!HE_File26_Connector::can_view(array_merge($doc,['visibility'=>'private']),[]),
    'Private visibility denied');
check_file26(HE_File26_Connector::fetch_object('concept','1')===null,
    'Internal numeric identifier denied');
check_file26(HE_File26_Connector::fetch_object('encyclopedia_entry',$doc['object_id'])!==null,
    'Canonical fetch_object available');
check_file26(HE_File26_Connector::list_batch('1',1,[])['done']===true,
    'A page with draft-only content safely finishes without public leakage');
check_file26(HE_File26_Connector::list_batch('not-a-cursor',50,[]) instanceof WP_Error,
    'Invalid cursor rejected');
check_file26(HE_File26_Connector::list_batch('',50,['object_id'=>'1']) instanceof WP_Error,
    'Partial owner scope rejected');
$GLOBALS['concept_rows'][0]['status']='retracted';
check_file26(!HE_File26_Connector::can_view($doc,[]),
    'Retraction invalidates cached public item at view time');
$GLOBALS['concept_rows'][0]['status']='published';
$GLOBALS['concept_rows'][0]['dto']['safety_status']='rejected';
check_file26(!HE_File26_Connector::can_view($doc,[]),
    'Safety revocation invalidates public item');
$GLOBALS['concept_rows'][0]['dto']['safety_status']='approved';
$GLOBALS['concept_rows'][0]['dto']['canonical_url']='http://foreign.invalid/unsafe/';
check_file26(HE_File26_Connector::fetch_object('concept',$doc['object_id'])===null,
    'Unapproved canonical URL denied');
$GLOBALS['migration_ready']=false;
check_file26(HE_File26_Connector::list_batch('',50,[]) instanceof WP_Error,
    'Missing future schema refuses indexing');
check_file26(!HE_File26_Connector::can_view($doc,[]),
    'Missing migration refuses click-time visibility');
check_file26(HE_File26_Connector::health()['state']==='degraded',
    'Migration failure is reported as degraded');
echo 'File06 File26 proposed connector isolated checks: '.$GLOBALS['checks'].' PASS; staging and live unverified.'.PHP_EOL;
