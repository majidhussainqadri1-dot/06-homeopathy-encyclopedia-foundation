<?php
/** Baseline regression invariants for File 06 v2.3. */
$root = dirname( __DIR__ );
$plugin = $root . '/homeopathy-encyclopedia';
$failures = array();
function f06_reg_assert( $condition, $message ) { global $failures; if ( ! $condition ) { $failures[] = $message; } }
function f06_reg_read( $path ) { $data = file_get_contents( $path ); if ( false === $data ) { throw new RuntimeException( 'Cannot read ' . $path ); } return $data; }

$bootstrap = f06_reg_read( $plugin . '/homeopathy-encyclopedia.php' );
$auth = f06_reg_read( $plugin . '/includes/class-he-v2-auth.php' );
$schema = f06_reg_read( $plugin . '/includes/class-he-v2-schema.php' );
$domain = f06_reg_read( $plugin . '/includes/class-he-v2-domain.php' );
$governance = f06_reg_read( $plugin . '/includes/class-he-v22-governance.php' );
$integrity = f06_reg_read( $plugin . '/includes/class-he-v22-integrity.php' );
$schedule = f06_reg_read( $plugin . '/includes/class-he-v22-schedule.php' );
$search = f06_reg_read( $plugin . '/includes/class-he-v22-search.php' );
$type_schemas = f06_reg_read( $plugin . '/includes/class-he-v22-type-schemas.php' );
$research = f06_reg_read( $plugin . '/includes/class-he-v22-research-guard.php' );
$consumers = f06_reg_read( $plugin . '/includes/class-he-v22-consumers.php' );
$operations = f06_reg_read( $plugin . '/includes/class-he-v22-operations.php' );
$future = f06_reg_read( $plugin . '/includes/class-he-v23-future-intelligence.php' );
$privacy = f06_reg_read( $plugin . '/includes/class-he-v2-privacy.php' );
$css = f06_reg_read( $plugin . '/assets/css/encyclopedia-v2.css' );
$all = implode( "\n", array( $bootstrap,$auth,$schema,$domain,$governance,$integrity,$schedule,$search,$type_schemas,$research,$consumers,$operations,$future,$privacy ) );

f06_reg_assert( false !== strpos( $bootstrap, "define( 'HE_VERSION', '2.3.0' )" ), 'Version mismatch.' );
f06_reg_assert( false !== strpos( $bootstrap, "define( 'HE_SCHEMA_VERSION', 9 )" ), 'Schema mismatch.' );
f06_reg_assert( false !== strpos( $bootstrap, "HE_CONTRACT_VERSION', '2.3" ), 'Contract mismatch.' );
f06_reg_assert( false !== strpos( $bootstrap, "'staging_accepted' => false" ) && false !== strpos( $bootstrap, "'live_deployed' => false" ), 'Repository truth is incorrectly promoted to staging/live.' );
f06_reg_assert( false !== strpos( $auth, 'SMC_Contracts::assertions' ) && false !== strpos( $auth, 'he_identity_provider_unavailable' ), 'File 00 fail-closed authority regressed.' );
f06_reg_assert( false === strpos( $auth, "return (bool) user_can( $user_id, 'manage_options' )" ), 'Legacy founder bypass returned.' );
f06_reg_assert( false !== strpos( $governance, 'migration_quarantine' ) && false !== strpos( $governance, 'add_option( self::LOCK_OPTION' ), 'Migration safety regressed.' );
f06_reg_assert( false !== strpos( $integrity, 'he_integrity_acceptance_required' ) && false !== strpos( $integrity, 'START TRANSACTION' ), 'Integrity workflow regressed.' );
f06_reg_assert( false !== strpos( $schedule, 'content-or-review-changed-before-publication' ), 'Scheduled publication revalidation regressed.' );
f06_reg_assert( false !== strpos( $search, 'spelling-recovery' ) && false !== strpos( $search, 'similar_text' ), 'Search recovery regressed.' );
f06_reg_assert( 16 === substr_count( $type_schemas, "'body_system_required' =>" ), 'Sixteen type schemas are not preserved.' );
f06_reg_assert( false !== strpos( $research, 'he_dataset_private_by_default' ) && false !== strpos( $research, 'کامیاب کیس' ), 'Research/case governance regressed.' );
foreach ( array( 'file-05','file-12','file-15','file-16','file-21','file-26' ) as $consumer ) { f06_reg_assert( false !== strpos( $consumers, "'" . $consumer . "'" ), 'Missing consumer: ' . $consumer ); }
f06_reg_assert( false !== strpos( $operations, "status='dead-letter'" ), 'Operational dead-letter evidence regressed.' );
f06_reg_assert( false !== strpos( $privacy, 'wp_privacy_personal_data_exporters' ) && false !== strpos( $privacy, 'wp_privacy_personal_data_erasers' ), 'Privacy lifecycle regressed.' );
f06_reg_assert( false !== strpos( $css, ':focus-visible' ) && false !== strpos( $css, 'prefers-reduced-motion' ) && false !== strpos( $css, '[dir="rtl"]' ), 'Accessibility/RTL regressed.' );
f06_reg_assert( false !== strpos( $future, 'F06-FUT-001' ) && false !== strpos( $future, 'F06-FUT-018' ), 'Future intelligence is not loaded end-to-end.' );
f06_reg_assert( false === preg_match( '/\b(eval|create_function)\s*\(/i', $all ), 'Unsafe dynamic execution found.' );
f06_reg_assert( false === strpos( $all, 'wp_remote_get( $_' ), 'Unvalidated outbound request found.' );

if ( $failures ) { fwrite( STDERR, "File 06 v2.3 regression failures:\n- " . implode( "\n- ", $failures ) . "\n" ); exit( 1 ); }
echo "File 06 v2.3 baseline regression invariants passed.\n";
