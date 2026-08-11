from pathlib import Path
p=Path('homeopathy-encyclopedia/includes/class-he-v22-integrity.php')
s=p.read_text()
old="""\t\t$actions = HE_V2_Schema::table( 'integrity_actions' );
\t\t$concepts = HE_V2_Schema::table( 'concepts' );
\t\t$wpdb->query( 'START TRANSACTION' );
\t\ttry {
"""
new="""\t\t$actions = HE_V2_Schema::table( 'integrity_actions' );
\t\t$concepts = HE_V2_Schema::table( 'concepts' );
\t\tif ( false === $wpdb->query( 'START TRANSACTION' ) ) {
\t\t\tHE_V2_Schema::record_runtime_failure( 'entry_integrity_transaction_start_failed', 'File 06 could not start the entry-integrity apply transaction.' );
\t\t\treturn self::finish( $reservation, new WP_Error( 'he_integrity_apply_failed', __( 'The accepted integrity action could not start safely.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) ) );
\t\t}
\t\ttry {
"""
if old not in s: raise SystemExit('R5 start target not found')
s=s.replace(old,new,1)
old="""\t\t\t$wpdb->query( 'COMMIT' );
\t\t\tHE_V22_Governance::reindex_concept_secure( (int) $concept['id'] );
"""
new="""\t\t\tif ( false === $wpdb->query( 'COMMIT' ) ) {
\t\t\t\tthrow new RuntimeException( 'integrity-commit-failed' );
\t\t\t}
\t\t\tHE_V22_Governance::reindex_concept_secure( (int) $concept['id'] );
"""
if old not in s: raise SystemExit('R5 commit target not found')
s=s.replace(old,new,1)
# add explicit 503 mapping for transaction certainty failures
old="""\t\t} catch ( Throwable $error ) {
\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\t$code = 'unsupported-action' === $error->getMessage() ? 'he_integrity_action_unsupported' : 'he_integrity_apply_conflict';
\t\t\t$status = 'unsupported-action' === $error->getMessage() ? 422 : 409;
\t\t\treturn self::finish( $reservation, new WP_Error( $code, __( 'The accepted integrity action could not be applied safely to the current record.', 'homeopathy-encyclopedia' ), array( 'status' => $status ) ) );
\t\t}
"""
new="""\t\t} catch ( Throwable $error ) {
\t\t\t$wpdb->query( 'ROLLBACK' );
\t\t\t$message = $error->getMessage();
\t\t\tif ( 'integrity-commit-failed' === $message ) {
\t\t\t\tHE_V2_Schema::record_runtime_failure( 'entry_integrity_commit_failed', 'File 06 could not confirm the entry-integrity transaction commit.' );
\t\t\t\treturn self::finish( $reservation, new WP_Error( 'he_integrity_apply_failed', __( 'The integrity outcome could not be confirmed safely. Reload the current state before retrying.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) ) );
\t\t\t}
\t\t\t$code = 'unsupported-action' === $message ? 'he_integrity_action_unsupported' : 'he_integrity_apply_conflict';
\t\t\t$status = 'unsupported-action' === $message ? 422 : 409;
\t\t\treturn self::finish( $reservation, new WP_Error( $code, __( 'The accepted integrity action could not be applied safely to the current record.', 'homeopathy-encyclopedia' ), array( 'status' => $status ) ) );
\t\t}
"""
if old not in s: raise SystemExit('R5 catch target not found')
s=s.replace(old,new,1)
p.write_text(s)

t=Path('tests/v2410-eleventh-ten-round-regressions.php')
ts=t.read_text(); marker='/*__V2410_MORE__*/'
add="""v2410_ok(false!==strpos($integrity,"entry_integrity_transaction_start_failed") && false!==strpos($integrity,"integrity-commit-failed") && false!==strpos($integrity,"entry_integrity_commit_failed"),'R5 entry integrity transaction start/commit failures are not fail-closed');\n"""
if marker not in ts: raise SystemExit('test marker missing')
ts=ts.replace(marker,add+marker,1); t.write_text(ts)
