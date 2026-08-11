from pathlib import Path

p = Path(__file__).resolve().parents[1] / 'homeopathy-encyclopedia/includes/class-he-v241-governance-privacy.php'
text = p.read_text(encoding='utf-8')
old = "\t\t\t'post_type' => HE_V2_Domain::ENTRY_TYPE,\n"
new = "\t\t\t'post_type' => array( HE_V2_Domain::ENTRY_TYPE, HE_V2_Domain::RESEARCH_TYPE ),\n"
if text.count(old) != 1:
    raise SystemExit('round6 post-type pagination target missing or non-unique')
text = text.replace(old, new, 1)
marker = "\tpublic static function export( $email, $page = 1 ) {\n"
helper = "\tprivate static function public_object_id( $post_id ) {\n\t\tif ( HE_V2_Domain::RESEARCH_TYPE === get_post_type( $post_id ) ) {\n\t\t\tglobal $wpdb;\n\t\t\treturn (string) $wpdb->get_var( $wpdb->prepare( 'SELECT public_id FROM ' . HE_V2_Schema::table( 'research' ) . ' WHERE post_id=%d', absint( $post_id ) ) );\n\t\t}\n\t\t$concept = HE_V2_Domain::concept_by_id( get_post_field( 'post_name', $post_id ), true );\n\t\treturn is_array( $concept ) ? (string) ( $concept['public_id'] ?? '' ) : '';\n\t}\n\n"
if text.count(marker) != 1:
    raise SystemExit('round6 export insertion target missing or non-unique')
text = text.replace(marker, helper + marker, 1)
old_id = "array( 'name' => 'entry_public_id', 'value' => (string) ( HE_V2_Domain::concept_by_id( get_post_field( 'post_name', $post_id ), true )['public_id'] ?? '' ) ),"
new_id = "array( 'name' => 'object_public_id', 'value' => self::public_object_id( $post_id ) ),\n\t\t\t\t\t\tarray( 'name' => 'object_type', 'value' => HE_V2_Domain::RESEARCH_TYPE === get_post_type( $post_id ) ? 'research' : 'entry' ),"
if text.count(old_id) != 1:
    raise SystemExit('round6 exported object id target missing or non-unique')
p.write_text(text.replace(old_id, new_id, 1), encoding='utf-8')
print('Applied File 06 v2.4.4 round-6 research reviewer privacy export/erasure correction')
