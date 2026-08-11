from pathlib import Path

# API mutation finalization must be fail-closed if the idempotency response cannot be fenced/persisted.
p=Path('homeopathy-encyclopedia/includes/class-he-v2-api.php')
s=p.read_text()
old="""\t\tif ( is_wp_error( $result ) ) {
\t\t\t$data = $result->get_error_data();
\t\t\t$code = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;
\t\t\t$body = array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message(), 'data' => $data );
\t\t\tHE_V2_Domain::idempotent_finish( $reservation['id'], $code, $body );
\t\t\treturn $result;
\t\t}
\t\t$body = array( 'data' => $result, 'trace_id' => HE_V2_Domain::trace_id() );
\t\tHE_V2_Domain::idempotent_finish( $reservation['id'], $success_code, $body );
\t\treturn new WP_REST_Response( $body, $success_code );
"""
new="""\t\tif ( is_wp_error( $result ) ) {
\t\t\t$data = $result->get_error_data();
\t\t\t$code = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;
\t\t\t$body = array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message(), 'data' => $data );
\t\t\t$finished = HE_V2_Domain::idempotent_finish( $reservation['id'], $code, $body );
\t\t\tif ( ! $finished ) {
\t\t\t\treturn new WP_Error( 'he_idempotency_finalize_failed', __( 'The request outcome could not be recorded safely. Do not retry with a new key until the current state is reloaded.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
\t\t\t}
\t\t\treturn $result;
\t\t}
\t\t$body = array( 'data' => $result, 'trace_id' => HE_V2_Domain::trace_id() );
\t\t$finished = HE_V2_Domain::idempotent_finish( $reservation['id'], $success_code, $body );
\t\tif ( ! $finished ) {
\t\t\treturn new WP_Error( 'he_idempotency_finalize_failed', __( 'The request may have completed, but its retry record could not be finalized safely. Reload the current state before retrying.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
\t\t}
\t\treturn new WP_REST_Response( $body, $success_code );
"""
if old not in s: raise SystemExit('R3 API mutation_response marker missing')
p.write_text(s.replace(old,new,1))

# V22 mutation helper has the same finalization obligation.
p=Path('homeopathy-encyclopedia/includes/class-he-v22-governance.php')
s=p.read_text()
old="""\t\tif ( is_wp_error( $result ) ) {
\t\t\t$data = $result->get_error_data();
\t\t\t$code = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;
\t\t\tHE_V2_Domain::idempotent_finish( $reservation['id'], $code, array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ) );
\t\t\treturn $result;
\t\t}
\t\tHE_V2_Domain::idempotent_finish( $reservation['id'], $success_code, $result );
\t\treturn new WP_REST_Response( $result, $success_code );
"""
new="""\t\tif ( is_wp_error( $result ) ) {
\t\t\t$data = $result->get_error_data();
\t\t\t$code = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;
\t\t\t$finished = HE_V2_Domain::idempotent_finish( $reservation['id'], $code, array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ) );
\t\t\tif ( ! $finished ) {
\t\t\t\treturn new WP_Error( 'he_idempotency_finalize_failed', __( 'The request outcome could not be recorded safely. Reload the current state before retrying.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
\t\t\t}
\t\t\treturn $result;
\t\t}
\t\t$finished = HE_V2_Domain::idempotent_finish( $reservation['id'], $success_code, $result );
\t\tif ( ! $finished ) {
\t\t\treturn new WP_Error( 'he_idempotency_finalize_failed', __( 'The request may have completed, but its retry record could not be finalized safely. Reload the current state before retrying.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
\t\t}
\t\treturn new WP_REST_Response( $result, $success_code );
"""
if old not in s: raise SystemExit('R3 V22 mutation_finish marker missing')
p.write_text(s.replace(old,new,1))

# Distinguish a stale/fenced lease from a database execution error and record it.
p=Path('homeopathy-encyclopedia/includes/class-he-v2-domain.php')
s=p.read_text()
old="""\t\tif ( false === $updated ) {
\t\t\tHE_V2_Schema::record_runtime_failure( 'idempotency_finish_failed', 'The reserved File 06 response could not be persisted.' );
\t\t\treturn false;
\t\t}
\t\treturn 1 === (int) $updated;
"""
new="""\t\tif ( false === $updated ) {
\t\t\tHE_V2_Schema::record_runtime_failure( 'idempotency_finish_failed', 'The reserved File 06 response could not be persisted.' );
\t\t\treturn false;
\t\t}
\t\tif ( 1 !== (int) $updated ) {
\t\t\tHE_V2_Schema::record_runtime_failure( 'idempotency_finish_stale_lease', 'A File 06 idempotency reservation was reclaimed or changed before its original worker could finalize the response.' );
\t\t\treturn false;
\t\t}
\t\treturn true;
"""
if old not in s: raise SystemExit('R3 idempotent_finish marker missing')
p.write_text(s.replace(old,new,1))

# Add regression contract.
t=Path('tests/v249-tenth-ten-round-regressions.php')
s=t.read_text(); marker='/*__V249_MORE__*/'
block="""$api=v249_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-api.php');
$gov=v249_read($root.'/homeopathy-encyclopedia/includes/class-he-v22-governance.php');
$domain=v249_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');
v249_ok(substr_count($api,'he_idempotency_finalize_failed')>=2 && substr_count($gov,'he_idempotency_finalize_failed')>=2,'R3 mutation helpers ignore idempotency finalization failure and can report unsafe success');
v249_ok(false!==strpos($domain,'idempotency_finish_stale_lease'),'R3 stale/reclaimed idempotency finalization is not surfaced as an operational failure');"""
if block not in s:
    if marker not in s: raise SystemExit('R3 test marker missing')
    s=s.replace(marker,block+'\n'+marker,1)
t.write_text(s)
