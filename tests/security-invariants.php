<?php
/** Deterministic source-level security assertions; no WordPress bootstrap required. */
$root = dirname( __DIR__ ) . '/homeopathy-encyclopedia';
$files = array(
	'permissions' => file_get_contents( $root . '/includes/class-he-permissions.php' ),
	'content' => file_get_contents( $root . '/includes/class-he-content.php' ),
	'database' => file_get_contents( $root . '/includes/class-he-database.php' ),
	'publishing' => file_get_contents( $root . '/includes/class-he-publishing.php' ),
	'admin' => file_get_contents( $root . '/includes/class-he-admin.php' ),
	'catalog' => file_get_contents( $root . '/includes/class-he-catalog.php' ),
	'comments' => file_get_contents( $root . '/includes/class-he-comments.php' ),
	'interactions' => file_get_contents( $root . '/includes/class-he-interactions.php' ),
	'privacy' => file_get_contents( $root . '/includes/class-he-privacy.php' ),
);
$failures = array();
$require = static function( $condition, $message ) use ( &$failures ) { if ( ! $condition ) { $failures[] = $message; } };
$require( false !== strpos( $files['permissions'], 'SLC_Permissions::is_verified_doctor' ), 'File 00/File 05 doctor authority missing.' );
$require( false === strpos( $files['permissions'], 'sabri_doctor_verified' ), 'Role-only doctor fallback found.' );
$require( false !== strpos( $files['content'], "'capabilities'     => HE_Permissions::post_type_caps()" ), 'Dedicated post capabilities missing.' );
$require( false !== strpos( $files['database'], 'ON DUPLICATE KEY UPDATE view_count=view_count+1' ), 'Atomic view counter missing.' );
$require( false !== strpos( $files['database'], 'he_search_index' ), 'Structured search index missing.' );
$require( false !== strpos( $files['publishing'], 'getimagesize' ) && false !== strpos( $files['publishing'], '24000000' ), 'Image dimension/pixel guard missing.' );
$require( false !== strpos( $files['publishing'], "! trim( \$excerpt )" ), 'Server-side summary requirement missing.' );
$require( false !== strpos( $files['admin'], 'can_review_entry' ) && false !== strpos( $files['admin'], 'claim_version' ), 'Self-review or optimistic locking control missing.' );
$require( false !== strpos( $files['admin'], 'resolution_note' ) && false !== strpos( $files['admin'], 'disposition' ), 'Versioned feedback resolution missing.' );
$require( false === strpos( $files['comments'], 'option_comment_registration' ), 'Site-wide comment mutation found.' );
$require( false === strpos( $files['catalog'], '<main' ) && false === strpos( $files['catalog'], 'navigation(' ), 'File 20 shell bypass found.' );
$require( false !== strpos( $files['catalog'], 'he_page' ) && false !== strpos( $files['catalog'], 'HE_Database::catalog' ), 'True pagination path missing.' );
$require( false !== strpos( $files['interactions'], 'he_bookmark_states' ) && false !== strpos( $files['interactions'], 'desired' ), 'Cache-safe idempotent bookmarks missing.' );
$require( false !== strpos( $files['privacy'], "'post_status' => 'any'" ) && false !== strpos( $files['privacy'], 'he_audit_log' ), 'Privacy coverage for non-public entries/audit missing.' );
if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}
echo "Security invariants passed.\n";
