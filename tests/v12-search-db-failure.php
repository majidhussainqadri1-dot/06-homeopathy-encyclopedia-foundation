<?php
/**
 * File 06 V12.3 search: deterministic DB-error and degraded-mode negative cases.
 * Source unit harness only. This is NOT a Hostinger staging or live test.
 */
declare(strict_types=1);
define('ABSPATH', __DIR__);
define('ARRAY_A', 'ARRAY_A');
define('HE_CONTRACT_VERSION', '2.4.20');

class WP_Error {
    private $code;
    private $data;
    public function __construct($code, $message = '', $data = array()) {
        $this->code = $code;
        $this->data = $data;
    }
    public function get_error_code() { return $this->code; }
    public function get_error_data() { return $this->data; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function __($text, $domain = '') { return $text; }
class HE_V2_API { const NS = 'sabri/v2/file-06'; }
class HE_V2_Schema {
    public static function runtime_status() { return $GLOBALS['schema_state']; }
    public static function table($name) { return 'wp_he_' . $name; }
}
class HE_V2_Domain {
    public static function normalize($value) { return strtolower(trim((string) $value)); }
    public static function public_dto($row) { return $row['dto'] ?? null; }
}
class WP_REST_Server { const READABLE = 'GET'; }
class WP_REST_Request {
    private $route;
    private $params;
    public function __construct($route, $params = array()) {
        $this->route = $route; $this->params = $params;
    }
    public function get_method() { return 'GET'; }
    public function get_route() { return $this->route; }
    public function get_params() { return $this->params; }
    public function get_param($key) { return $this->params[$key] ?? null; }
}
class FakeRestResponse {
    public $headers = array();
    public $data;
    public function __construct($data) { $this->data = $data; }
    public function header($key, $value) { $this->headers[$key] = $value; }
}
function rest_ensure_response($value) {
    return is_wp_error($value) ? $value : new FakeRestResponse($value);
}
function add_filter($name, $callback, $priority = 10, $accepted = 1) { return true; }
function absint($number) { return abs((int) $number); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $value)); }
function sanitize_text_field($value) { return strip_tags((string) $value); }
function get_permalink($id) { return 'https://example.org/encyclopedia/entry/' . (int) $id; }

class SearchDB {
    public $last_error = '';
    public $queries = 0;
    public $mode = 'failure';
    public function prepare($sql, ...$params) { return $sql; }
    public function esc_like($text) { return addcslashes($text, '%_\\'); }
    public function get_results($sql, $format = ARRAY_A) {
        $this->queries++;
        if ($this->mode === 'failure') {
            $this->last_error = 'private_database_details';
            return null;
        }
        if ($this->mode === 'stale_error') {
            $this->last_error = 'private_query_failure';
            return array();
        }
        if ($this->mode === 'alias_failure' && $this->queries >= 2) {
            $this->last_error = 'private_alias_failure';
            return null;
        }
        if ($this->mode === 'autocomplete_fallback_failure' && $this->queries >= 2) {
            $this->last_error = 'private_autocomplete_alias_failure';
            return null;
        }
        $this->last_error = '';
        if ($this->mode === 'one_result' && strpos($sql, 'SELECT DISTINCT c.*') !== false) {
            return array(array('id' => 7, 'dto' => array(
                'id' => 'public-7', 'title' => 'Reviewed', 'summary' => 'Source',
                'type' => 'remedy', 'body_system' => 'general', 'language' => 'ur',
                'canonical_url' => 'https://example.org/encyclopedia/entry/7',
                'version' => 2, 'safety_status' => 'approved',
                'freshness' => array('updated_at' => '2026-11-14T00:00:00Z')
            )));
        }
        return array();
    }
}
function require_check($condition, $message) {
    $GLOBALS['check_count']++;
    if (!$condition) {
        fwrite(STDERR, "FAIL search DB recovery: $message\n");
        exit(1);
    }
}
function assert_degraded($result, $label) {
    require_check(is_wp_error($result), "$label returns a proper WordPress error");
    require_check($result->get_error_code() === 'he_knowledge_search_unavailable',
        "$label stable, private-detail-free error code");
    require_check(($result->get_error_data()['status'] ?? null) === 503,
        "$label produces 503 rather than misleading no-match or fatal");
}

$GLOBALS['schema_state'] = 'active';
$GLOBALS['check_count'] = 0;
$GLOBALS['wpdb'] = new SearchDB();
require dirname(__DIR__) . '/homeopathy-encyclopedia/includes/class-he-v22-search.php';

assert_degraded(HE_V22_Search::search(array('q' => 'test')), 'Main query DB failure');
require_check($GLOBALS['wpdb']->queries === 1,
    'Do not attempt spelling fallback after failed primary DB query');

$GLOBALS['wpdb'] = new SearchDB();
$GLOBALS['wpdb']->mode = 'stale_error';
assert_degraded(HE_V22_Search::search(array()), 'SQL error with empty array');

$GLOBALS['wpdb'] = new SearchDB();
$GLOBALS['wpdb']->mode = 'alias_failure';
assert_degraded(HE_V22_Search::search(array('q' => 'test')), 'Spelling candidate query failure');

$GLOBALS['wpdb'] = new SearchDB();
assert_degraded(HE_V22_Search::autocomplete('abcd'), 'Autocomplete primary query failure');
require_check($GLOBALS['wpdb']->queries === 1,
    'Do not issue autocomplete fallback after DB failure');

$GLOBALS['wpdb'] = new SearchDB();
$GLOBALS['wpdb']->mode = 'autocomplete_fallback_failure';
assert_degraded(HE_V22_Search::autocomplete('abcd'), 'Autocomplete fallback query failure');

$GLOBALS['wpdb'] = new SearchDB();
$result = HE_V22_Search::intercept(null, array(), new WP_REST_Request('/sabri/v2/file-06/entries', array('q' => 'test')));
assert_degraded($result, 'REST entries error propagation');
require_check(!($result instanceof FakeRestResponse), 'Never cache a failed entries query as public success');

$GLOBALS['wpdb'] = new SearchDB();
$result = HE_V22_Search::intercept(null, array(), new WP_REST_Request('/sabri/v2/file-06/autocomplete', array('q' => 'test')));
assert_degraded($result, 'REST autocomplete error propagation');
require_check(!($result instanceof FakeRestResponse), 'Never cache a failed autocomplete query as public success');

$GLOBALS['schema_state'] = 'migration_pending';
$GLOBALS['wpdb'] = new SearchDB();
assert_degraded(HE_V22_Search::search(array()), 'Incomplete schema');
require_check($GLOBALS['wpdb']->queries === 0, 'Do not query incomplete schema');
$GLOBALS['schema_state'] = 'active';

$GLOBALS['wpdb'] = new SearchDB();
$GLOBALS['wpdb']->mode = 'empty';
$result = HE_V22_Search::search(array('q' => 'test'));
require_check(is_array($result) && $result['search_mode'] === 'no-match' &&
    $result['items'] === array(), 'Successful empty query is distinguishable from DB error');
require_check(HE_V22_Search::autocomplete('abcd') === array(),
    'Successful empty autocomplete is an empty list');

$GLOBALS['wpdb'] = new SearchDB();
$GLOBALS['wpdb']->mode = 'one_result';
$result = HE_V22_Search::search(array('q' => 'review'));
require_check(is_array($result) && count($result['items']) === 1 &&
    $result['items'][0]['id'] === 'public-7', 'Normal sourced public search still works');

echo "PASS File06 V12.3 fail-closed search DB and REST checks: {$GLOBALS['check_count']} assertions.\n";
