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

WP_CLI::success('File06 disposable WordPress search: ' . $checks . ' checks passed; not Hostinger staging/live.');
