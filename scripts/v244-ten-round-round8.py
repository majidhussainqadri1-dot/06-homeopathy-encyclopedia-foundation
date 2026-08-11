from pathlib import Path

p = Path(__file__).resolve().parents[1] / 'homeopathy-encyclopedia/includes/class-he-v242-research-immutability.php'
text = p.read_text(encoding='utf-8')
old = "\t\tif ( ! $post_id || wp_is_post_revision( $post_id ) || ! isset( $_POST['he_v2_research_nonce'] ) ) { return $data; } // phpcs:ignore WordPress.Security.NonceVerification.Missing\n"
new = "\t\tif ( ! $post_id || wp_is_post_revision( $post_id ) ) { return $data; }\n"
if text.count(old) != 1:
    raise SystemExit('round8 immutability nonce-gate target missing or non-unique')
p.write_text(text.replace(old, new, 1), encoding='utf-8')
print('Applied File 06 v2.4.4 round-8 published research immutability correction')
