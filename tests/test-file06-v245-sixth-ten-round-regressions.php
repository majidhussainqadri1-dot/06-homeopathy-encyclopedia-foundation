<?php
declare(strict_types=1);
function f06_v245_assert($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } }
$api = file_get_contents(__DIR__ . '/../homeopathy-encyclopedia/includes/class-he-v24-future-api.php');
$prov = file_get_contents(__DIR__ . '/../homeopathy-encyclopedia/includes/class-he-v24-public-provenance.php');
foreach (array('/future/public/claims/','/future/public/graph/','/future/public/time-machine/','/future/public/freshness/','/future/public/citations/') as $route) {
    f06_v245_assert(strpos($api, $route) !== false, 'Round 1 canonical public route missing: ' . $route);
}
f06_v245_assert(strpos($api, 'private static function public_read_concept') !== false, 'Round 1 canonical public concept resolver missing');
f06_v245_assert(strpos($prov, 'is_legacy_internal_public_read') !== false, 'Round 1 legacy numeric public-read block missing');
$domain = file_get_contents(__DIR__ . '/../homeopathy-encyclopedia/includes/class-he-v2-domain.php');
f06_v245_assert(strpos($domain, 'canonicalize_idempotency_value') !== false, 'Round 2 stable idempotency fingerprint missing');
f06_v245_assert(strpos($domain, 'DATE_SUB(UTC_TIMESTAMP(), INTERVAL 10 MINUTE)') !== false, 'Round 2 stale idempotency reservation CAS recovery missing');
f06_v245_assert(strpos($domain, "JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES") !== false, 'Round 2 canonical idempotency JSON encoding missing');
$schema = file_get_contents(__DIR__ . '/../homeopathy-encyclopedia/includes/class-he-v24-future-schema.php');
f06_v245_assert(strpos($schema, 'OPTION_MAINTENANCE_LEASE') !== false, 'Round 3 Future maintenance lease missing');
f06_v245_assert(strpos($schema, 'acquire_maintenance_lease') !== false && strpos($schema, 'release_maintenance_lease') !== false, 'Round 3 serialized Future maintenance methods missing');
f06_v245_assert(strpos($schema, 'DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s') !== false, 'Round 3 CAS stale-lease takeover missing');
$runtime = file_get_contents(__DIR__ . '/../homeopathy-encyclopedia/includes/class-he-v241-runtime-guard.php');
f06_v245_assert(strpos($runtime, 'maybe_serialize( $existing )') !== false, 'Round 4 stale core-maintenance lease CAS takeover missing');
f06_v245_assert(strpos($runtime, 'maybe_serialize( $current )') !== false, 'Round 4 core-maintenance lease CAS release missing');
f06_v245_assert(strpos($runtime, 'delete_option( $option )') === false, 'Round 4 unsafe unconditional core lease deletion remains');
$gprivacy = file_get_contents(__DIR__ . '/../homeopathy-encyclopedia/includes/class-he-v241-governance-privacy.php');
f06_v245_assert(strpos($gprivacy, 'assigned_posts_after') !== false, 'Round 5 cursor-based governance privacy scan missing');
f06_v245_assert(strpos($gprivacy, 'he_v241_privacy_assignment_cursor_') !== false, 'Round 5 per-user privacy cursor missing');
f06_v245_assert(strpos($gprivacy, 'assigned_posts_page( 1 )') === false, 'Round 5 stalling first-batch eraser pattern remains');
$gov = file_get_contents(__DIR__ . '/../homeopathy-encyclopedia/includes/class-he-v241-governance.php');
f06_v245_assert(strpos($gov, 'maintenance_serialized') === false, 'Round 6 obsolete outer Future maintenance lease remains');
f06_v245_assert(strpos($gov, 'he_v241_future_maintenance_lease') === false, 'Round 6 split Future maintenance lease ownership remains');
$watch = file_get_contents(__DIR__ . '/../homeopathy-encyclopedia/includes/class-he-v242-watchlist.php');
f06_v245_assert(strpos($watch, 'rest_sanitize_boolean') !== false, 'Round 7 explicit watchlist false normalization missing');
f06_v245_assert(strpos($watch, '! empty( $data') === false, 'Round 7 truthy-string watchlist activation bug remains');
$schema = file_get_contents(__DIR__ . '/../homeopathy-encyclopedia/includes/class-he-v24-future-schema.php');
f06_v245_assert(strpos($schema, '$last_completed_id = $cursor') !== false, 'Round 9 retry-safe retraction cursor missing');
f06_v245_assert(strpos($schema, 'if ( is_wp_error( $data ) ) { break; }') !== false, 'Round 9 provider failure still skips ahead');
f06_v245_assert(strpos($schema, "update_option( 'he_v24_retraction_cursor', (int) end( \$rows )['id'], false )") === false, 'Round 9 retraction cursor still advances to batch end');
$bootstrap = file_get_contents(__DIR__ . '/../homeopathy-encyclopedia/homeopathy-encyclopedia.php');
f06_v245_assert(strpos($bootstrap, ' * Version:') !== false, 'Historical Round 10 plugin header declaration missing');
f06_v245_assert(strpos($bootstrap, "define( 'HE_VERSION',") !== false, 'Historical Round 10 runtime declaration missing');
f06_v245_assert(strpos($bootstrap, "define( 'HE_CONTRACT_VERSION',") !== false, 'Historical Round 10 contract declaration missing');
f06_v245_assert(strpos($bootstrap, "'future_hardening_version'=>") !== false, 'Historical Round 10 hardening declaration missing');
f06_v245_assert(strpos($bootstrap, 'canonical_future_read_routes') !== false, 'Round 10 canonical Future read routes absent from contract descriptor');
$runall = file_get_contents(__DIR__ . '/run-all.sh');
f06_v245_assert(strpos($runall, 'test-file06-v245-sixth-ten-round-regressions.php') !== false, 'Round 10 sixth-cycle suite missing from aggregate gate');
f06_v245_assert(strpos($runall, '-a.zip') !== false && strpos($runall, '-b.zip') !== false, 'Historical deterministic package labels missing');
echo "File 06 v2.4.5 sixth-review regressions: PASS\n";
