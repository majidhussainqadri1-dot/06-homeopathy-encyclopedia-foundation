#!/usr/bin/env python3
from pathlib import Path
p=Path('homeopathy-encyclopedia/includes/class-he-v2-domain.php')
s=p.read_text(encoding='utf-8')
old="""\t\t\t$exists = (int) $wpdb->get_var( $wpdb->prepare(\n\t\t\t\t\"SELECT id FROM {$table} WHERE concept_id=%d AND version_id=%d AND source_type=%s AND title=%s AND edition=%s AND page_locator=%s AND url=%s AND doi=%s LIMIT 1\",\n\t\t\t\t$concept_id, $new_version_id, $ref['source_type'], $ref['title'], $ref['edition'], $ref['page_locator'], $ref['url'], $ref['doi']\n\t\t\t) );\n\t\t\tif ( $exists ) { continue; }\n\t\t\tunset( $ref['id'] );\n\t\t\t$ref['version_id'] = $new_version_id;\n\t\t\t$ref['created_by'] = absint( $actor_id );\n\t\t\t$ref['created_at'] = current_time( 'mysql', true );\n\t\t\t$wpdb->insert( $table, $ref );\n"""
new="""\t\t\t$old_reference_id = absint( $ref['id'] ?? 0 );\n\t\t\t$new_reference_id = (int) $wpdb->get_var( $wpdb->prepare(\n\t\t\t\t\"SELECT id FROM {$table} WHERE concept_id=%d AND version_id=%d AND source_type=%s AND title=%s AND edition=%s AND page_locator=%s AND url=%s AND doi=%s LIMIT 1\",\n\t\t\t\t$concept_id, $new_version_id, $ref['source_type'], $ref['title'], $ref['edition'], $ref['page_locator'], $ref['url'], $ref['doi']\n\t\t\t) );\n\t\t\tif ( ! $new_reference_id ) {\n\t\t\t\tunset( $ref['id'] );\n\t\t\t\t$ref['version_id'] = $new_version_id;\n\t\t\t\t$ref['created_by'] = absint( $actor_id );\n\t\t\t\t$ref['created_at'] = current_time( 'mysql', true );\n\t\t\t\tif ( ! $wpdb->insert( $table, $ref ) ) {\n\t\t\t\t\tcontinue;\n\t\t\t\t}\n\t\t\t\t$new_reference_id = (int) $wpdb->insert_id;\n\t\t\t}\n\t\t\tif ( $old_reference_id && $new_reference_id ) {\n\t\t\t\t$wpdb->query( $wpdb->prepare(\n\t\t\t\t\t'UPDATE ' . HE_V2_Schema::table( 'relations' ) . ' SET source_reference_id=%d,row_version=row_version+1,updated_at=UTC_TIMESTAMP() WHERE source_concept_id=%d AND source_reference_id=%d',\n\t\t\t\t\t$new_reference_id, $concept_id, $old_reference_id\n\t\t\t\t) );\n\t\t\t}\n"""
if old not in s:
    raise SystemExit('round3 target block not found')
s=s.replace(old,new,1)
p.write_text(s,encoding='utf-8')
print('round3 relation provenance remap applied')
