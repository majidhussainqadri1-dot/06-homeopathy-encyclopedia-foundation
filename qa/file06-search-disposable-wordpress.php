<?php
/**
 * Real disposable WordPress 7.0.1/PHP 8.3/MariaDB smoke for File06 public
 * REST search freshness and the direct fallback controllers.
 * Run only inside an isolated synthetic CI installation, never on Hostinger.
 */
if (!defined('ABSPATH')) { exit(1); }
$checks = 0;
$check = static function($ok, $label) use (&$checks) {
    ++$checks;
    if (!$ok) { WP_CLI::error('FAIL File06 search REST: ' . $label); }
    WP_CLI::log('PASS File06 search REST: ' . $label);
};
$header = static function($response) {
    if (!($response instanceof WP_REST_Response)) { return ''; }
    foreach ((array)$response->get_headers() as $name => $value) {
        if (strtolower((string)$name) === 'cache-control') { return (string)$value; }
    }
    return '';
};
$safe = static function($response) use ($header) {
    $cache = $header($response);
    return strpos($cache, 'no-store') !== false && strpos($cache, 'public') === false;
};
$check(class_exists('HE_V22_Search') && class_exists('HE_V2_API'),
    'Canonical search and legacy controller both loaded');
$check(HE_V2_Schema::runtime_status() === 'active',
    'Disposable installed schema is active');

$entries = new WP_REST_Request('GET', '/sabri/v2/file-06/entries');
$entries->set_param('q', 'file06zznosuchentry2026');
$autos = new WP_REST_Request('GET', '/sabri/v2/file-06/autocomplete');
$autos->set_param('q', 'file06zznosuchentry2026');

$rest_entries = rest_do_request($entries);
$check($rest_entries instanceof WP_REST_Response && $rest_entries->get_status() === 200,
    'Real public REST entries endpoint succeeds with empty search');
$check($safe($rest_entries),
    'Actual WordPress public entries response does not cache stale scholarly data');

$rest_autos = rest_do_request($autos);
$check($rest_autos instanceof WP_REST_Response && $rest_autos->get_status() === 200,
    'Real autocomplete endpoint succeeds with empty search');
$check($safe($rest_autos),
    'Actual WordPress autocomplete response is not stale-cacheable');

$api = new HE_V2_API();
$fallback_entries = $api->search($entries);
$check($fallback_entries instanceof WP_REST_Response && $fallback_entries->get_status() === 200,
    'Direct fallback controller returns valid entries response');
$check($safe($fallback_entries),
    'Fallback entries controller independently enforces no-store');

$fallback_autos = $api->autocomplete($autos);
$check($fallback_autos instanceof WP_REST_Response && $fallback_autos->get_status() === 200,
    'Direct fallback controller returns valid autocomplete');
$check($safe($fallback_autos),
    'Fallback autocomplete independently enforces no-store');

// Adversarial synthetic drift: File06's SQL concept flags remain "approved",
// but the real WordPress owner object is still DRAFT. Autocomplete must never
// expose its alias or permalink. This simulates a partial migration/transition.
global $wpdb;
$draft_post = wp_insert_post(array(
    'post_type' => HE_V2_Domain::ENTRY_TYPE,
    'post_status' => 'draft',
    'post_title' => 'CI only: unpublished private knowledge',
    'post_content' => 'Do not expose until independently reviewed.',
    'post_author' => 1
), true);
$check(!is_wp_error($draft_post) && (int)$draft_post > 0,
    'Synthetic unpublished WordPress entry created');
$draft_concept = HE_V2_Domain::concept_by_post_id((int)$draft_post);
$check(is_array($draft_concept), 'Canonical draft concept exists');
$draft_version = HE_V2_Domain::snapshot_version(
    (int)$draft_concept['id'], 'Synthetic unreleased drift fixture', 'published', 1);
$check($draft_version > 0, 'Synthetic immutable version seeded');
$updated = $wpdb->update(HE_V2_Schema::table('concepts'),array(
    'current_version' => (int)$draft_version,
    'status' => 'published',
    'review_status' => 'approved',
    'safety_status' => 'approved',
    'row_version' => 2
),array('id'=>(int)$draft_concept['id']));
$check($updated !== false && get_post_status((int)$draft_post) === 'draft',
    'Owner status drift simulated: canonical approved but WordPress unpublished');
$alias = 'file06draftprivatealias20261115';
$inserted = $wpdb->insert(HE_V2_Schema::table('aliases'),array(
    'concept_id'=>(int)$draft_concept['id'],
    'alias'=>$alias,
    'normalized_alias'=>HE_V2_Domain::normalize($alias),
    'language'=>'en-US',
    'alias_type'=>'synonym',
    'is_primary'=>0,
    'created_by'=>1,
    'created_at'=>gmdate('Y-m-d H:i:s')
));
$check($inserted === 1, 'Synthetic private alias inserted only in isolated DB');
$leaked = HE_V22_Search::autocomplete('file06draftprivatealias',10);
$check(is_array($leaked) && !array_filter($leaked,static function($item) use ($draft_concept) {
    return ($item['id']??'') === $draft_concept['public_id'];
}), 'Autocomplete excludes aliases whose WordPress post is unpublished');

WP_CLI::success('File06 disposable WordPress search: ' . $checks . ' checks passed; not Hostinger staging/live.');
