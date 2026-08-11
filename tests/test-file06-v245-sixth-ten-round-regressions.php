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
echo "File 06 v2.4.5 sixth-review regressions: PASS\n";
