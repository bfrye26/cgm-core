<?php
namespace CGM\Core;

use CGM\Core\Registry\{
    ProviderRegistry, ContentTypeRegistry, FieldRegistry, ServiceRegistry,
    EditorControlRegistry, BuilderRegistry, QueryProviderRegistry, IndexRegistry, ViewModeRegistry, FacetRegistry
};
use CGM\Core\Events\EventBus;
use CGM\Core\Context\ContextResolver;
use CGM\Core\DynamicData\{DynamicDataRegistry,TraversalResolver};
use CGM\Core\Objects\ObjectResolver;
use CGM\Core\Relationships\{RelationshipManager, CoreRelationshipStore, NetworkRelationshipStore, ConfiguredRelationshipRepository, RelationshipFeatures, RelationshipLifecycle};
use CGM\Core\Query\{QueryEngine, SavedQueryRepository, QueryValidator};
use CGM\Core\Providers\WordPress\WordPressProvider;
use CGM\Core\Providers\ACF\ACFProvider;
use CGM\Core\Providers\MetaBox\MetaBoxProvider;
use CGM\Core\Providers\CGMAuthors\CGMAuthorsProvider;
use CGM\Core\Providers\CGMGameLinker\CGMGameLinkerProvider;
use CGM\Core\Providers\CGMSuite\CGMSuiteMembersProvider;
use CGM\Core\Providers\WooCommerce\WooCommerceProvider;
use CGM\Core\Providers\Yoast\YoastProvider;
use CGM\Core\Providers\Typesense\TypesenseProvider;
use CGM\Core\Integrations\Gutenberg\GutenbergIntegration;
use CGM\Core\Integrations\Bricks\BricksIntegration;
use CGM\Core\Integrations\Elementor\ElementorIntegration;
use CGM\Core\Integrations\Oxygen\OxygenIntegration;
use CGM\Core\Integrations\Divi\DiviIntegration;
use CGM\Core\Integrations\Mosaic\MosaicIntegration;
use CGM\Core\Integrations\Shortcodes\ShortcodesIntegration;
use CGM\Core\Integrations\CGMSuite\SuiteIntegration;
use CGM\Core\REST\RestRegistrar;
use CGM\Core\Admin\Admin;
use CGM\Core\Admin\ListTables;
use CGM\Core\Cache\{Cache, Invalidator};
use CGM\Core\Support\VisibilityPolicy;
use CGM\Core\Configuration\ConfigurationManager;
use CGM\Core\Health\SiteHealth;
use CGM\Core\CLI\Commands;
use CGM\Core\Multisite\MultisitePolicy;
use CGM\Core\Support\ApiCompatibility;
use CGM\Core\Telemetry\Telemetry;
use CGM\Core\Index\IndexManager;
use CGM\Core\Rules\RuleEngine;
use CGM\Core\Workflow\WorkflowManager;
use CGM\Core\Support\Notifications;
use CGM\Core\Support\Pathauto;
use CGM\Core\Support\Locale;
use CGM\Core\Search\SearchManager;
use CGM\Core\Graph\GraphManager;
final class Plugin {
    private static ?self $instance = null;
    private bool $booted = false;
    private ProviderRegistry $providers;
    private ContentTypeRegistry $content_types;
    private FieldRegistry $fields;
    private ServiceRegistry $services;
    private EditorControlRegistry $editor_controls;
    private BuilderRegistry $builders;
    private QueryProviderRegistry $query_providers;
    private IndexRegistry $indexes;
    private RuleEngine $rules;
    private WorkflowManager $workflow;
    private ViewModeRegistry $view_modes;
    private SearchManager $search;
    private FacetRegistry $facets;
    private GraphManager $graph;
    private Notifications $notifications;
    private Pathauto $pathauto;
    private Locale $locale;
    private EventBus $events;
    private ContextResolver $context;
    private DynamicDataRegistry $dynamic_data;
    private ObjectResolver $objects;
    private RelationshipManager $relationships;
    private SavedQueryRepository $saved_queries;
    private QueryEngine $queries;
    private Cache $cache;
    private ConfiguredRelationshipRepository $configured_relationships;
    private ConfigurationManager $configuration;
    private MultisitePolicy $multisite;
    private ApiCompatibility $api_compatibility;

    public static function instance(): self { return self::$instance ??= new self(); }

