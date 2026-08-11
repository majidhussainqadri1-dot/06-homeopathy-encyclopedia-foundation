from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def replace_once(path, old, new):
    p = ROOT / path
    text = p.read_text(encoding="utf-8")
    if old not in text:
        raise SystemExit(f"round1 target not found: {path}")
    if text.count(old) != 1:
        raise SystemExit(f"round1 target not unique: {path}")
    p.write_text(text.replace(old, new, 1), encoding="utf-8")

ready_guard = "\t\tif ( ! class_exists( 'HE_V24_Migration_Safety' ) || ! HE_V24_Migration_Safety::ready() ) { return; }\n"

for path, signature in [
    ("homeopathy-encyclopedia/includes/class-he-v242-multilingual.php", "\tpublic static function override_translation_route() {\n"),
    ("homeopathy-encyclopedia/includes/class-he-v242-language-surfaces.php", "\tpublic static function routes() {\n"),
    ("homeopathy-encyclopedia/includes/class-he-v242-translation-compat.php", "\tpublic static function override_public_route() {\n"),
    ("homeopathy-encyclopedia/includes/class-he-v242-watchlist.php", "\tpublic static function override_route() {\n"),
]:
    replace_once(path, signature, signature + ready_guard)

path = "homeopathy-encyclopedia/includes/class-he-v242-language-migration.php"
old = "\tpublic static function run_bounded() {\n\t\tif ( self::ready() || ! class_exists( 'HE_V24_Future_Schema' ) || ! self::lock() ) { return; }\n"
new = "\tpublic static function run_bounded() {\n\t\tif ( self::ready() || ! class_exists( 'HE_V24_Future_Schema' ) || ! class_exists( 'HE_V24_Migration_Safety' ) ) { return; }\n\t\t$translations = HE_V24_Future_Schema::table( 'translations' );\n\t\tif ( ! HE_V24_Migration_Safety::table_exists( $translations ) || ! self::lock() ) { return; }\n"
replace_once(path, old, new)

# Reuse the already verified table name instead of re-deriving it after the lease is acquired.
replace_once(
    path,
    "\t\t\t$translations = HE_V24_Future_Schema::table( 'translations' );\n\t\t\t$rows =",
    "\t\t\t$rows =",
)

print("Applied File 06 v2.4.4 round-1 fail-closed migration/route correction")
