<?php
/** Runtime regression for deleted_post_meta passing an array of meta IDs. */
$GLOBALS['cgm_test_options'] = array();

if ( ! function_exists( 'get_option' ) ) {
    function get_option( $key, $default = false ) { return $GLOBALS['cgm_test_options'][ $key ] ?? $default; }
}
if ( ! function_exists( 'update_option' ) ) {
    function update_option( $key, $value, $autoload = null ) { $GLOBALS['cgm_test_options'][ $key ] = $value; return true; }
}
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) ); }
}
if ( ! function_exists( 'absint' ) ) {
    function absint( $value ) { return abs( (int) $value ); }
}

$root = dirname( __DIR__ );
require_once $root . '/src/Cache/Cache.php';
require_once $root . '/src/Cache/Invalidator.php';

$cache = new \CGM\Core\Cache\Cache();
$invalidator = new \CGM\Core\Cache\Invalidator( $cache );

try {
    $invalidator->deleted_post_meta( array( 2501, 2502 ), 391, '_wp_attached_file', '2026/08/cgm-core.zip' );
} catch ( Throwable $e ) {
    fwrite( STDERR, 'FAIL: deleted_post_meta array caused ' . get_class( $e ) . ': ' . $e->getMessage() . "\n" );
    exit( 1 );
}

$epochs = $GLOBALS['cgm_test_options']['cgm_core_cache_tag_epochs'] ?? array();
foreach ( array( 'post_391', 'field_meta__wp_attached_file', 'field_acf__wp_attached_file', 'field_metabox__wp_attached_file' ) as $key ) {
    if ( empty( $epochs[ $key ] ) ) {
        fwrite( STDERR, "FAIL: expected invalidation tag {$key} was not bumped.\n" );
        exit( 1 );
    }
}

echo "Cache invalidator runtime regression passed for deleted_post_meta array payload.\n";
