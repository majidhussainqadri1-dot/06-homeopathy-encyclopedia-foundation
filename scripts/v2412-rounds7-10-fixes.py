#!/usr/bin/env python3
from pathlib import Path
import re, sys

ROOT=Path(__file__).resolve().parents[1]
P=ROOT/'homeopathy-encyclopedia'
T=ROOT/'tests'
D=ROOT/'docs'
round_no=int(sys.argv[1]) if len(sys.argv)>1 else 0
if round_no not in (7,8,9,10):
    raise SystemExit('round must be 7..10')

def read(path): return Path(path).read_text(encoding='utf-8')
def write(path,text): Path(path).write_text(text,encoding='utf-8')
def replace_once(path,old,new,label):
    path=Path(path); s=read(path); n=s.count(old)
    if n!=1: raise SystemExit(f'{label}: expected exactly one match, found {n} in {path}')
    write(path,s.replace(old,new,1))
def regex_once(path,pattern,repl,label,flags=re.S):
    path=Path(path); s=read(path)
    out,n=re.subn(pattern,lambda m: repl,s,count=1,flags=flags)
    if n!=1: raise SystemExit(f'{label}: expected exactly one regex match, found {n} in {path}')
    write(path,out)

domain=P/'includes/class-he-v2-domain.php'
authoring=P/'includes/class-he-v242-research-authoring.php'
third=P/'includes/class-he-v242-third-audit.php'
public=P/'includes/class-he-v22-public-guard.php'
bootstrap=P/'homeopathy-encyclopedia.php'
readme=P/'readme.txt'

if round_no==7:
    anchor="\n\tpublic static function create_research( $data, $actor_id ) {\n"
    helper=r'''
	public static function sanitize_text_list( $value ) {
		if ( ! is_array( $value ) ) { $value = preg_split( '/[\r\n,;]+/u', (string) $value ); }
		if ( isset( $value['statement'] ) && is_scalar( $value['statement'] ) ) { $value = array( $value['statement'] ); }
		elseif ( isset( $value['name'] ) && is_scalar( $value['name'] ) ) { $value = array( $value['name'] ); }
		$out = array();
		$walk = static function( $item ) use ( &$out, &$walk ) {
			if ( is_array( $item ) ) {
				foreach ( array( 'statement','name','text','value' ) as $key ) {
					if ( isset( $item[ $key ] ) && is_scalar( $item[ $key ] ) ) { $walk( $item[ $key ] ); return; }
				}
				foreach ( $item as $child ) { $walk( $child ); }
				return;
			}
			if ( ! is_scalar( $item ) ) { return; }
			$text = sanitize_text_field( (string) $item );
			if ( '' !== $text ) { $out[] = $text; }
		};
		$walk( $value );
		return array_values( array_unique( $out ) );
	}
'''
    replace_once(domain,anchor,helper+anchor,'R7 safe text-list helper')
    replace_once(domain,"'investigators_json' => wp_json_encode( array_values( array_map( 'sanitize_text_field', (array) ( $data['investigators'] ?? array() ) ) ) ),","'investigators_json' => wp_json_encode( self::sanitize_text_list( $data['investigators'] ?? array() ) ),",'R7 research investigator shape')
    replace_once(domain,"'conflicts_json' => wp_json_encode( array_values( array_map( 'sanitize_text_field', (array) ( $data['conflicts'] ?? array() ) ) ) ),","'conflicts_json' => wp_json_encode( self::sanitize_text_list( $data['conflicts'] ?? array() ) ),",'R7 research conflict shape')
    pattern=r"\tprivate static function conflicts\( \$value \) \{.*?\n\t\}\n\n\tprivate static function investigators"
    repl="""\tprivate static function conflicts( $value ) {
\t\t$parts = HE_V2_Domain::sanitize_text_list( $value );
\t\t$statement = sanitize_textarea_field( implode( '; ', $parts ) );
\t\tif ( '' === trim( $statement ) ) { return array(); }
\t\t$none = (bool) preg_match( '/\\b(?:no|none)\\s+(?:conflict|conflicts)\\b/i', $statement );
\t\treturn array( 'recorded' => true, 'statement' => $statement, 'none_declared' => $none );
\t}

\tprivate static function investigators"""
    regex_once(authoring,pattern,repl,'R7 authoring conflicts normalizer')
    replace_once(third,"$conflicts = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $data['conflicts'] ?? array() ) ) ) );","$conflicts = HE_V2_Domain::sanitize_text_list( $data['conflicts'] ?? array() );",'R7 early conflict validation')
    replace_once(third,"$parts = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $input['conflicts'] ?? array() ) ) ) );","$parts = HE_V2_Domain::sanitize_text_list( $input['conflicts'] ?? array() );",'R7 post-create conflict normalization')

