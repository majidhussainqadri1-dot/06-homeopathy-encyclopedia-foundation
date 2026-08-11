from pathlib import Path
import re

files = {
    'homeopathy-encyclopedia/includes/class-he-v24-future-api.php': ('mutation_finish', '$success_code = 200'),
    'homeopathy-encyclopedia/includes/class-he-v241-governance.php': ('finish_mutation', '$code = 200'),
    'homeopathy-encyclopedia/includes/class-he-v241-research-governance.php': ('finish', '$code=200'),
    'homeopathy-encyclopedia/includes/class-he-v24-future-review-guard.php': ('finish', '$status = 200'),
    'homeopathy-encyclopedia/includes/class-he-v22-integrity.php': ('finish', '$code = 200'),
    'homeopathy-encyclopedia/includes/class-he-v242-watchlist.php': ('finish', '$code = 200'),
    'homeopathy-encyclopedia/includes/class-he-v242-multilingual.php': ('finish', '$success_code = 200'),
}

for name, (fn, defaultarg) in files.items():
    p=Path(name); s=p.read_text()
    if 'he_idempotency_finalize_failed' in s:
        continue
    # Determine success parameter name.
    success = '$success_code' if 'success_code' in defaultarg else ('$status' if '$status' in defaultarg else '$code')
    signature = f"\tprivate static function {fn}( $reservation, $result, {defaultarg} ) {{"
    if signature not in s:
        raise SystemExit(f'R4 helper signature missing in {name}')
    error_status_var = '$result_status' if success == '$status' else '$status'
    body = signature + "\n" + """\t\tif ( is_wp_error( $reservation ) ) { return $reservation; }
\t\tif ( ! empty( $reservation['replay'] ) ) { return new WP_REST_Response( $reservation['body'], $reservation['code'] ); }
\t\tif ( is_wp_error( $result ) ) {
\t\t\t$data = $result->get_error_data();
"""
    body += f"\t\t\t{error_status_var} = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;\n"
    body += f"\t\t\t$finished = HE_V2_Domain::idempotent_finish( $reservation['id'], {error_status_var}, array( 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ) );\n"
    body += """\t\t\tif ( ! $finished ) {
\t\t\t\treturn new WP_Error( 'he_idempotency_finalize_failed', __( 'The request outcome could not be recorded safely. Reload the current state before retrying.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
\t\t\t}
\t\t\treturn $result;
\t\t}
"""
    body += f"\t\t$finished = HE_V2_Domain::idempotent_finish( $reservation['id'], {success}, $result );\n"
    body += """\t\tif ( ! $finished ) {
\t\t\treturn new WP_Error( 'he_idempotency_finalize_failed', __( 'The request may have completed, but its retry record could not be finalized safely. Reload the current state before retrying.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
\t\t}
"""
    body += f"\t\treturn new WP_REST_Response( $result, {success} );\n\t}}"
    pattern = re.escape(signature) + r'.*?\n\t}\n\n(?=\t(?:public|private) static function)'
    ns, n = re.subn(pattern, body+'\n\n', s, count=1, flags=re.S)
    if n != 1:
        raise SystemExit(f'R4 helper body not replaceable in {name}')
    p.write_text(ns)

# Regression: every mutation helper that finalizes an idempotency reservation must surface finalize failure.
t=Path('tests/v249-tenth-ten-round-regressions.php')
s=t.read_text(); marker='/*__V249_MORE__*/'
block="""$r4files=array('class-he-v24-future-api.php','class-he-v241-governance.php','class-he-v241-research-governance.php','class-he-v24-future-review-guard.php','class-he-v22-integrity.php','class-he-v242-watchlist.php','class-he-v242-multilingual.php');
foreach($r4files as $r4file){$src=v249_read($root.'/homeopathy-encyclopedia/includes/'.$r4file);v249_ok(false!==strpos($src,'he_idempotency_finalize_failed'),'R4 idempotency finalization failure remains silently ignored in '.$r4file);}"""
if block not in s:
    if marker not in s: raise SystemExit('R4 test marker missing')
    s=s.replace(marker,block+'\n'+marker,1)
t.write_text(s)
