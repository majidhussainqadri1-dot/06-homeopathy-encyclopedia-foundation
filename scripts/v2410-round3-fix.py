from pathlib import Path
# Harden the release gate and authoritative transition gate so successful-case publication independently rechecks anonymization + verified consent flags.
p=Path('homeopathy-encyclopedia/includes/class-he-v22-governance.php')
s=p.read_text()
old="""\t\tif ( empty( $ethics['approval_reference'] ) || ( 'successful-case' === $row['record_type'] && empty( $consent['verified'] ) ) ) {
\t\t\treturn new WP_Error( 'he_ethics_gate_failed', __( 'Ethics approval and required consent must be documented.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
\t\t}
"""
new="""\t\t$case_governance_failed = 'successful-case' === $row['record_type'] && ( empty( $consent['verified'] ) || empty( $row['case_consent_verified'] ) || empty( $row['case_anonymized'] ) );
\t\tif ( empty( $ethics['approval_reference'] ) || $case_governance_failed ) {
\t\t\treturn new WP_Error( 'he_ethics_gate_failed', __( 'Ethics approval, verified consent and anonymization must be documented before release.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
\t\t}
"""
if old not in s: raise SystemExit('R3 governance release target not found')
s=s.replace(old,new,1)
p.write_text(s)

p=Path('homeopathy-encyclopedia/includes/class-he-v2-domain.php')
s=p.read_text()
old="""\t\tif ( in_array( $to_state, array( 'approved', 'active', 'published' ), true ) ) {
\t\t\t$ethics = json_decode( $row['ethics_json'], true );
\t\t\t$consent = json_decode( $row['consent_json'], true );
\t\t\tif ( empty( $ethics['approval_reference'] ) || ( 'successful-case' === $row['record_type'] && empty( $consent['verified'] ) ) ) {
\t\t\t\treturn new WP_Error( 'he_ethics_gate_failed', __( 'Ethics approval and required consent must be documented.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
\t\t\t}
\t\t}
"""
new="""\t\tif ( in_array( $to_state, array( 'approved', 'active', 'published' ), true ) ) {
\t\t\t$ethics = json_decode( $row['ethics_json'], true );
\t\t\t$consent = json_decode( $row['consent_json'], true );
\t\t\t$case_governance_failed = 'successful-case' === $row['record_type'] && ( empty( $consent['verified'] ) || empty( $row['case_consent_verified'] ) || empty( $row['case_anonymized'] ) );
\t\t\tif ( empty( $ethics['approval_reference'] ) || $case_governance_failed ) {
\t\t\t\treturn new WP_Error( 'he_ethics_gate_failed', __( 'Ethics approval, verified consent and anonymization must be documented.', 'homeopathy-encyclopedia' ), array( 'status' => 422 ) );
\t\t\t}
\t\t}
"""
if old not in s: raise SystemExit('R3 domain transition target not found')
s=s.replace(old,new,1)
p.write_text(s)

t=Path('tests/v2410-eleventh-ten-round-regressions.php')
ts=t.read_text(); marker='/*__V2410_MORE__*/'
add="""$domain=v2410_read($root.'/homeopathy-encyclopedia/includes/class-he-v2-domain.php');\nv2410_ok(substr_count($v22,'case_governance_failed')>=1 && false!==strpos($domain,'case_governance_failed') && false!==strpos($domain,'case_consent_verified') && false!==strpos($domain,'case_anonymized'),'R3 successful-case release can proceed without authoritative consent/anonymization flags');\n"""
if marker not in ts: raise SystemExit('test marker missing')
ts=ts.replace(marker,add+marker,1); t.write_text(ts)