elif round_no==8:
    replace_once(public,"\t\tadd_filter( 'posts_where', array( __CLASS__, 'research_public_query_where' ), 99, 2 );\n","\t\tadd_filter( 'posts_where', array( __CLASS__, 'research_public_query_where' ), 99, 2 );\n\t\tadd_filter( 'posts_results', array( __CLASS__, 'research_public_query_results' ), 99, 2 );\n",'R8 canonical result hook')
    anchor="\n\tpublic static function research_title( $title, $post_id = 0 ) {\n"
    fn=r'''
	public static function research_public_query_results( $posts, $query ) {
		if ( is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() || ! is_array( $posts ) ) { return $posts; }
		$out = array();
		foreach ( $posts as $post ) {
			if ( ! is_object( $post ) || HE_V2_Domain::RESEARCH_TYPE !== $post->post_type ) { $out[] = $post; continue; }
			$row = self::row_for_post( (int) $post->ID );
			if ( self::is_public_row( $row ) ) { $out[] = $post; }
		}
		return $out;
	}
'''
    replace_once(public,anchor,fn+anchor,'R8 canonical research results filter')

elif round_no==9:
    replace_once(domain,"\tprivate static $idempotency_leases = array();\n","\tprivate static $idempotency_leases = array();\n\tprivate static $merge_resolution_stack = array();\n",'R9 merge resolution stack')
    old="""\t\tif ( ! empty( $row['merged_into_id'] ) ) {
\t\t\treturn self::concept_by_id( (int) $row['merged_into_id'], $include_private );
\t\t}
"""
    new="""\t\tif ( ! empty( $row['merged_into_id'] ) ) {
\t\t\t$current_id = (int) $row['id'];
\t\t\tif ( isset( self::$merge_resolution_stack[ $current_id ] ) || count( self::$merge_resolution_stack ) >= 32 ) {
\t\t\t\tHE_V2_Schema::record_runtime_failure( 'concept_merge_cycle_detected', 'File 06 stopped canonical resolution because a merge cycle or excessive merge chain was detected.' );
\t\t\t\treturn null;
\t\t\t}
\t\t\tself::$merge_resolution_stack[ $current_id ] = true;
\t\t\ttry {
\t\t\t\treturn self::concept_by_id( (int) $row['merged_into_id'], $include_private );
\t\t\t} finally {
\t\t\t\tunset( self::$merge_resolution_stack[ $current_id ] );
\t\t\t}
\t\t}
"""
    replace_once(domain,old,new,'R9 merge-cycle fail closed')

