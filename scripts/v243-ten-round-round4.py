#!/usr/bin/env python3
from pathlib import Path
p=Path('homeopathy-encyclopedia/includes/class-he-v241-governance.php')
s=p.read_text(encoding='utf-8')
old="""\tpublic static function maintenance_serialized() {\n\t\t$existing = get_option( self::LEASE_OPTION, array() );\n\t\tif ( is_array( $existing ) && ! empty( $existing['time'] ) && ( time() - absint( $existing['time'] ) ) > self::LEASE_TTL ) {\n\t\t\tdelete_option( self::LEASE_OPTION );\n\t\t}\n\t\t$token = wp_generate_uuid4();\n\t\tif ( ! add_option( self::LEASE_OPTION, array( 'token' => $token, 'time' => time() ), '', false ) ) {\n\t\t\treturn;\n\t\t}\n"""
new="""\tpublic static function maintenance_serialized() {\n\t\tglobal $wpdb;\n\t\t$existing = get_option( self::LEASE_OPTION, array() );\n\t\tif ( is_array( $existing ) && ! empty( $existing['time'] ) && ( time() - absint( $existing['time'] ) ) > self::LEASE_TTL ) {\n\t\t\t$wpdb->query( $wpdb->prepare(\n\t\t\t\t\"DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s\",\n\t\t\t\tself::LEASE_OPTION,\n\t\t\t\tmaybe_serialize( $existing )\n\t\t\t) );\n\t\t}\n\t\t$token = wp_generate_uuid4();\n\t\tif ( ! add_option( self::LEASE_OPTION, array( 'token' => $token, 'time' => time() ), '', false ) ) {\n\t\t\treturn;\n\t\t}\n"""
if old not in s:
    raise SystemExit('round4 target block not found')
s=s.replace(old,new,1)
p.write_text(s,encoding='utf-8')
print('round4 maintenance lease CAS takeover applied')
