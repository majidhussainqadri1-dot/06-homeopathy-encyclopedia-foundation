#!/usr/bin/env python3
from pathlib import Path
import json, sys
ROOT=Path(__file__).resolve().parents[1]
P=ROOT/'homeopathy-encyclopedia'; I=P/'includes'; T=ROOT/'tests'; D=ROOT/'docs'
r=int(sys.argv[1]) if len(sys.argv)>1 else 0
if r not in range(1,21): raise SystemExit('round must be 1..20')
def rd(p): return Path(p).read_text(encoding='utf-8')
def wr(p,s): Path(p).write_text(s,encoding='utf-8')
def between(p,start,end,new,label):
 p=Path(p);s=rd(p);a=s.find(start);b=s.find(end,a+len(start))
 if a<0 or b<0: raise SystemExit(f'{label}: markers missing')
 wr(p,s[:a]+new+s[b:])
def one(p,old,new,label):
 p=Path(p);s=rd(p)
 if s.count(old)!=1: raise SystemExit(f'{label}: expected one match, got {s.count(old)}')
 wr(p,s.replace(old,new,1))

schema=I/'class-he-v2-schema.php'; api=I/'class-he-v2-api.php'
if r==15:
 s=rd(schema); start='\tpublic static function repair( $dry_run = true ) {'; a=s.find(start); b=s.rfind('\n}')
 if a<0 or b<a: raise SystemExit('R15 repair markers missing')
 fn='''\tpublic static function repair( $dry_run = true ) {
\t\t$before = self::health();
\t\t$result = array( 'dry_run' => (bool) $dry_run, 'before' => $before, 'actions' => array() );
\t\tif ( $dry_run ) { return $result; }
\t\ttry { self::install(); } catch ( Throwable $error ) {
\t\t\tself::record_runtime_failure( 'repair_schema_failed', 'File 06 repair could not verify/install the canonical schema.' );
\t\t\treturn new WP_Error( 'he_repair_failed', __( 'File 06 schema repair could not be completed safely.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
\t\t}
\t\t$reindexed = HE_V2_Domain::reindex_all();
\t\tif ( is_wp_error( $reindexed ) ) { return $reindexed; }
\t\tif ( ! self::schema_complete() ) {
\t\t\tself::record_runtime_failure( 'repair_verification_failed', 'File 06 repair completed writes but the required schema is still incomplete.' );
\t\t\treturn new WP_Error( 'he_repair_failed', __( 'File 06 repair could not verify a complete schema.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
\t\t}
\t\tdelete_option( self::OPTION_FAILURE );
\t\tdelete_option( self::OPTION_SAFE_MODE );
\t\t$after = self::health();
\t\tif ( 'active' !== $after['status'] || empty( $after['schema_complete'] ) ) {
\t\t\tupdate_option( self::OPTION_SAFE_MODE, 1, false );
\t\t\tself::record_runtime_failure( 'repair_final_health_failed', 'File 06 repair could not establish an active verified final health state.' );
\t\t\treturn new WP_Error( 'he_repair_failed', __( 'File 06 repair could not establish a healthy final state.', 'homeopathy-encyclopedia' ), array( 'status' => 503 ) );
\t\t}
\t\t$result['actions'][] = 'schema-verified'; $result['actions'][] = 'search-index-rebuilt'; $result['actions'][] = 'safe-mode-cleared-after-verification'; $result['after'] = $after;
\t\treturn $result;
\t}
'''
 wr(schema,s[:a]+fn+s[b:])

if r==19:
 one(api,"\tprivate function require_mutation_guards( WP_REST_Request $request, $operation ) {\n\t\tif ( get_option( HE_V2_Schema::OPTION_SAFE_MODE ) ) {","\tprivate function require_mutation_guards( WP_REST_Request $request, $operation, $allow_safe_mode = false ) {\n\t\tif ( ! $allow_safe_mode && get_option( HE_V2_Schema::OPTION_SAFE_MODE ) ) {",'R19 allow repair in safe mode')
 one(api,"\t\t$reservation = $this->require_mutation_guards( $request, 'repair' );","\t\t$reservation = $this->require_mutation_guards( $request, 'repair', true );",'R19 repair reservation')

if r==20:
 # Correct the release record to reflect the new R19 recovery defect instead of a clean round.
 wr(ROOT/'README.md',rd(ROOT/'README.md').replace('- Defect rounds: `1–18, 20`\n- Clean fresh audit round: `19`','- Defect rounds: `1–20`\n- Clean rounds: `none`').replace('repair failure propagation, schema-complete health','repair failure propagation/recovery, schema-complete health'))
 wr(ROOT/'STATUS.md',rd(ROOT/'STATUS.md').replace('| Defect rounds | `1–18, 20` |\n| Clean round | `19` |','| Defect rounds | `1–20` |\n| Clean rounds | `none` |'))
 ch=rd(ROOT/'CHANGELOG.md').replace('product/repository defects corrected in rounds 1–18 and release-truth drift corrected in round 20; round 19 was a clean cross-cutting audit.','product/repository defects corrected in rounds 1–19 and release-truth drift corrected in round 20; every round produced an actionable correction.')
 wr(ROOT/'CHANGELOG.md',ch)
 sb=json.loads(rd(ROOT/'SBOM.json')); rel=sb.setdefault('release',{}); rel['defect_rounds']=list(range(1,21)); rel['clean_rounds']=[]; wr(ROOT/'SBOM.json',json.dumps(sb,ensure_ascii=False,indent=2)+'\n')
 wr(ROOT/'V2-MANIFEST.md',rd(ROOT/'V2-MANIFEST.md').replace('- Defect rounds: `1–18, 20`; clean round: `19`','- Defect rounds: `1–20`; clean rounds: `none`'))
 review=D/'FILE-06-v2.4.15-SIXTEENTH-TWENTY-ROUND-REVIEW.md'; s=rd(review).replace('Defect rounds: 1–18 and 20. Clean fresh cross-cutting audit: round 19.','Defect rounds: 1–20. No clean rounds in this cycle.').replace('19. Fresh cross-cutting audit found no additional repository-level defect after R1–R18 corrections.','19. Recovery audit found that the repair command itself was blocked by safe mode; repair reservation now remains authenticated/idempotent while permitting governed recovery from safe mode.')
 wr(review,s)
print(f'v2.4.15 overlay round {r} complete')
