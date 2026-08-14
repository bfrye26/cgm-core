<?php
$root = dirname( __DIR__ );
$required = array(
    'cgm-core.php',
    'src/Plugin.php',
    'src/Registry/ProviderRegistry.php',
    'src/Objects/ObjectResolver.php',
    'src/Query/QueryEngine.php',
    'src/Query/Providers/PostQueryProvider.php',
    'src/Query/Providers/UserQueryProvider.php',
    'src/Query/Providers/TermQueryProvider.php',
    'src/Relationships/RelationshipManager.php',
    'src/Relationships/CoreRelationshipStore.php',
    'src/Relationships/RelationshipLifecycle.php',
    'src/DynamicData/TraversalResolver.php',
    'src/Configuration/ConfigurationManager.php',
    'src/Providers/WordPress/WordPressProvider.php',
    'src/Providers/ACF/ACFProvider.php',
    'src/Providers/MetaBox/MetaBoxProvider.php',
    'src/Providers/CGMAuthors/CGMAuthorsProvider.php',
    'src/Providers/CGMGameLinker/CGMGameLinkerProvider.php',
    'src/Integrations/Gutenberg/GutenbergIntegration.php',
    'src/Integrations/Bricks/BricksIntegration.php',
    'src/Integrations/Elementor/ElementorIntegration.php',
    'src/Integrations/Oxygen/OxygenIntegration.php',
    'src/Integrations/Divi/DiviIntegration.php',
    'src/Integrations/Mosaic/MosaicIntegration.php',
    'src/Admin/Admin.php',
    'src/REST/BootstrapController.php',
    'src/REST/QueryController.php',
    'src/REST/RelationshipController.php',
    'src/REST/RestRegistrar.php',
    'src/Health/SiteHealth.php',
    'src/CLI/Commands.php',
    'src/admin/App.tsx',
    'src/admin/main.tsx',
    'src/admin/components/AppLayout.tsx',
    'src/admin/components/query-builder.tsx',
    'src/admin/routes/Queries.tsx',
    'src/admin/routes/Relationships.tsx',
    'assets/js/editor.js',
    'build/admin.js',
    'build/admin.css',
    'blocks/query-loop/block.json',
    'blocks/dynamic-value/block.json',
);
$missing = array();
foreach ( $required as $file ) { if ( ! is_readable( $root . '/' . $file ) ) { $missing[] = $file; } }
if ( $missing ) { fwrite( STDERR, "Missing required files:\n" . implode( "\n", $missing ) . "\n" ); exit( 1 ); }
foreach ( array( 'blocks/query-loop/block.json','blocks/dynamic-value/block.json','composer.json' ) as $json ) {
    if ( null === json_decode( (string) file_get_contents( $root . '/' . $json ), true ) ) { fwrite( STDERR, "Invalid JSON: {$json}\n" ); exit(1); }
}
echo "Feature-lock package structure smoke passed.\n";
