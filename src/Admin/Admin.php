<?php
namespace CGM\Core\Admin;

use CGM\Core\Plugin;

/**
 * WordPress admin host for the React control room console.
 *
 * The screen itself is a single mount point rendered by build/admin.js. All
 * data flows through the cgm-core/v1 REST API (BootstrapController and the
 * other controllers), so this class only registers the menu and enqueues the
 * compiled assets plus the boot payload.
 */
final class Admin {
    public function __construct( private Plugin $core ) {}

    public function register(): void {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
    }

    public function menu(): void {
        add_management_page(
            __( 'CGM Core', 'cgm-core' ),
            __( 'CGM Core', 'cgm-core' ),
            'manage_cgm_core',
            'cgm-core',
            array( $this, 'render' )
        );
    }

    public function assets( string $hook ): void {
        if ( 'tools_page_cgm-core' !== $hook ) { return; }
        wp_enqueue_style( 'cgm-core-admin', CGM_CORE_URL . 'build/admin.css', array(), $this->asset_version( 'build/admin.css' ) );
        wp_enqueue_script( 'cgm-core-admin', CGM_CORE_URL . 'build/admin.js', array(), $this->asset_version( 'build/admin.js' ), true );
        wp_add_inline_script(
            'cgm-core-admin',
            'window.cgmCoreBoot = ' . wp_json_encode( $this->bootstrap() ) . ';',
            'before'
        );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_cgm_core' ) ) { wp_die( esc_html__( 'Permission denied.', 'cgm-core' ) ); }
        echo '<div class="wrap"><div id="cgm-core-app"><p>' . esc_html__( 'Loading CGM Core…', 'cgm-core' ) . '</p></div></div>';
    }

    private function bootstrap(): array {
        return array(
            'restPath'      => rest_url( 'cgm-core/v1/' ),
            'adminUrl'      => admin_url(),
            'pluginVersion' => CGM_CORE_VERSION,
            'nonce'         => wp_create_nonce( 'wp_rest' ),
            'caps'          => array(
                'manage'               => current_user_can( 'manage_cgm_core' ),
                'manageQueries'        => current_user_can( 'manage_cgm_queries' ),
                'manageRelationships'  => current_user_can( 'manage_cgm_relationships' ),
                'manageConfig'         => current_user_can( 'manage_cgm_configuration' ),
                'inspectData'          => current_user_can( 'inspect_cgm_data' ) || current_user_can( 'inspect_cgm_core' ),
            ),
        );
    }

    /** Cache-busting version string for a built asset. */
    private function asset_version( string $relative_path ): string {
        $full_path = CGM_CORE_PATH . $relative_path;
        $mtime     = file_exists( $full_path ) ? filemtime( $full_path ) : false;
        return $mtime ? (string) $mtime : CGM_CORE_VERSION;
    }
}
