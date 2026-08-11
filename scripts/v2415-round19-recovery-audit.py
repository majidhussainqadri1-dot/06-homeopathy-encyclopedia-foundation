#!/usr/bin/env python3
from pathlib import Path
root=Path(__file__).resolve().parents[1]
api=(root/'homeopathy-encyclopedia/includes/class-he-v2-api.php').read_text(encoding='utf-8')
schema=(root/'homeopathy-encyclopedia/includes/class-he-v2-schema.php').read_text(encoding='utf-8')
checks={
 'repair guard supports governed safe-mode recovery':'require_mutation_guards( WP_REST_Request $request, $operation, $allow_safe_mode = false )' in api,
 'normal mutations remain blocked in safe mode':'! $allow_safe_mode && get_option( HE_V2_Schema::OPTION_SAFE_MODE )' in api,
 'repair opts into recovery path':"require_mutation_guards( $request, 'repair', true )" in api,
 'repair still uses authenticated route capability':"HE_V2_Auth::CAP_REPAIR" in api,
 'repair only clears safe mode after schema/reindex verification':'delete_option( self::OPTION_SAFE_MODE )' in schema and 'self::schema_complete()' in schema and 'is_wp_error( $reindexed )' in schema,
 'repair has final active-health verification':"'active' !== $after['status']" in schema and 'repair_final_health_failed' in schema,
}
failed=[k for k,v in checks.items() if not v]
if failed:
    raise SystemExit('File 06 round-19 recovery audit FAILED:\n- '+'\n- '.join(failed))
print('File 06 v2.4.15 round-19 recovery audit: PASS')