    private function __construct() {
        $this->providers = new ProviderRegistry();
        $this->content_types = new ContentTypeRegistry();
        $this->fields = new FieldRegistry();
        $this->services = new ServiceRegistry();
        $this->editor_controls = new EditorControlRegistry();
        $this->builders = new BuilderRegistry();
        $this->query_providers = new QueryProviderRegistry();
        $this->indexes = new IndexRegistry();
        $this->rules = new RuleEngine( $this );
        $this->workflow = new WorkflowManager( $this );
        $this->view_modes = new ViewModeRegistry();
        $this->search = new SearchManager( $this );
        $this->facets = new FacetRegistry();
        $this->graph = new GraphManager( $this );
        $this->notifications = new Notifications();
        $this->pathauto = new Pathauto( $this );
        $this->locale = new Locale();
        $this->events = new EventBus();
        $this->context = new ContextResolver();
        $this->dynamic_data = new DynamicDataRegistry();
        $this->cache = new Cache();
        $this->objects = new ObjectResolver( $this->content_types );
        $this->relationships = new RelationshipManager( new CoreRelationshipStore(), $this->events, $this->cache, new VisibilityPolicy() );
        $this->relationships->set_object_resolver( $this->objects );
        if ( is_multisite() ) { $this->relationships->register_store( 'network', new NetworkRelationshipStore() ); }
        $this->dynamic_data->set_fallback_resolver( array( new TraversalResolver( $this ), 'resolve' ) );
        $this->configured_relationships = new ConfiguredRelationshipRepository();
        $this->saved_queries = new SavedQueryRepository();
        $this->queries = new QueryEngine( $this, $this->saved_queries, new QueryValidator(), $this->cache );
        $this->configuration = new ConfigurationManager( $this->saved_queries, $this->configured_relationships );
        $this->multisite = new MultisitePolicy();
        $this->api_compatibility = new ApiCompatibility();
        $this->services->register( 'multisite.policy', $this->multisite, array( 'version'=>'1.0', 'provider'=>'cgm-core', 'public'=>false ) );
        $this->services->register( 'api.compatibility', $this->api_compatibility, array( 'version'=>'2.0', 'provider'=>'cgm-core', 'public'=>false ) );
    }

    public static function activate(): void {
        self::install_schema(); self::install_caps();
        if ( is_multisite() ) { NetworkRelationshipStore::install(); }
        update_option( 'cgm_core_schema_version', CGM_CORE_SCHEMA_VERSION, false );
    }
    public static function deactivate(): void {}

