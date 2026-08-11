from pathlib import Path

p = Path(__file__).resolve().parents[1] / 'homeopathy-encyclopedia/includes/class-he-v2-admin.php'
text = p.read_text(encoding='utf-8')
old_ui = "\t\t\t<label><span><?php esc_html_e( 'Language', 'homeopathy-encyclopedia' ); ?></span><select name=\"he_v2_language\"><option value=\"en-US\" <?php selected( $language, 'en-US' ); ?>>English (US)</option><option value=\"ur-PK\" <?php selected( $language, 'ur-PK' ); ?>>اردو</option><option value=\"ar\" <?php selected( $language, 'ar' ); ?>>العربية</option></select></label>\n"
new_ui = "\t\t\t<label><span><?php esc_html_e( 'Source language', 'homeopathy-encyclopedia' ); ?></span><input type=\"text\" value=\"<?php echo esc_attr( class_exists( 'HE_V242_Multilingual' ) ? ( HE_V242_Multilingual::canonical_locale( $language ) ?: $language ) : $language ); ?>\" readonly><small><?php esc_html_e( 'Edit the canonical BCP-47 source language in the Original source language box.', 'homeopathy-encyclopedia' ); ?></small></label>\n"
if text.count(old_ui) != 1:
    raise SystemExit('round4 legacy language UI target missing or non-unique')
text = text.replace(old_ui, new_ui, 1)
old_save = "\t\t$language = sanitize_text_field( wp_unslash( $_POST['he_v2_language'] ?? 'en-US' ) );\n\t\tif ( in_array( $language, array( 'en-US', 'ur-PK', 'ar' ), true ) ) {\n\t\t\tupdate_post_meta( $post_id, '_he_language', $language );\n\t\t}\n"
new_save = "\t\t/* v2.4.2+ owns source-language writes; never transiently reset a wider BCP-47 source through the legacy three-locale field. */\n\t\tif ( ! class_exists( 'HE_V242_Language_Surfaces' ) || ! isset( $_POST[ HE_V242_Language_Surfaces::NONCE_FIELD ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing\n\t\t\t$language = sanitize_text_field( wp_unslash( $_POST['he_v2_language'] ?? 'en-US' ) );\n\t\t\tif ( in_array( $language, array( 'en-US', 'ur-PK', 'ar' ), true ) ) {\n\t\t\t\tupdate_post_meta( $post_id, '_he_language', $language );\n\t\t\t}\n\t\t}\n"
if text.count(old_save) != 1:
    raise SystemExit('round4 legacy language save target missing or non-unique')
p.write_text(text.replace(old_save, new_save, 1), encoding='utf-8')
print('Applied File 06 v2.4.4 round-4 canonical source-language ownership correction')
