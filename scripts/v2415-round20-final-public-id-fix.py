#!/usr/bin/env python3
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]; P=ROOT/'homeopathy-encyclopedia'; I=P/'includes'; D=ROOT/'docs'; T=ROOT/'tests'
def rd(p): return Path(p).read_text(encoding='utf-8')
def wr(p,s): Path(p).write_text(s,encoding='utf-8')
def one(p,old,new,label):
 p=Path(p);s=rd(p);n=s.count(old)
 if n!=1: raise SystemExit(f'{label}: expected one match, found {n}')
 wr(p,s.replace(old,new,1))
api=I/'class-he-v2-api.php'; domain=I/'class-he-v2-domain.php'; bootstrap=P/'homeopathy-encyclopedia.php'
one(api,"'/dataset-access/(?P<id>\\d+)/approve'","'/dataset-access/(?P<id>[A-Za-z0-9_-]+\\.[a-f0-9]{64})/approve'",'dataset approval route')
one(api,"HE_V2_Domain::approve_dataset_access( absint( $request['id'] ),","HE_V2_Domain::approve_dataset_access( sanitize_text_field( (string) $request['id'] ),",'dataset approval callback')
one(domain,"\t\tif ( $existing && 'approved' === $existing['status'] && ! empty( $existing['expires_at'] ) && strtotime( $existing['expires_at'] . ' UTC' ) > time() ) { return true; }","\t\tif ( $existing && 'approved' === $existing['status'] && ! empty( $existing['expires_at'] ) && strtotime( $existing['expires_at'] . ' UTC' ) > time() ) {\n\t\t\treturn array( 'request_id' => self::encode_public_cursor( 'dataset-access', (int) $existing['id'] ), 'status' => 'approved', 'expires_at' => $existing['expires_at'] );\n\t\t}",'existing approved token')
one(domain,"\t\tif ( false === $result ) {\n\t\t\tHE_V2_Schema::record_runtime_failure( 'dataset_access_request_write_failed', 'File 06 could not persist a governed dataset-access request.' );\n\t\t\treturn new WP_Error( 'he_dataset_access_write_failed', __( 'Dataset access request could not be saved safely.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );\n\t\t}\n\t\treturn true;\n\t}\n\n\tpublic static function approve_dataset_access( $access_id, $expires_at, $actor_id ) {","\t\tif ( false === $result ) {\n\t\t\tHE_V2_Schema::record_runtime_failure( 'dataset_access_request_write_failed', 'File 06 could not persist a governed dataset-access request.' );\n\t\t\treturn new WP_Error( 'he_dataset_access_write_failed', __( 'Dataset access request could not be saved safely.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );\n\t\t}\n\t\t$access_id = $existing ? (int) $existing['id'] : (int) $wpdb->insert_id;\n\t\t$request_id = self::encode_public_cursor( 'dataset-access', $access_id );\n\t\tif ( ! $access_id || ! $request_id ) {\n\t\t\tHE_V2_Schema::record_runtime_failure( 'dataset_access_public_id_failed', 'File 06 persisted a dataset-access request but could not derive its opaque public request identifier.' );\n\t\t\treturn new WP_Error( 'he_dataset_access_write_failed', __( 'Dataset access request could not establish its public identifier safely.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );\n\t\t}\n\t\treturn array( 'request_id' => $request_id, 'status' => 'requested' );\n\t}\n\n\tpublic static function approve_dataset_access( $access_identifier, $expires_at, $actor_id ) {\n\t\t$access_id = self::decode_public_cursor( 'dataset-access', $access_identifier );\n\t\tif ( null === $access_id || ! $access_id ) {\n\t\t\treturn new WP_Error( 'he_dataset_access_not_found', __( 'Dataset access request not found.', 'homeopathy-encyclopedia' ), array( 'status' => 404 ) );\n\t\t}",'dataset request token and approval decode')
one(bootstrap,"'canonical_public_ids_only'=>true,'internal_ids_exposed'=>false,'core_numeric_enumeration_blocked'=>true,","'canonical_public_ids_only'=>true,'internal_ids_exposed'=>false,'core_numeric_enumeration_blocked'=>true,'dataset_access_approval_identifier'=>'opaque-signed-token',",'contract token boundary')
run=T/'run-all.sh'; s=rd(run); marker='php "$root/tests/v2415-sixteenth-twenty-round-regressions.php"\n'
if 'v2415-round20-public-id-regressions.php' not in s:
 if marker not in s: raise SystemExit('run-all v2415 marker missing')
 s=s.replace(marker,marker+'php "$root/tests/v2415-round20-public-id-regressions.php"\n',1); wr(run,s)
review=D/'FILE-06-v2.4.15-SIXTEENTH-TWENTY-ROUND-REVIEW.md'; s=rd(review); s=s.replace('20. Runtime, contract, current QA, SBOM/manifest and repository documentation aligned to 2.4.15.','20. Final cross-cutting audit found a remaining raw numeric dataset-access approval route; it was replaced by a signed opaque request token, then runtime, contract, current QA, SBOM/manifest and repository documentation were aligned to 2.4.15.')
wr(review,s)
ch=ROOT/'CHANGELOG.md'; s=rd(ch); needle='- Hardened canonical identifiers, opaque pagination, WordPress/domain projection parity, migration/reindex/repair failure propagation, complete health checks, authoritative post binding, hard-delete confirmation and secure scheduled-publication ownership.'
if needle in s: s=s.replace(needle,needle+' Dataset-access approval now also uses a signed opaque request token rather than a raw database ID.',1)
wr(ch,s)
readme=ROOT/'README.md'; s=rd(readme); s=s.replace('authoritative post binding, and a single secure scheduled-publication path.','authoritative post binding, a single secure scheduled-publication path, and opaque dataset-access approval identifiers.')
wr(readme,s)
print('File 06 v2.4.15 R20 final public-ID correction applied')
