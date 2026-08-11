from pathlib import Path

p = Path(__file__).resolve().parents[1] / 'homeopathy-encyclopedia/includes/class-he-v242-research-authoring.php'
text = p.read_text(encoding='utf-8')
old = "\t\t$expected_loaded = isset( $_POST[ HE_V242_Third_Audit::RESEARCH_EXPECTED_VERSION ] ) ? absint( $_POST[ HE_V242_Third_Audit::RESEARCH_EXPECTED_VERSION ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing\n\t\t/* HE_V2_Admin::save_research_meta runs at priority 20 and increments exactly once before this saver. */\n\t\t$expected_now = $expected_loaded ? $expected_loaded + 1 : (int) $row['row_version'];\n"
new = "\t\t$expected_loaded = isset( $_POST[ HE_V242_Third_Audit::RESEARCH_EXPECTED_VERSION ] ) ? absint( $_POST[ HE_V242_Third_Audit::RESEARCH_EXPECTED_VERSION ] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing\n\t\t/* Account for every verified same-request writer that runs before priority 170. */\n\t\t$expected_now = $expected_loaded;\n\t\tif ( $expected_loaded ) {\n\t\t\tif ( isset( $_POST['he_v2_research_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['he_v2_research_nonce'] ) ), 'he_v2_save_research' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing\n\t\t\t\t++$expected_now;\n\t\t\t}\n\t\t\tif ( isset( $_POST['he_v22_research_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['he_v22_research_nonce'] ) ), 'he_v22_research_completeness' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing\n\t\t\t\t++$expected_now;\n\t\t\t}\n\t\t} else {\n\t\t\t$expected_now = (int) $row['row_version'];\n\t\t}\n"
if text.count(old) != 1:
    raise SystemExit('round3 concurrency target missing or non-unique')
p.write_text(text.replace(old, new, 1), encoding='utf-8')
print('Applied File 06 v2.4.4 round-3 same-request row-version accounting correction')