elif round_no==10:
    s=read(bootstrap).replace('2.4.11','2.4.12')
    write(bootstrap,s)
    s=read(readme)
    s=s.replace('Stable tag: 2.4.11','Stable tag: 2.4.12').replace('The 2.4.11 candidate','The 2.4.12 candidate')
    if '= 2.4.12 =' not in s:
        s=s.replace('== Changelog ==\n','== Changelog ==\n\n= 2.4.12 =\n* Thirteenth ten-round corrective candidate: recursive structured DTO safety, CAS-safe scheduled invalidation, transaction-certain governed merges, source-language convergence, event privacy minimization, nested research-shape compatibility, canonical public research filtering, merge-cycle resilience, and refreshed exact-head QA truth.\n')
    write(readme,s)
    for path in (T/'v2-invariants.php', T/'v2-source-invariants.sh'):
        write(path,read(path).replace('2.4.11','2.4.12'))
    oldtest=T/'v2411-twelfth-ten-round-regressions.php'
    s=read(oldtest)
    old="v2411_ok(10,false!==strpos($bootstrap,' * Version: 2.4.11')&&false!==strpos($bootstrap,\"define( 'HE_VERSION', '2.4.11' );\")&&false!==strpos($bootstrap,\"define( 'HE_CONTRACT_VERSION', '2.4.11' );\")&&false!==strpos($bootstrap,\"'future_hardening_version'=>'2.4.11'\"),'runtime/contract/future hardening truth is not v2.4.11');\nv2411_ok(10,false!==strpos($readme,'Stable tag: 2.4.11')&&false!==strpos($runall,'v2411-twelfth-ten-round-regressions.php')&&false!==strpos($runall,'file06-v2.4.11-a.zip'),'package/aggregate release truth is not v2.4.11');"
    new="v2411_ok(10,preg_match('/ \\* Version: 2\\.4\\.(?:11|12)/',$bootstrap)&&preg_match(\"/HE_VERSION', '2\\.4\\.(?:11|12)/\",$bootstrap)&&preg_match(\"/HE_CONTRACT_VERSION', '2\\.4\\.(?:11|12)/\",$bootstrap)&&false!==strpos($bootstrap,\"'future_hardening_version'=>\"),'historical v2.4.11 release controls do not tolerate a later current v2.4.x candidate');\nv2411_ok(10,false!==strpos($runall,'v2411-twelfth-ten-round-regressions.php'),'historical twelfth-cycle regression suite is no longer wired into aggregate QA');"
    if old not in s: raise SystemExit('R10 v2411 historical release assertion not found')
    write(oldtest,s.replace(old,new,1))
    runall=T/'run-all.sh'; s=read(runall)
    if 'v2412-thirteenth-ten-round-regressions.php' not in s:
        s=s.replace('php "$root/tests/v2411-twelfth-ten-round-regressions.php"\n','php "$root/tests/v2411-twelfth-ten-round-regressions.php"\nphp "$root/tests/v2412-thirteenth-ten-round-regressions.php"\n')
    s=s.replace('file06-v2.4.11-a.zip','file06-v2.4.12-a.zip').replace('file06-v2.4.11-b.zip','file06-v2.4.12-b.zip')
    s=s.replace('All File 06 v2.4.11 automated checks, inherited review matrices, twelfth ten-round regressions and deterministic package comparison passed.','All File 06 v2.4.12 automated checks, inherited review matrices, thirteenth ten-round regressions and deterministic package comparison passed.')
    write(runall,s)
    (D/'RELEASE-NOTES.md').write_text("""# File 06 — Release Notes 2.4.12

Thirteenth fresh ten-round corrective repository candidate. Repository defects were found and corrected in rounds `1–9`; round `10` reconciles current release/runtime/package/QA truth after those source corrections.

Primary corrections cover recursive structured DTO safety, CAS-safe scheduled invalidation, transaction-certain governed merge behavior, fail-closed source-language convergence, privacy-minimized event payloads, nested research input compatibility, canonical public-research filtering, bounded merge-chain/cycle handling, and exact v2.4.12 release truth.

Final exact-head automated run number and package/source hashes must be taken from the completed final workflow. Staging and live remain separate evidence gates.
""",encoding='utf-8')
    signoff=D/'RELEASE-SIGNOFF.md'
    if signoff.exists():
        signoff.write_text("""# File 06 — v2.4.12 Repository Candidate Signoff

- Repository candidate: v2.4.12
- Base DB schema: 10
- Future internal schema: 2
- Automated QA: pending final exact-head workflow at the time of this source commit
- Staging acceptance: unverified
- Live deployed version: unverified
- Live DB/migration state: unverified
- Operational status: not established

No staging/live claim is implied by repository automation.
""",encoding='utf-8')

print(f'v2.4.12 thirteenth cycle round {round_no} correction applied')
