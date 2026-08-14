<?php
/**
 * Static regression checks for the Core 1.x/2.x Suite compatibility facade.
 * Runtime function-call coverage is handled in staging/WordPress; this test ensures
 * the historical entry points and permissive enqueue signature cannot disappear.
 */
$root = dirname( __DIR__ );
$bootstrap = file_get_contents( $root . '/cgm-core.php' );
$compat = file_get_contents( $root . '/includes/legacy-suite-compat.php' );
$ui = file_get_contents( $root . '/src/UI/UIContract.php' );

$checks = array(
    'compat facade is loaded' => str_contains( $bootstrap, "includes/legacy-suite-compat.php" ),
    'legacy UI contract version is explicit' => str_contains( $bootstrap, 'CGM_CORE_UI_CONTRACT_VERSION' ),
    'historical two-argument enqueue is permissive' => str_contains( $compat, 'function cgm_core_enqueue_ui( $module_id = \'\', $args = array() )' ),
    'admin app shim exists' => str_contains( $compat, 'function cgm_core_register_admin_app' ),
    'module contract shim exists' => str_contains( $compat, 'function cgm_core_register_module_contract' ),
    'UI availability shim exists' => str_contains( $compat, 'function cgm_core_ui_available' ),
    'legacy asset registration exists' => str_contains( $compat, 'function cgm_core_register_ui_assets' ),
    'legacy token handle exists' => str_contains( $compat, 'cgm-core-ui-tokens' ),
    'UIContract compatibility class exists' => str_contains( $ui, 'final class UIContract' ),
);
foreach ( $checks as $label => $pass ) {
    if ( ! $pass ) { fwrite( STDERR, "FAIL: {$label}\n" ); exit( 1 ); }
}
echo 'Legacy Suite compatibility regression passed (' . count( $checks ) . " checks).\n";
