<?php
/**
 * Plugin Name: CGM Core
 * Description: WordPress-native structured content, relationships, query, dynamic data, and builder interoperability platform.
 * Version: 3.0.0-beta.1
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Author: CGMagazine
 * Text Domain: cgm-core
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'CGM_CORE_VERSION', '3.0.0-beta.1' );
// Core 3 does not ship the retired CGMUI 1.x application runtime. Keep the legacy
// contract explicit so older Suite modules can fail closed instead of fatally erroring.
if ( ! defined( 'CGM_CORE_UI_CONTRACT_VERSION' ) ) { define( 'CGM_CORE_UI_CONTRACT_VERSION', '0.0.0' ); }
define( 'CGM_CORE_API_VERSION', '3.0' );
define( 'CGM_CORE_QUERY_API_VERSION', '3.0' );
define( 'CGM_CORE_RELATIONSHIP_API_VERSION', '3.0' );
define( 'CGM_CORE_DYNAMIC_DATA_API_VERSION', '3.0' );
define( 'CGM_CORE_SCHEMA_VERSION', '4' );
define( 'CGM_CORE_CONFIG_SCHEMA', '3' );
define( 'CGM_CORE_FILE', __FILE__ );
define( 'CGM_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'CGM_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once CGM_CORE_PATH . 'src/Support/Autoloader.php';
CGM\Core\Support\Autoloader::register();

register_activation_hook( __FILE__, array( 'CGM\\Core\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CGM\\Core\\Plugin', 'deactivate' ) );
add_action( 'plugins_loaded', static function(): void { CGM\Core\Plugin::instance()->boot(); }, 5 );

if ( ! function_exists( 'cgm_core' ) ) { function cgm_core(): CGM\Core\Plugin { return CGM\Core\Plugin::instance(); } }
if ( ! function_exists( 'cgm_register_provider' ) ) { function cgm_register_provider( array $definition ): void { cgm_core()->providers()->register( $definition ); } }
if ( ! function_exists( 'cgm_register_provider_object' ) ) { function cgm_register_provider_object( CGM\Core\Contracts\ProviderInterface $provider ): void { $provider->register( cgm_core() ); } }
if ( ! function_exists( 'cgm_register_content_type' ) ) { function cgm_register_content_type( array $definition ): void { cgm_core()->content_types()->register( $definition ); } }
if ( ! function_exists( 'cgm_register_field' ) ) { function cgm_register_field( array $definition ): void { cgm_core()->fields()->register( $definition ); } }
if ( ! function_exists( 'cgm_register_relationship_type' ) ) { function cgm_register_relationship_type( array $definition ): void { cgm_core()->relationships()->register_type( $definition ); } }
if ( ! function_exists( 'cgm_register_relationship_store' ) ) { function cgm_register_relationship_store( string $id, CGM\Core\Contracts\RelationshipStoreInterface $store ): void { cgm_core()->relationships()->register_store( $id, $store ); } }
if ( ! function_exists( 'cgm_register_dynamic_data' ) ) { function cgm_register_dynamic_data( array $definition ): void { cgm_core()->dynamic_data()->register( $definition ); } }
if ( ! function_exists( 'cgm_register_editor_control' ) ) { function cgm_register_editor_control( array $definition ): void { cgm_core()->editor_controls()->register( $definition ); } }
if ( ! function_exists( 'cgm_register_service' ) ) { function cgm_register_service( string $id, object|callable $service, array $definition = array() ): void { cgm_core()->services()->register( $id, $service, $definition ); } }
if ( ! function_exists( 'cgm_register_builder' ) ) { function cgm_register_builder( array $definition ): void { cgm_core()->builders()->register( $definition ); } }
if ( ! function_exists( 'cgm_register_object_adapter' ) ) { function cgm_register_object_adapter( CGM\Core\Contracts\ObjectAdapterInterface $adapter ): void { cgm_core()->objects()->register_adapter( $adapter ); } }
if ( ! function_exists( 'cgm_register_query_provider' ) ) { function cgm_register_query_provider( CGM\Core\Contracts\QueryProviderInterface $provider ): void { cgm_core()->query_providers()->register( $provider ); } }
if ( ! function_exists( 'cgm_register_index' ) ) { function cgm_register_index( array $definition ): void { cgm_core()->indexes()->register( $definition ); } }
if ( ! function_exists( 'cgm_register_rule_action' ) ) { function cgm_register_rule_action( string $id, string $label, callable $callback ): void { cgm_core()->rules()->register_action( $id, $label, $callback ); } }
if ( ! function_exists( 'cgm_register_workflow_state' ) ) { function cgm_register_workflow_state( array $definition ): void { cgm_core()->workflow()->register_state( $definition ); } }
if ( ! function_exists( 'cgm_register_view_mode' ) ) { function cgm_register_view_mode( array $definition ): void { cgm_core()->view_modes()->register( $definition ); } }
if ( ! function_exists( 'cgm_register_search_provider' ) ) { function cgm_register_search_provider( CGM\Core\Search\SearchProviderInterface $provider ): void { cgm_core()->search()->register_provider( $provider ); } }
if ( ! function_exists( 'cgm_register_facet' ) ) { function cgm_register_facet( array $definition ): void { cgm_core()->facets()->register( $definition ); } }
if ( ! function_exists( 'cgm_notify' ) ) { function cgm_notify( string $id, string $title, string $message, string $type = 'info' ): void { cgm_core()->notifications()->notify( $id, $title, $message, $type ); } }
if ( ! function_exists( 'cgm_locale' ) ) { function cgm_locale( mixed $object = null ): string { return cgm_core()->locale()->for_object( $object ); } }
if ( ! function_exists( 'cgm_pathauto' ) ) { function cgm_pathauto( string $pattern, mixed $object = null ): string { return cgm_core()->pathauto()->build( $pattern, $object ); } }
if ( ! function_exists( 'cgm_register_saved_query' ) ) { function cgm_register_saved_query( string $id, array $definition, array $args = array() ): void { cgm_core()->saved_queries()->register_code( $id, $definition, $args ); } }
if ( ! function_exists( 'cgm_register_relationship_definition' ) ) { function cgm_register_relationship_definition( array $definition ): void { cgm_core()->configured_relationships()->register_code( $definition ); cgm_core()->relationships()->register_type( $definition + array( 'managed_by'=>'code' ) ); } }
if ( ! function_exists( 'cgm_register_context' ) ) { function cgm_register_context( string $key, string $label, callable $resolver ): void { cgm_core()->context()->register( $key, $label, $resolver ); } }
if ( ! function_exists( 'cgm_core_supports' ) ) { function cgm_core_supports( string $capability ): bool { return cgm_core()->providers()->supports( $capability ); } }
if ( ! function_exists( 'cgm_core_api_compatible' ) ) { function cgm_core_api_compatible( array $requirements ): array { return cgm_core()->api_compatibility()->report( $requirements ); } }
if ( ! function_exists( 'cgm_query' ) ) { function cgm_query( array|string|int $query, array $context = array() ): CGM\Core\Query\QueryResult { return cgm_core()->queries()->run( $query, $context ); } }
if ( ! function_exists( 'cgm_query_ids' ) ) { function cgm_query_ids( array|string|int $query, array $context = array() ): array { return cgm_core()->queries()->ids_for( $query, $context ); } }
if ( ! function_exists( 'cgm_data' ) ) { function cgm_data( string $key, mixed $object = null, array $context = array() ): mixed { return cgm_core()->dynamic_data()->resolve( $key, $object, $context ); } }
if ( ! function_exists( 'cgm_object' ) ) { function cgm_object( mixed $value, ?string $content_type = null ): ?CGM\Core\Objects\ObjectReference { return cgm_core()->objects()->reference( $value, $content_type ); } }
if ( ! function_exists( 'cgm_relationships' ) ) { function cgm_relationships(): CGM\Core\Relationships\RelationshipManager { return cgm_core()->relationships(); } }
if ( ! function_exists( 'cgm_register_event_contract' ) ) { function cgm_register_event_contract( string $event, string $version = '1.0', array $required = array(), array $definition = array() ): void { cgm_core()->events()->register( $event, $version, $required, $definition ); } }
if ( ! function_exists( 'cgm_event' ) ) { function cgm_event( string $event, array $payload = array() ): void { cgm_core()->events()->dispatch( $event, $payload ); } }
if ( ! function_exists( 'cgm_listen' ) ) { function cgm_listen( string $event, callable $callback, int $priority = 10 ): void { cgm_core()->events()->listen( $event, $callback, $priority ); } }
if ( ! function_exists( 'cgm_service' ) ) { function cgm_service( string $id, string $constraint = '*' ): mixed { return cgm_core()->services()->require( $id, $constraint ); } }

// Builder-neutral helpers. These are intentionally plain PHP so builders that accept
// PHP-returned dynamic data or query arrays can consume Core without private hooks.
if ( ! function_exists( 'cgm_builder_data' ) ) { function cgm_builder_data( string $key, mixed $object = null, array $context = array() ): mixed { return cgm_data( $key, $object, $context ); } }
if ( ! function_exists( 'cgm_builder_query' ) ) { function cgm_builder_query( array|string|int $query, array $context = array() ): CGM\Core\Query\QueryResult { return cgm_query( $query, $context ); } }
if ( ! function_exists( 'cgm_builder_query_items' ) ) { function cgm_builder_query_items( array|string|int $query, array $context = array() ): array { return cgm_query( $query, $context )->items; } }
if ( ! function_exists( 'cgm_builder_query_args' ) ) { function cgm_builder_query_args( string|int $query, int $post_id = 0 ): array { $ids = cgm_query_ids( $query, array( 'post_id'=>$post_id ?: get_the_ID() ) ); return array( 'post__in'=>$ids ?: array(0), 'orderby'=>'post__in' ); } }
if ( ! function_exists( 'cgm_builder_condition' ) ) { function cgm_builder_condition( string $key, string $operator = 'EXISTS', mixed $value = '', mixed $object = null, array $context = array() ): bool { $actual=cgm_data($key,$object,$context);$op=strtoupper(trim($operator));$text=is_array($actual)?implode(', ',array_map('strval',$actual)):(string)$actual;return match($op){ 'EXISTS'=>null!==$actual&&''!==$text, 'NOT EXISTS'=>null===$actual||''===$text, '!='=>$actual!=$value, '>'=>$actual>$value, '>='=>$actual>=$value, '<'=>$actual<$value, '<='=>$actual<=$value, 'CONTAINS'=>false!==stripos($text,(string)$value), 'NOT CONTAINS'=>false===stripos($text,(string)$value), 'IN'=>in_array($actual,is_array($value)?$value:array_map('trim',explode(',',(string)$value)),false), 'NOT IN'=>!in_array($actual,is_array($value)?$value:array_map('trim',explode(',',(string)$value)),false), default=>$actual==$value }; } }
if ( ! function_exists( 'cgm_oxygen_data' ) ) { function cgm_oxygen_data( string $key, int $object_id = 0 ): mixed { return cgm_builder_data( $key, $object_id ); } }
if ( ! function_exists( 'cgm_oxygen_query' ) ) { function cgm_oxygen_query( string|int $query, int $post_id = 0 ): array { return cgm_builder_query_args( $query, $post_id ); } }
if ( ! function_exists( 'cgm_oxygen_condition' ) ) { function cgm_oxygen_condition( string $key, string $operator = 'EXISTS', mixed $value = '' ): bool { return cgm_builder_condition( $key, $operator, $value ); } }
if ( ! function_exists( 'cgm_divi_data' ) ) { function cgm_divi_data( string $key, int $object_id = 0 ): mixed { return cgm_builder_data( $key, $object_id ); } }
if ( ! function_exists( 'cgm_divi_query' ) ) { function cgm_divi_query( array|string|int $query, array $context = array() ): CGM\Core\Query\QueryResult { return cgm_builder_query( $query, $context ); } }
if ( ! function_exists( 'cgm_mosaic_data' ) ) { function cgm_mosaic_data( string $key, int $object_id = 0 ): mixed { return cgm_builder_data( $key, $object_id ); } }
if ( ! function_exists( 'cgm_mosaic_query' ) ) { function cgm_mosaic_query( array|string|int $query, array $context = array() ): CGM\Core\Query\QueryResult { return cgm_builder_query( $query, $context ); } }


if ( ! function_exists( 'cgm_builder_manifest' ) ) { function cgm_builder_manifest(): array { return array( 'version'=>CGM_CORE_API_VERSION,'content_types'=>array_values(cgm_core()->content_types()->all()),'fields'=>array_values(cgm_core()->fields()->all()),'relationships'=>array_values(cgm_core()->relationships()->all()),'dynamic_data'=>array_values(cgm_core()->dynamic_data()->all()),'saved_queries'=>cgm_core()->saved_queries()->list(),'contexts'=>cgm_core()->context()->tokens() ); } }
if ( ! function_exists( 'cgm_builder_path' ) ) { function cgm_builder_path( string $path, mixed $object = null, array $context = array() ): mixed { return cgm_data( $path, $object, $context ); } }
if ( ! function_exists( 'cgm_divi_condition' ) ) { function cgm_divi_condition( string $key, string $operator = 'EXISTS', mixed $value = '' ): bool { return cgm_builder_condition( $key, $operator, $value ); } }
if ( ! function_exists( 'cgm_mosaic_condition' ) ) { function cgm_mosaic_condition( string $key, string $operator = 'EXISTS', mixed $value = '' ): bool { return cgm_builder_condition( $key, $operator, $value ); } }

if ( ! function_exists( 'cgm_deprecated' ) ) {
    function cgm_deprecated( string $function, string $version, string $replacement = '' ): void {
        _deprecated_function( $function, $version, $replacement ?: null );
        do_action( 'cgm_core/deprecated', $function, $version, $replacement );
    }
}

/* Legacy Suite compatibility facade. It prevents older CGM plugins from fatally
 * erroring while they are migrated to the Core 3 provider/query contracts. */
require_once CGM_CORE_PATH . 'includes/legacy-suite-compat.php';
