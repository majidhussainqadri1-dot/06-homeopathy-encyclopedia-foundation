<?php
/** File 06 v2.4.20 R18 release-truth regression invariants. */
$root = dirname( __DIR__ );
$files = array(
    'main' => file_get_contents( $root . '/homeopathy-encyclopedia/homeopathy-encyclopedia.php' ),
    'plugin_readme' => file_get_contents( $root . '/homeopathy-encyclopedia/readme.txt' ),
    'readme' => file_get_contents( $root . '/README.md' ),
    'status' => file_get_contents( $root . '/STATUS.md' ),
    'sbom' => file_get_contents( $root . '/SBOM.json' ),
    'manifest' => file_get_contents( $root . '/V2-MANIFEST.md' ),
    'runall' => file_get_contents( $root . '/tests/run-all.sh' ),
);
foreach ( $files as $name => $content ) {
    if ( false === $content ) { fwrite( STDERR, "Unable to read release file: {$name}\n" ); exit( 1 ); }
}
$needles = array(
    'main' => array("Version: 2.4.20", "HE_VERSION', '2.4.20", "HE_CONTRACT_VERSION', '2.4.20", "future_hardening_version'=>'2.4.20'"),
    'plugin_readme' => array('Stable tag: 2.4.20', '= 2.4.20 ='),
    'readme' => array('Homeopathy Encyclopedia 2.4.20', 'audit/file-06-twenty-first-twenty-round-v2.4.20'),
    'status' => array('2.4.20 / schema 10 / contract 2.4.20'),
    'sbom' => array('"version": "2.4.20"', 'homeopathy-encyclopedia@2.4.20', '2.4.20.zip', '"contract": "2.4.20"'),
    'manifest' => array('Runtime version: `2.4.20`', 'Contract: `2.4.20`', 'audit/file-06-twenty-first-twenty-round-v2.4.20'),
    'runall' => array('file06-v2.4.20-a.zip', 'file06-v2.4.20-b.zip', 'All File 06 v2.4.20 automated checks'),
);
foreach ( $needles as $name => $required ) {
    foreach ( $required as $needle ) {
        if ( false === strpos( $files[$name], $needle ) ) {
            fwrite( STDERR, "Missing R18 release-truth invariant in {$name}: {$needle}\n" );
            exit( 1 );
        }
    }
}
foreach ( array('main','plugin_readme','readme','status','sbom','manifest','runall') as $name ) {
    if ( false !== strpos( $files[$name], 'v2.4.19-a.zip' ) || false !== strpos( $files[$name], 'v2.4.19-b.zip' ) ) {
        fwrite( STDERR, "Stale current package label remains in {$name}.\n" );
        exit( 1 );
    }
}
echo "File 06 v2.4.20 R18 release-truth regressions: PASS\n";
