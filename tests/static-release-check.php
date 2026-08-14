<?php
$root = dirname( __DIR__ );
$fail = array();
$count = 0;
$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );
foreach ( $iterator as $file ) {
    if ( 'php' !== $file->getExtension() ) { continue; }
    if ( str_contains( $file->getPathname(), DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR ) ) { continue; }
    ++$count;
    $code = (string) file_get_contents( $file->getPathname() );
    if ( str_contains( $code, '__return_true' ) && str_contains( $code, 'permission_callback' ) ) { $fail[] = 'Unsafe REST permission marker: ' . $file->getPathname(); }
    if ( preg_match( '/\b(eval|exec|shell_exec|passthru|system)\s*\(/', $code ) ) { $fail[] = 'Runtime execution primitive: ' . $file->getPathname(); }
}
$bootstrap = (string) file_get_contents( $root . '/cgm-core.php' );
foreach ( array(
    "CGM_CORE_VERSION', '3.0.0-beta.1",
    "CGM_CORE_API_VERSION', '3.0'",
    "CGM_CORE_QUERY_API_VERSION', '3.0'",
    "CGM_CORE_RELATIONSHIP_API_VERSION', '3.0'",
    "CGM_CORE_DYNAMIC_DATA_API_VERSION', '3.0'",
) as $marker ) { if ( ! str_contains( $bootstrap, $marker ) ) { $fail[] = 'Missing release/API marker: ' . $marker; } }

$critical = array(
    'src/Objects/ObjectResolver.php',
    'src/Objects/ObjectReference.php',
    'src/Query/Providers/PostQueryProvider.php',
    'src/Query/Providers/UserQueryProvider.php',
    'src/Query/Providers/TermQueryProvider.php',
    'src/DynamicData/TraversalResolver.php',
    'src/Relationships/RelationshipSchema.php',
    'src/Relationships/RelationshipManager.php',
    'src/Relationships/RelationshipLifecycle.php',
    'src/Configuration/ConfigurationManager.php',
    'src/Support/ApiCompatibility.php',
    'src/Multisite/MultisitePolicy.php',
    'src/Integrations/Gutenberg/GutenbergIntegration.php',
    'src/Integrations/Bricks/BricksIntegration.php',
    'src/Integrations/Elementor/ElementorIntegration.php',
    'src/Integrations/Oxygen/OxygenIntegration.php',
    'src/Integrations/Divi/DiviIntegration.php',
    'src/Integrations/Mosaic/MosaicIntegration.php',
    'src/Providers/CGMAuthors/CGMAuthorsProvider.php',
    'src/Providers/CGMGameLinker/CGMGameLinkerProvider.php',
    'src/Admin/Admin.php',
    'src/REST/BootstrapController.php',
    'src/REST/QueryController.php',
    'src/REST/RelationshipController.php',
);
foreach ( $critical as $file ) { if ( ! is_readable( $root . '/' . $file ) ) { $fail[] = 'Missing feature-lock file: ' . $file; } }

if ( is_dir( $root . '/src/Entity' ) ) { $fail[] = 'Obsolete duplicate Entity subsystem is present.'; }
if ( is_file( $root . '/src/Multisite/NetworkPolicy.php' ) ) { $fail[] = 'Obsolete duplicate NetworkPolicy is present.'; }

$post_provider = (string) file_get_contents( $root . '/src/Query/Providers/PostQueryProvider.php' );
if ( preg_match( '/posts_per_page[\'\"]?\s*=>\s*-1/', $post_provider ) ) { $fail[] = 'Post query planner regressed to unbounded posts_per_page=-1.'; }
foreach ( array( 'COUNT(DISTINCT p.ID)', 'LIMIT %d OFFSET %d', 'compile_group', 'sql_condition' ) as $marker ) {
    if ( ! str_contains( $post_provider, $marker ) ) { $fail[] = 'Post query planner missing marker: ' . $marker; }
}

$relationship = (string) file_get_contents( $root . '/src/Relationships/RelationshipManager.php' );
foreach ( array( 'public_endpoint_allowed', 'replace_for_object', 'matching_source_ids', 'sql_condition', 'deletion_blockers', 'purge_object', 'last_error' ) as $marker ) {
    if ( ! str_contains( $relationship, $marker ) ) { $fail[] = 'Relationship platform missing marker: ' . $marker; }
}


$lifecycle = (string) file_get_contents( $root . '/src/Relationships/RelationshipLifecycle.php' );
foreach ( array( 'pre_delete_post', 'before_delete_post', 'map_meta_cap', 'delete_user', 'pre_delete_term', 'relationship.object_deleted' ) as $marker ) {
    if ( ! str_contains( $lifecycle, $marker ) ) { $fail[] = 'Relationship lifecycle missing marker: ' . $marker; }
}
$schema = (string) file_get_contents( $root . '/src/Relationships/RelationshipSchema.php' );
foreach ( array( 'validate_item', 'missing_metadata:', 'invalid_metadata:', 'public_role' ) as $marker ) {
    if ( ! str_contains( $schema, $marker ) ) { $fail[] = 'Relationship schema validation missing marker: ' . $marker; }
}
$dynamic_rest = (string) file_get_contents( $root . '/src/REST/DynamicDataController.php' );
if ( ! str_contains( $dynamic_rest, "dynamic['public']" ) ) { $fail[] = 'Dynamic data REST does not gate public keys.'; }
$provider_registry = (string) file_get_contents( $root . '/src/Registry/ProviderRegistry.php' );
foreach ( array( 'suggested_missing', 'optional_missing', 'api_incompatible' ) as $marker ) {
    if ( ! str_contains( $provider_registry, $marker ) ) { $fail[] = 'Provider compatibility report missing marker: ' . $marker; }
}

$config = (string) file_get_contents( $root . '/src/Configuration/ConfigurationManager.php' );
foreach ( array( 'preview(', 'snapshot(', 'rollback(', 'recover_interrupted_import', 'register_relationship' ) as $marker ) {
    if ( ! str_contains( $config, $marker ) ) { $fail[] = 'Configuration platform missing marker: ' . $marker; }
}

$bricks = (string) file_get_contents( $root . '/src/Integrations/Bricks/BricksIntegration.php' );
foreach ( array( 'bricks/query/run', 'bricks/dynamic_tags_list', 'bricks/conditions/groups', 'bricks/conditions/options', 'bricks/conditions/result' ) as $marker ) {
    if ( ! str_contains( $bricks, $marker ) ) { $fail[] = 'Bricks adapter missing public hook: ' . $marker; }
}

$editor = (string) file_get_contents( $root . '/assets/js/editor.js' );
foreach ( array( 'PluginPostStatusInfo', 'Make primary', 'Move up', 'Move down' ) as $marker ) {
    if ( ! str_contains( $editor, $marker ) ) { $fail[] = 'Gutenberg relationship editor missing marker: ' . $marker; }
}

$builder = (string) file_get_contents( $root . '/src/Admin/components/query-builder.tsx' );
foreach ( array( 'Group', 'NOT EXISTS', 'cgm-context-tokens', 'Add sort' ) as $marker ) {
    if ( ! str_contains( $builder, $marker ) ) { $fail[] = 'Visual query builder missing marker: ' . $marker; }
}

echo "Scanned {$count} production PHP files.\n";
if ( $fail ) { fwrite( STDERR, implode( "\n", $fail ) . "\n" ); exit( 1 ); }
echo "Feature-lock static release assertions passed.\n";
