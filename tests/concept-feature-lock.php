<?php
$root=dirname(__DIR__);
$required=[
'cgm-core.php',
'src/Registry/ProviderRegistry.php','src/Registry/ContentTypeRegistry.php','src/Registry/FieldRegistry.php','src/Registry/ServiceRegistry.php','src/Registry/QueryProviderRegistry.php',
'src/Objects/ObjectReference.php','src/Objects/ObjectResolver.php','src/Relationships/RelationshipSchema.php','src/Relationships/RelationshipManager.php','src/Relationships/RelationshipLifecycle.php',
'src/Query/QueryEngine.php','src/Query/QueryPlan.php','src/Query/SavedQueryRepository.php','src/Context/ContextResolver.php','src/DynamicData/TraversalResolver.php','src/Events/EventBus.php',
'src/Configuration/ConfigurationManager.php','src/Multisite/MultisitePolicy.php','src/Cache/Cache.php','src/REST/RestRegistrar.php','src/Health/SiteHealth.php','src/CLI/Commands.php',
'src/Integrations/Gutenberg/GutenbergIntegration.php','src/Integrations/Bricks/BricksIntegration.php','src/Integrations/Elementor/ElementorIntegration.php','src/Integrations/Oxygen/OxygenIntegration.php','src/Integrations/Divi/DiviIntegration.php','src/Integrations/Mosaic/MosaicIntegration.php',
'src/Providers/CGMAuthors/CGMAuthorsProvider.php','src/Providers/CGMGameLinker/CGMGameLinkerProvider.php',
'docs/CONCEPT-FEATURE-LOCK.md','docs/PROVIDER-SDK.md','docs/QUERY-FORMAT.md','docs/RELATIONSHIPS.md','docs/CONFIGURATION.md'
];
$missing=[];foreach($required as $f)if(!is_file($root.'/'.$f))$missing[]=$f;
if($missing){fwrite(STDERR,"Missing concept feature-lock files:\n- ".implode("\n- ",$missing)."\n");exit(1);} 
$bootstrap=file_get_contents($root.'/cgm-core.php');
foreach(['CGM_CORE_API_VERSION\', \'3.0','cgm_register_saved_query','cgm_register_relationship_definition','cgm_register_event_contract','cgm_register_context','cgm_register_query_provider','cgm_builder_condition'] as $needle){if(strpos($bootstrap,$needle)===false){fwrite(STDERR,"Missing public API marker: $needle\n");exit(1);}}
$concept=file_get_contents($root.'/docs/CONCEPT-FEATURE-LOCK.md');
foreach(['Provider','Relationship','Query','Gutenberg','Bricks','Elementor','Oxygen','Divi','Mosaic','Multisite','Configuration'] as $needle){if(stripos($concept,$needle)===false){fwrite(STDERR,"Concept matrix missing: $needle\n");exit(1);}}
echo "Concept feature-lock matrix passed (".count($required)." required artifacts).\n";
