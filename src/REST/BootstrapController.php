<?php
namespace CGM\Core\REST;

use CGM\Core\Plugin;

/**
 * Single-request aggregate for the React console: versioned contracts,
 * capabilities, counts, and the query-builder schema (content types, fields,
 * relationships, taxonomies, context tokens) plus saved-query summaries.
 */
final class BootstrapController extends BaseController {
    private const CACHE_KEY = 'cgm_core_bootstrap';

    public function __construct( private Plugin $core ) {}

    /** Drop the cached aggregate after any write that changes registry state. */
    public static function invalidate(): void { delete_transient( self::CACHE_KEY ); }

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/bootstrap', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => fn() => rest_ensure_response( $this->cached_payload() ),
            'permission_callback' => fn() => current_user_can( 'inspect_cgm_core' ) || $this->can_manage() || current_user_can( 'manage_cgm_queries' ),
        ) );
    }

    private function cached_payload(): array {
        // Registries are rebuilt in-memory every request anyway; the transient only
        // skips the per-field/per-relationship/taxonomy iteration cost. Writes bump it.
        $cached = get_transient( self::CACHE_KEY );
        if ( ! is_array( $cached ) ) {
            $cached = $this->payload();
            set_transient( self::CACHE_KEY, $cached, 60 );
        }
        // Caps are per-user and must never be served from the shared cache.
        $cached['caps'] = $this->caps();
        return $cached;
    }

    private function caps(): array {
        return array(
            'manage'               => $this->can_manage(),
            'manageQueries'        => current_user_can( 'manage_cgm_queries' ),
            'manageRelationships'  => current_user_can( 'manage_cgm_relationships' ),
            'manageConfig'         => current_user_can( 'manage_cgm_configuration' ),
            'inspectData'          => current_user_can( 'inspect_cgm_data' ) || current_user_can( 'inspect_cgm_core' ),
        );
    }

    private function payload(): array {
        $content_types = array();
        foreach ( $this->core->content_types()->all() as $ct ) {
            if ( ! $this->core->query_providers()->for_content_type( $ct ) ) { continue; }
            $content_types[] = array( 'id'=>$ct['id'], 'label'=>$ct['label'] ?? $ct['id'], 'plural_label'=>$ct['plural_label'] ?? $ct['label'], 'kind'=>$ct['kind'] ?? '', 'public'=>!empty( $ct['public'] ) );
        }

        $fields = array();
        foreach ( $this->core->fields()->all() as $f ) {
            if ( empty( $f['queryable'] ) ) { continue; }
            $fields[] = array(
                'id'=>$f['id'], 'label'=>$f['label'] ?? $f['id'], 'type'=>$f['type'] ?? 'string',
                'operators'=>$f['operators'] ?? array( '=', '!=' ), 'provider'=>$f['provider'] ?? '',
                'content_types'=>array_values( (array) ( $f['content_types'] ?? array( '*' ) ) ),
                'sortable'=>!empty( $f['sortable'] ), 'options'=>$f['options'] ?? array(), 'source'=>$f['source'] ?? '',
            );
        }

        $relationships = array();
        foreach ( $this->core->relationships()->all() as $r ) {
            if ( empty( $r['queryable'] ) || ! $this->core->relationships()->queryable( (string) $r['id'] ) ) { continue; }
            $relationships[] = array(
                'id'=>$r['id'], 'label'=>$r['label'] ?? $r['id'], 'source_type'=>$r['source_type'] ?? '',
                'source_types'=>array_values( (array) ( $r['source_types'] ?? array() ) ), 'target_type'=>$r['target_type'] ?? '',
                'operators'=>array( '=', '!=', 'IN', 'NOT IN', 'EXISTS', 'NOT EXISTS' ), 'roles'=>$r['roles'] ?? array(),
                'metadata_schema'=>$r['metadata_schema'] ?? array(), 'provider'=>$r['provider'] ?? 'core', 'store'=>$r['store'] ?? 'core',
                'managed_by'=>$r['managed_by'] ?? 'provider',
                'properties'=>array_merge(
                    array(
                        array( 'id'=>'target_id', 'label'=>'Target', 'type'=>'object' ),
                        array( 'id'=>'role', 'label'=>'Role', 'type'=>'string' ),
                        array( 'id'=>'primary', 'label'=>'Primary', 'type'=>'boolean' ),
                        array( 'id'=>'order', 'label'=>'Order', 'type'=>'integer' ),
                    ),
                    array_map(
                        fn( $k, $d ) => array( 'id'=>$k, 'label'=>$d['label'] ?? $k, 'type'=>$d['type'] ?? 'string', 'options'=>$d['options'] ?? array() ),
                        array_keys( (array) ( $r['metadata_schema'] ?? array() ) ),
                        array_values( (array) ( $r['metadata_schema'] ?? array() ) )
                    )
                ),
            );
        }

        $taxonomies = array();
        foreach ( get_taxonomies( array( 'show_ui'=>true ), 'objects' ) as $t ) {
            $supported = array();
            foreach ( (array) $t->object_type as $post_type ) { if ( $this->core->content_types()->has( $post_type ) ) { $supported[] = $post_type; } }
            $taxonomies[] = array( 'id'=>$t->name, 'label'=>$t->labels->singular_name, 'content_types'=>$supported, 'operators'=>array( 'IN', 'NOT IN', 'EXISTS', 'NOT EXISTS' ) );
        }

        $providers = array();
        foreach ( $this->core->providers()->all() as $id => $p ) {
            $providers[] = array(
                'id'=>$id, 'label'=>$p['label'] ?? $id, 'status'=>$p['status'] ?? 'ready',
                'version'=>$p['version'] ?? '', 'capabilities'=>$p['capabilities'] ?? array(),
                'compatibility'=>$this->core->providers()->compatibility( (string) $id ),
            );
        }

        $builders = array();
        foreach ( $this->core->builders()->all() as $b ) {
            $builders[] = array(
                'id'=>$b['id'], 'label'=>$b['label'] ?? $b['id'], 'detected'=>!empty( $b['detected'] ),
                'integration_level'=>$b['integration_level'] ?? 'bridge', 'capabilities'=>$b['capabilities'] ?? array(),
            );
        }

        $saved_queries = array();
        foreach ( $this->core->saved_queries()->list() as $q ) {
            $saved_queries[] = array(
                'id'=>$q['id'], 'slug'=>$q['slug'], 'title'=>$q['title'], 'public'=>!empty( $q['public'] ),
                'managed_by'=>$q['managed_by'] ?? 'database', 'readonly'=>!empty( $q['readonly'] ),
                'usage'=>count( $this->core->saved_queries()->usage( (string) $q['slug'] ) ),
            );
        }

        // Full normalized definitions (not queryable-filtered) for the Relationships editor.
        $relationship_definitions = array();
        foreach ( $this->core->relationships()->all() as $r ) {
            $relationship_definitions[] = array(
                'id'=>$r['id'], 'label'=>$r['label'] ?? $r['id'], 'reverse_label'=>$r['reverse_label'] ?? '',
                'source_type'=>$r['source_type'] ?? 'post', 'source_types'=>$r['source_types'] ?? array(),
                'target_type'=>$r['target_type'] ?? 'post', 'cardinality'=>$r['cardinality'] ?? 'many_to_many',
                'multiple'=>!empty( $r['multiple'] ), 'ordered'=>!empty( $r['ordered'] ),
                'primary'=>!empty( $r['primary'] ), 'queryable'=>!empty( $r['queryable'] ), 'public'=>!empty( $r['public'] ),
                'cross_site'=>!empty( $r['cross_site'] ), 'max_items'=>(int) ( $r['max_items'] ?? 0 ),
                'primary_max'=>(int) ( $r['primary_max'] ?? 1 ), 'delete_behavior'=>$r['delete_behavior'] ?? 'detach',
                'roles'=>$r['roles'] ?? array(), 'metadata_schema'=>$r['metadata_schema'] ?? array(),
                'assign_capability'=>$r['assign_capability'] ?? 'edit_posts', 'read_capability'=>$r['read_capability'] ?? 'read',
                'provider'=>$r['provider'] ?? 'core', 'store'=>$r['store'] ?? 'core', 'managed_by'=>$r['managed_by'] ?? 'provider',
            );
        }

        return array(
            'version'            => CGM_CORE_VERSION,
            'api'                => array( 'core'=>CGM_CORE_API_VERSION, 'query'=>CGM_CORE_QUERY_API_VERSION, 'relationships'=>CGM_CORE_RELATIONSHIP_API_VERSION, 'dynamic_data'=>CGM_CORE_DYNAMIC_DATA_API_VERSION ),
            'schema'             => CGM_CORE_SCHEMA_VERSION,
            'counts'             => array(
                'providers'       => count( $providers ),
                'content_types'   => count( $content_types ),
                'fields'          => count( $fields ),
                'relationships'   => count( $relationships ),
                'queries'         => count( $saved_queries ),
                'builders'        => count( $builders ),
            ),
            'contentTypes'       => $content_types,
            'fields'             => $fields,
            'relationships'      => $relationships,
            'relationshipDefinitions' => $relationship_definitions,
            'taxonomies'         => $taxonomies,
            'tokens'             => $this->core->context()->tokens(),
            'providers'          => $providers,
            'builders'           => $builders,
            'queryProviders'     => array_keys( $this->core->query_providers()->all() ),
            'multisite'          => $this->core->multisite()->describe(),
            'savedQueries'       => $saved_queries,
            'dynamicData'        => array_values( $this->core->dynamic_data()->serialize() ),
            'viewModes'          => array_values( $this->core->view_modes()->all() ),
        );
    }
}
