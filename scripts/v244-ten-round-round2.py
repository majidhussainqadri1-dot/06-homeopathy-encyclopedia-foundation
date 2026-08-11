from pathlib import Path

p = Path(__file__).resolve().parents[1] / 'homeopathy-encyclopedia/includes/class-he-v242-research-browse.php'
text = p.read_text(encoding='utf-8')
old = "\t\tif ( 'successful-case' === $row['record_type'] ) { $out['case'] = json_decode( (string) $row['case_json'], true ); }\n\t\tif ( 'dataset' === $row['record_type'] ) { $out['dataset_metadata'] = json_decode( (string) $row['metadata_json'], true ); }\n"
new = "\t\tif ( 'successful-case' === $row['record_type'] ) {\n\t\t\t$case = json_decode( (string) $row['case_json'], true );\n\t\t\tif ( 'public' === $row['data_class'] ) {\n\t\t\t\t$out['case'] = is_array( $case ) ? $case : array();\n\t\t\t} else {\n\t\t\t\t$out['case_details_restricted'] = true;\n\t\t\t}\n\t\t}\n\t\tif ( 'dataset' === $row['record_type'] ) {\n\t\t\t$metadata = json_decode( (string) $row['metadata_json'], true );\n\t\t\t$metadata = is_array( $metadata ) ? $metadata : array();\n\t\t\t$public_metadata = array();\n\t\t\tforeach ( array( 'description','de_identification','lawful_basis','access_policy' ) as $field ) {\n\t\t\t\tif ( isset( $metadata[ $field ] ) && is_scalar( $metadata[ $field ] ) ) {\n\t\t\t\t\t$public_metadata[ $field ] = sanitize_textarea_field( (string) $metadata[ $field ] );\n\t\t\t\t}\n\t\t\t}\n\t\t\t$out['dataset_metadata'] = $public_metadata;\n\t\t\t$out['dataset_payload_public'] = false;\n\t\t}\n"
if text.count(old) != 1:
    raise SystemExit('round2 research browse target missing or non-unique')
p.write_text(text.replace(old, new, 1), encoding='utf-8')
print('Applied File 06 v2.4.4 round-2 public research DTO minimization')
