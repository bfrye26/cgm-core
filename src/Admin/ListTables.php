<?php
namespace CGM\Core\Admin;

use CGM\Core\Plugin;
use CGM\Core\Workflow\WorkflowManager;

/**
 * WordPress-native admin list-table integration: a CGM state column plus a
 * filter dropdown, so editorial state is visible and filterable in the native
 * post/media list screens.
 */
final class ListTables {
    public function __construct( private Plugin $core ) {}

    public function register(): void {
        foreach ( get_post_types( array( 'public' => true ), 'names' ) as $pt ) {
            add_filter( "manage_{$pt}_posts_columns", array( $this, 'columns' ) );
            add_action( "manage_{$pt}_posts_custom_column", array( $this, 'render' ), 10, 2 );
        }
        add_action( 'restrict_manage_posts', array( $this, 'state_filter' ), 10, 2 );
        add_action( 'pre_get_posts', array( $this, 'filter_query' ) );
    }

    public function columns( array $cols ): array {
        $cols['cgm_state'] = __( 'CGM State', 'cgm-core' );
        return $cols;
    }

    public function render( string $column, int $post_id ): void {
        if ( 'cgm_state' !== $column ) { return; }
        $state = $this->core->workflow()->get_state( $post_id );
        $label = $state;
        foreach ( $this->core->workflow()->states() as $s ) { if ( $s['id'] === $state ) { $label = $s['label']; break; } }
        echo '<span class="cgm-state cgm-state--' . esc_attr( $state ) . '">' . esc_html( $label ) . '</span>';
    }

    public function state_filter( string $post_type, string $which ): void {
        if ( ! $this->core->content_types()->has( $post_type ) ) { return; }
        $current = sanitize_key( (string) ( $_GET['cgm_state'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        echo '<select name="cgm_state"><option value="">' . esc_html__( 'All CGM states', 'cgm-core' ) . '</option>';
        foreach ( $this->core->workflow()->states() as $s ) {
            echo '<option value="' . esc_attr( $s['id'] ) . '"' . selected( $current, $s['id'], false ) . '>' . esc_html( $s['label'] ) . '</option>';
        }
        echo '</select>';
    }

    public function filter_query( \WP_Query $q ): void {
        if ( ! is_admin() || ! $q->is_main_query() ) { return; }
        $state = sanitize_key( (string) ( $_GET['cgm_state'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( ! $state ) { return; }
        // Mirror the dropdown: only apply the filter on registered CGM lists.
        $post_type = $q->get( 'post_type' );
        if ( is_array( $post_type ) || ! $post_type || ! $this->core->content_types()->has( (string) $post_type ) ) { return; }
        $q->set( 'meta_key', WorkflowManager::META );
        $q->set( 'meta_value', $state );
    }
}
