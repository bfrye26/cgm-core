<?php
/** Static regression for WordPress hook argument compatibility. */
$root = dirname( __DIR__ );
$code = (string) file_get_contents( $root . '/src/Cache/Invalidator.php' );

$checks = array(
    'deleted_post_meta has a dedicated callback' => str_contains( $code, "add_action( 'deleted_post_meta', array( \$this, 'deleted_post_meta' )" ),
    'deleted_post_meta callback is not scalar-type constrained' => (bool) preg_match( '/function\s+deleted_post_meta\s*\(\s*\$meta_ids\b/', $code ),
    'added/updated post_meta callback is not scalar-type constrained' => (bool) preg_match( '/function\s+post_meta\s*\(\s*\$meta_id\b/', $code ),
    'post-meta invalidation is centralized' => str_contains( $code, 'private function invalidate_post_meta' ),
    'set_object_terms hook boundary is not array-type constrained' => (bool) preg_match( '/function\s+terms\s*\(\s*\$object_id\s*,\s*\$terms\s*,/', $code ),
);

foreach ( $checks as $label => $pass ) {
    if ( ! $pass ) { fwrite( STDERR, "FAIL: {$label}\n" ); exit( 1 ); }
}

echo 'Cache invalidator WordPress-hook compatibility regression passed (' . count( $checks ) . " checks).\n";