    private static function install_schema(): void {
        global $wpdb; $table = $wpdb->prefix . 'cgm_core_relationships'; $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            relationship_key VARCHAR(100) NOT NULL,
            source_type VARCHAR(100) NOT NULL,
            source_id BIGINT UNSIGNED NOT NULL,
            target_type VARCHAR(100) NOT NULL,
            target_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(100) NOT NULL DEFAULT '',
            sort_order INT NOT NULL DEFAULT 0,
            is_primary TINYINT(1) NOT NULL DEFAULT 0,
            meta LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_rel (relationship_key,source_type,source_id,target_type,target_id,role),
            KEY source_lookup (relationship_key,source_type,source_id),
            KEY target_lookup (relationship_key,target_type,target_id),
            KEY primary_lookup (relationship_key,source_type,source_id,is_primary)
        ) {$charset};" );
    }

    private static function install_caps(): void {
        $role = get_role( 'administrator' ); if ( ! $role ) { return; }
        foreach ( array( 'manage_cgm_core','manage_cgm_queries','manage_cgm_relationships','manage_cgm_configuration','inspect_cgm_core','inspect_cgm_data' ) as $cap ) { $role->add_cap( $cap ); }
    }


    private function maybe_upgrade(): void {
        $stored = (string) get_option( 'cgm_core_schema_version', '' );
        if ( $stored !== (string) CGM_CORE_SCHEMA_VERSION ) {
            self::install_schema();
            self::install_caps();
            update_option( 'cgm_core_schema_version', CGM_CORE_SCHEMA_VERSION, false );
            do_action( 'cgm_core/schema_upgraded', $stored, (string) CGM_CORE_SCHEMA_VERSION );
        }
    }

    public function boot(): void {
        if ( $this->booted ) { return; } $this->booted = true;
        $this->maybe_upgrade();
        ( new Invalidator( $this->cache ) )->register();
        ( new RelationshipLifecycle( $this->relationships, $this->events ) )->register();
        $this->configuration->register_recovery();
        $this->multisite->register();
        $this->events->register( 'relationship.changed', '1.0', array( 'relationship','source_id','items' ) );
        $this->events->register( 'relationship.delete.cascade', '1.0', array( 'relationship','object','schema','store' ) );
        $this->events->register( 'relationship.object_deleted', '1.0', array( 'object','result' ) );
        $this->events->register( 'content.changed', '1.0', array( 'object_type','object_id' ) );

        add_action( 'init', array( $this, 'register_native' ), 20 );
        add_action( 'init', array( $this, 'register_optional_providers' ), 70 );
        add_action( 'init', array( $this, 'register_metabox' ), 90 );
        add_action( 'init', array( $this, 'register_relationship_features' ), 130 );
        add_action( 'init', array( $this, 'finalize_providers' ), 250 );
        add_action( 'acf/init', array( $this, 'register_acf' ), 100 );
        add_action( 'rest_api_init', fn() => ( new RestRegistrar( $this, $this->saved_queries, $this->configuration ) )->register() );

        ( new GutenbergIntegration( $this ) )->register();
        ( new ShortcodesIntegration( $this ) )->register();
        ( new SuiteIntegration( $this ) )->register();
        $this->register_builders();
        ( new Admin( $this ) )->register();
        ( new ListTables( $this ) )->register();
        ( new SiteHealth( $this ) )->register();
        ( new Commands( $this ) )->register();
        ( new Telemetry() )->register();
        ( new IndexManager( $this ) )->register();
        $this->rules->register();
        $this->workflow->register();
        do_action( 'cgm_core/boot', $this );
    }

    private function register_builders(): void {
        $this->builders->register( array(
            'id'=>'gutenberg','label'=>'Gutenberg','detected'=>true,'integration_level'=>'native',
            'capabilities'=>array('editor-summary','query-loop-block','block-bindings','dynamic-data','relationships','context')
        ) );

        // Bricks is a theme, so its constants/classes only exist after the theme's
        // functions.php loads. Detect and register the adapter late so `detected`
        // and the query/dynamic-data/condition hooks are correct.
        add_action( 'after_setup_theme', function(): void {
            $bricks = defined( 'BRICKS_VERSION' ) || class_exists( 'Bricks\\Query' );
            if ( $bricks ) { ( new BricksIntegration( $this, $this->saved_queries ) )->register(); }
            $this->builders->register( array(
                'id'=>'bricks','label'=>'Bricks','detected'=>$bricks,'integration_level'=>'native-public-api',
                'capabilities'=>array('saved-query-loop','inline-query-controls','dynamic-data','conditions','context','data-paths')
            ) );
        }, 100 );
        $this->builders->register( array(
            'id'=>'bricks','label'=>'Bricks','detected'=>false,'integration_level'=>'native-public-api',
            'capabilities'=>array('saved-query-loop','inline-query-controls','dynamic-data','conditions','context','data-paths')
        ) );

        add_action( 'elementor/init', function(): void {
            ( new ElementorIntegration( $this, $this->saved_queries ) )->register();
            $this->builders->register( array(
                'id'=>'elementor','label'=>'Elementor','detected'=>true,'integration_level'=>'native-public-api',
                'capabilities'=>array('dynamic-tag','saved-query-id','traversal','context','builder-manifest')
            ) );
        }, 20 );
        $this->builders->register( array(
            'id'=>'elementor','label'=>'Elementor','detected'=>defined('ELEMENTOR_VERSION'),'integration_level'=>'native-public-api',
            'capabilities'=>array('dynamic-tag','saved-query-id','traversal','context','builder-manifest')
        ) );

        $oxygen = defined( 'CT_VERSION' ) || defined( 'OXYGEN_VSB_VERSION' ) || class_exists( 'Oxygen\\Builder' );
        ( new OxygenIntegration( $this, $this->saved_queries ) )->register();
        $this->builders->register( array(
            'id'=>'oxygen','label'=>'Oxygen','detected'=>$oxygen,'integration_level'=>'public-bridge',
            'capabilities'=>array('query-bridge','dynamic-data-bridge','conditions-bridge','traversal','context','shortcodes')
        ) );

        // Divi is a theme; detect it after the theme loads like Bricks above.
        add_action( 'after_setup_theme', function(): void {
            $divi = defined( 'ET_BUILDER_VERSION' ) || function_exists( 'et_setup_theme' );
            if ( $divi ) { ( new DiviIntegration( $this, $this->saved_queries ) )->register(); }
            $this->builders->register( array(
                'id'=>'divi','label'=>'Divi','detected'=>$divi,'integration_level'=>'public-bridge',
                'capabilities'=>array('dynamic-data-bridge','saved-query-bridge','condition-bridge','shortcodes','context')
            ) );
        }, 100 );
        $this->builders->register( array(
            'id'=>'divi','label'=>'Divi','detected'=>false,'integration_level'=>'public-bridge',
            'capabilities'=>array('dynamic-data-bridge','saved-query-bridge','condition-bridge','shortcodes','context')
        ) );

        $mosaic = defined( 'MOSAIC_BUILDER_VERSION' ) || class_exists( 'Mosaic\\Builder' );
        ( new MosaicIntegration( $this, $this->saved_queries ) )->register();
        $this->builders->register( array(
            'id'=>'mosaic','label'=>'Mosaic Builder','detected'=>$mosaic,'integration_level'=>'public-bridge',
            'capabilities'=>array('dynamic-source-bridge','query-source-bridge','condition-bridge','shortcodes','context')
        ) );
    }

    public function register_native(): void {
        $this->saved_queries->register_post_type();
        ( new WordPressProvider() )->register( $this );
        $this->register_network_defaults();
        $this->configuration->register_code_configuration();
        $this->configured_relationships->register( $this->relationships );
        do_action( 'cgm_core/register_query_providers', $this->query_providers, $this );
        do_action( 'cgm_core/register', $this );
    }

    private function register_network_defaults(): void {
        $defaults = $this->multisite->network_defaults();
        if ( ! $defaults ) { return; }
        foreach ( (array) ( $defaults['queries'] ?? array() ) as $query ) {
            if ( ! is_array( $query ) || empty( $query['slug'] ) || ! isset( $query['definition'] ) || ! is_array( $query['definition'] ) ) { continue; }
            $slug = sanitize_title( (string) $query['slug'] );
            // Site-local definitions always win over a network fallback.
            if ( $this->saved_queries->find( $slug ) ) { continue; }
            $this->saved_queries->register_code( $slug, (array) $query['definition'], array(
                'title'  => (string) ( $query['title'] ?? $slug ),
                'public' => ! empty( $query['public'] ),
                'source' => 'network-default',
            ) );
        }
        $local_relationships = $this->configured_relationships->stored();
        foreach ( (array) ( $defaults['relationships'] ?? array() ) as $relationship ) {
            if ( ! is_array( $relationship ) || empty( $relationship['id'] ) ) { continue; }
            $id = sanitize_key( (string) $relationship['id'] );
            if ( isset( $local_relationships[ $id ] ) ) { continue; }
            $relationship['provider'] = 'network-default';
            $relationship['managed_by'] = 'network-default';
            $this->configured_relationships->register_code( $relationship );
        }
        do_action( 'cgm_core/network_defaults_registered', $defaults, $this );
    }

    public function register_optional_providers(): void { ( new CGMAuthorsProvider() )->register( $this ); ( new CGMGameLinkerProvider() )->register( $this ); ( new CGMSuiteMembersProvider() )->register( $this ); ( new WooCommerceProvider() )->register( $this ); ( new YoastProvider() )->register( $this ); ( new TypesenseProvider() )->register( $this ); }
    public function register_acf(): void { ( new ACFProvider() )->register( $this ); }
    public function register_metabox(): void { ( new MetaBoxProvider() )->register( $this ); }
    public function register_relationship_features(): void { ( new RelationshipFeatures( $this ) )->register(); }
    public function finalize_providers(): void { $this->providers->finalize(); do_action( 'cgm_core/feature_registry_ready', $this ); }

    public function providers(): ProviderRegistry { return $this->providers; }
    public function content_types(): ContentTypeRegistry { return $this->content_types; }
    public function fields(): FieldRegistry { return $this->fields; }
    public function services(): ServiceRegistry { return $this->services; }
    public function editor_controls(): EditorControlRegistry { return $this->editor_controls; }
    public function builders(): BuilderRegistry { return $this->builders; }
    public function query_providers(): QueryProviderRegistry { return $this->query_providers; }
    public function indexes(): IndexRegistry { return $this->indexes; }
    public function rules(): RuleEngine { return $this->rules; }
    public function workflow(): WorkflowManager { return $this->workflow; }
    public function view_modes(): ViewModeRegistry { return $this->view_modes; }
    public function search(): SearchManager { return $this->search; }
    public function facets(): FacetRegistry { return $this->facets; }
    public function graph(): GraphManager { return $this->graph; }
    public function notifications(): Notifications { return $this->notifications; }
    public function pathauto(): Pathauto { return $this->pathauto; }
    public function locale(): Locale { return $this->locale; }
    public function events(): EventBus { return $this->events; }
    public function context(): ContextResolver { return $this->context; }
    public function dynamic_data(): DynamicDataRegistry { return $this->dynamic_data; }
    public function objects(): ObjectResolver { return $this->objects; }
    public function relationships(): RelationshipManager { return $this->relationships; }
    public function queries(): QueryEngine { return $this->queries; }
    public function saved_queries(): SavedQueryRepository { return $this->saved_queries; }
    public function cache(): Cache { return $this->cache; }
    public function configuration(): ConfigurationManager { return $this->configuration; }
    public function configured_relationships(): ConfiguredRelationshipRepository { return $this->configured_relationships; }
    public function multisite(): MultisitePolicy { return $this->multisite; }
    public function api_compatibility(): ApiCompatibility { return $this->api_compatibility; }
}
