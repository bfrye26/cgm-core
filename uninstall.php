<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

// Core is an interoperability layer. Preserve configuration and relationship data by
// default so uninstalling/reinstalling the plugin cannot silently destroy editorial data.
if ( ! defined( 'CGM_CORE_REMOVE_DATA_ON_UNINSTALL' ) || true !== CGM_CORE_REMOVE_DATA_ON_UNINSTALL ) { return; }

function cgm_core_remove_site_data(): void {
    global $wpdb;
    foreach ( array(
        'cgm_core_schema_version',
        'cgm_core_relationship_types',
        'cgm_core_query_usage',
        'cgm_core_config_backups',
        'cgm_core_config_import_state',
        'cgm_core_cache_epoch',
        'cgm_core_cache_namespace_epochs',
        'cgm_core_cache_tag_epochs',
    ) as $option ) { delete_option( $option ); }

    $ids = get_posts( array( 'post_type'=>'cgm_saved_query', 'post_status'=>'any', 'posts_per_page'=>-1, 'fields'=>'ids', 'suppress_filters'=>true ) );
    foreach ( $ids as $id ) { wp_delete_post( (int) $id, true ); }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cgm_core_relationships" );

    foreach ( wp_roles()->roles as $role_id => $_role ) {
        $role = get_role( $role_id ); if ( ! $role ) { continue; }
        foreach ( array( 'manage_cgm_core','manage_cgm_queries','manage_cgm_relationships','manage_cgm_configuration','inspect_cgm_core','inspect_cgm_data' ) as $cap ) { $role->remove_cap( $cap ); }
    }
}

if ( is_multisite() ) {
    foreach ( get_sites( array( 'fields'=>'ids', 'number'=>0 ) ) as $site_id ) {
        switch_to_blog( (int) $site_id ); cgm_core_remove_site_data(); restore_current_blog();
    }
    delete_site_option( 'cgm_core_network_policy' );
    delete_site_option( 'cgm_core_network_defaults' );
} else {
    cgm_core_remove_site_data();
}
