<?php
namespace CGM\Core\Multisite;

/**
 * Explicit multisite policy. Configuration is site-local by default; network defaults
 * and cross-site relationships are opt-in so a normal site never changes semantics.
 */
final class MultisitePolicy {
    private const OPTION = 'cgm_core_network_policy';

    public function register(): void {
        if ( is_multisite() ) { add_action( 'network_admin_menu', array( $this, 'menu' ) ); }
    }

    public function mode(): string { return is_multisite() ? 'site-local-with-network-defaults' : 'single-site'; }

    public function settings(): array {
        $defaults = array(
            'configuration_scope'      => 'site',
            'allow_network_defaults'   => true,
            'cross_site_relationships' => false,
        );
        if ( ! is_multisite() ) { return $defaults; }
        $stored = get_site_option( self::OPTION, array() );
        return wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );
    }

    public function cross_site_relationships_allowed(): bool {
        $settings = $this->settings();
        $constant = defined( 'CGM_CORE_ALLOW_CROSS_SITE_RELATIONSHIPS' ) && CGM_CORE_ALLOW_CROSS_SITE_RELATIONSHIPS;
        return (bool) apply_filters( 'cgm_core/multisite/cross_site_relationships', $constant || ! empty( $settings['cross_site_relationships'] ) );
    }

    public function network_defaults(): array {
        if ( ! is_multisite() || empty( $this->settings()['allow_network_defaults'] ) ) { return array(); }
        $defaults = get_site_option( 'cgm_core_network_defaults', array() );
        return is_array( $defaults ) ? $defaults : array();
    }

    public function configuration_scope(): string { return (string) $this->settings()['configuration_scope']; }
    public function site_key( int $site_id = 0 ): string { return is_multisite() ? 'site:' . ( $site_id ?: get_current_blog_id() ) : 'site:1'; }

    public function describe(): array {
        return array(
            'multisite'                => is_multisite(),
            'mode'                     => $this->mode(),
            'configuration_scope'      => $this->configuration_scope(),
            'cross_site_relationships' => $this->cross_site_relationships_allowed(),
            'network_defaults'         => array_keys( $this->network_defaults() ),
            'site_key'                 => $this->site_key(),
        );
    }

    public function menu(): void {
        if ( ! current_user_can( 'manage_network_options' ) ) { return; }
        add_submenu_page( 'settings.php', __( 'CGM Core Network', 'cgm-core' ), __( 'CGM Core', 'cgm-core' ), 'manage_network_options', 'cgm-core-network', array( $this, 'render' ) );
    }

    public function render(): void {
        if ( ! current_user_can( 'manage_network_options' ) ) { return; }
        if ( isset( $_POST['cgm_core_network_save'] ) ) {
            check_admin_referer( 'cgm_core_network' );
            $scope = sanitize_key( (string) ( $_POST['configuration_scope'] ?? 'site' ) );
            if ( ! in_array( $scope, array( 'site','network-defaults' ), true ) ) { $scope = 'site'; }
            update_site_option( self::OPTION, array(
                'configuration_scope'      => $scope,
                'allow_network_defaults'   => ! empty( $_POST['allow_network_defaults'] ),
                'cross_site_relationships' => ! empty( $_POST['cross_site_relationships'] ),
            ) );
            $raw_defaults = isset( $_POST['network_defaults_json'] ) ? wp_unslash( (string) $_POST['network_defaults_json'] ) : '';
            if ( '' !== trim( $raw_defaults ) ) {
                $decoded = json_decode( $raw_defaults, true );
                if ( is_array( $decoded ) && is_array( $decoded['queries'] ?? array() ) && is_array( $decoded['relationships'] ?? array() ) ) {
                    update_site_option( 'cgm_core_network_defaults', array(
                        'schema'        => sanitize_text_field( (string) ( $decoded['schema'] ?? CGM_CORE_CONFIG_SCHEMA ) ),
                        'queries'       => array_values( (array) $decoded['queries'] ),
                        'relationships' => array_values( (array) $decoded['relationships'] ),
                    ) );
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'CGM Core network policy and defaults saved.', 'cgm-core' ) . '</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>' . esc_html__( 'Network defaults JSON was not valid. The policy was saved, but existing defaults were preserved.', 'cgm-core' ) . '</p></div>';
                }
            } else {
                delete_site_option( 'cgm_core_network_defaults' );
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'CGM Core network policy saved.', 'cgm-core' ) . '</p></div>';
            }
        }
        $settings = $this->settings();
        echo '<div class="wrap"><h1>' . esc_html__( 'CGM Core Network', 'cgm-core' ) . '</h1>';
        echo '<p>' . esc_html__( 'Site-local configuration is the safe default. Network defaults and cross-site relationships must be enabled explicitly.', 'cgm-core' ) . '</p>';
        echo '<form method="post">'; wp_nonce_field( 'cgm_core_network' );
        echo '<table class="form-table"><tr><th>' . esc_html__( 'Configuration policy', 'cgm-core' ) . '</th><td><select name="configuration_scope">';
        echo '<option value="site" ' . selected( $settings['configuration_scope'], 'site', false ) . '>' . esc_html__( 'Site-local', 'cgm-core' ) . '</option>';
        echo '<option value="network-defaults" ' . selected( $settings['configuration_scope'], 'network-defaults', false ) . '>' . esc_html__( 'Site-local with network defaults', 'cgm-core' ) . '</option></select></td></tr>';
        echo '<tr><th>' . esc_html__( 'Network defaults', 'cgm-core' ) . '</th><td><label><input type="checkbox" name="allow_network_defaults" value="1" ' . checked( ! empty( $settings['allow_network_defaults'] ), true, false ) . '> ' . esc_html__( 'Allow sites to consume network defaults', 'cgm-core' ) . '</label></td></tr>';
        echo '<tr><th>' . esc_html__( 'Cross-site relationships', 'cgm-core' ) . '</th><td><label><input type="checkbox" name="cross_site_relationships" value="1" ' . checked( ! empty( $settings['cross_site_relationships'] ), true, false ) . '> ' . esc_html__( 'Allow providers that explicitly support cross-site relationships', 'cgm-core' ) . '</label><p class="description">' . esc_html__( 'Core-owned relationship rows remain site-local. A provider must explicitly implement cross-site storage before this setting has an effect.', 'cgm-core' ) . '</p></td></tr>';
        $network_defaults = $this->network_defaults();
        echo '<tr><th><label for="cgm-core-network-defaults">' . esc_html__( 'Network default configuration', 'cgm-core' ) . '</label></th><td><textarea id="cgm-core-network-defaults" name="network_defaults_json" class="large-text code" rows="14">' . esc_textarea( $network_defaults ? wp_json_encode( $network_defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : '' ) . '</textarea><p class="description">' . esc_html__( 'Optional fallback saved queries and relationship definitions. Site-local definitions with the same ID take precedence. Leave blank to remove network defaults.', 'cgm-core' ) . '</p></td></tr></table>';
        submit_button( __( 'Save network policy', 'cgm-core' ), 'primary', 'cgm_core_network_save' );
        echo '</form></div>';
    }
}
