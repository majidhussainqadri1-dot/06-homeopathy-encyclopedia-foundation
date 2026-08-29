<?php
/** File 06 v2.4.20 twenty-first fresh twenty-round regression invariants. */
$root = dirname( __DIR__ );
$api = file_get_contents( $root . '/homeopathy-encyclopedia/includes/class-he-v2-api.php' );
if ( false === $api ) { fwrite( STDERR, "Unable to read core API source.\n" ); exit( 1 ); }
$needles = array(
    "if ( ! is_wp_error( \$reservation ) && ! \$row )",
    "return \$this->mutation_response( \$reservation, new WP_Error( 'he_not_found'",
);
foreach ( $needles as $needle ) {
    if ( false === strpos( $api, $needle ) ) {
        fwrite( STDERR, "Missing R2 callback TOCTOU fail-closed invariant: {$needle}\n" );
        exit( 1 );
    }
}
if ( substr_count( $api, "if ( ! is_wp_error( \$reservation ) && ! \$row )" ) < 4 ) {
    fwrite( STDERR, "R2 requires fail-closed re-resolution guards on all core object mutation callbacks.\n" );
    exit( 1 );
}
$domain = file_get_contents( $root . '/homeopathy-encyclopedia/includes/class-he-v2-domain.php' );
if ( false === $domain ) { fwrite( STDERR, "Unable to read domain source.\n" ); exit( 1 ); }
$r3_needles = array(
    '$make_primary = $primary || \'canonical\' === $type;',
    "SELECT @@in_transaction",
    "FOR UPDATE",
    'SAVEPOINT \' . $savepoint',
    'ROLLBACK TO SAVEPOINT \' . $savepoint',
    "alias_atomic_write_failed",
);
foreach ( $r3_needles as $needle ) {
    if ( false === strpos( $domain, $needle ) ) {
        fwrite( STDERR, "Missing R3 atomic canonical-alias invariant: {$needle}\n" );
        exit( 1 );
    }
}
$language_migration = file_get_contents( $root . '/homeopathy-encyclopedia/includes/class-he-v242-language-migration.php' );
if ( false === $language_migration ) { fwrite( STDERR, "Unable to read language migration source.\n" ); exit( 1 ); }
$r9_needles = array(
    "private static \$lock_token = '';",
    "maybe_serialize( \$existing )",
    "hash_equals( (string) \$current['token'], self::\$lock_token )",
    "maybe_serialize( \$current )",
);
foreach ( $r9_needles as $needle ) {
    if ( false === strpos( $language_migration, $needle ) ) {
        fwrite( STDERR, "Missing R9 token-safe language migration lease invariant: {$needle}\n" );
        exit( 1 );
    }
}
echo "File 06 v2.4.20 twenty-first-review regressions through R9: PASS\n";
