<?php
namespace CGM\Core\Integrations\Bricks;

use CGM\Core\Plugin;
use CGM\Core\Query\SavedQueryRepository;
use CGM\Core\Contracts\BuilderAdapterInterface;

/**
 * Bricks adapter.
 *
 * Uses Bricks' public custom-query, dynamic-data and element-condition hooks.
 * Core remains the query/data owner; Bricks only renders the returned objects.
 */
final class BricksIntegration implements BuilderAdapterInterface {
    private const QUERY_PREFIX = 'cgm_query_';
    private const INLINE_QUERY = 'cgm_core_inline';

    /** @var array<string,string> */
    private array $dynamic_condition_map = array();
    /** @var array<string,string> */
    private array $query_condition_map = array();

    public function id(): string { return 'bricks'; }
    public function detected(): bool { return defined( 'BRICKS_VERSION' ) || class_exists( 'Bricks\\Query' ); }
    public function capabilities(): array { return array('saved-query-loop','inline-query-controls','dynamic-data','conditions','context','data-paths'); }

    public function __construct( private Plugin $core, private SavedQueryRepository $repo ) {}

    public function register(): void {
        add_filter( 'bricks/setup/control_options', array( $this, 'query_types' ) );
        add_filter( 'bricks/query/run', array( $this, 'run' ), 10, 2 );
        foreach ( array( 'container','block','div','section','slider','carousel','accordion','posts' ) as $element ) { add_filter( 'bricks/elements/' . $element . '/controls', array( $this, 'register_query_controls' ) ); }
        add_filter( 'bricks/query/run_fake', array( $this, 'run' ), 10, 2 );
        add_filter( 'bricks/query/loop_object', array( $this, 'loop_object' ), 10, 3 );

        add_filter( 'bricks/dynamic_tags_list', array( $this, 'tags' ) );
        add_filter( 'bricks/dynamic_data/render_tag', array( $this, 'render_tag' ), 10, 3 );
        add_filter( 'bricks/dynamic_data/render_content', array( $this, 'render_content' ), 10, 3 );

        add_filter( 'bricks/conditions/groups', array( $this, 'condition_groups' ) );
        add_filter( 'bricks/conditions/options', array( $this, 'condition_options' ) );
        add_filter( 'bricks/conditions/result', array( $this, 'condition_result' ), 10, 3 );

        // Public bridge shared by the built-in Bricks inline controls and companion extensions. The definition
        // uses the exact same CGM Query format as the WordPress visual builder.
        add_filter( 'cgm_core/bricks/inline_query', array( $this, 'run_inline_definition' ), 10, 3 );
        do_action( 'cgm_core/integration/bricks/registered', $this->core, $this->repo );
    }

    public function query_types( array $options ): array {
        if ( empty( $options['queryTypes'] ) || ! is_array( $options['queryTypes'] ) ) { return $options; }

        foreach ( $this->repo->list() as $query ) {
            $slug = sanitize_key( (string) ( $query['slug'] ?? '' ) );
            if ( ! $slug ) { continue; }
            $options['queryTypes'][ self::QUERY_PREFIX . $slug ] = sprintf(
                /* translators: %s: saved CGM query name. */
                __( 'CGM: %s', 'cgm-core' ),
                (string) ( $query['title'] ?? $slug )
            );
        }

        // The inline type is intentionally a contract rather than relying on undocumented Bricks UI internals.
        // Companion controls can store a standard definition as `cgmQueryDefinition`.
        $options['queryTypes'][ self::INLINE_QUERY ] = __( 'CGM: Inline Query', 'cgm-core' );
        return $options;
    }

    public function run( array $results, object $query_obj ): array {
        $type = (string) ( $query_obj->object_type ?? '' );
        $context = $this->context( $query_obj );

        if ( self::INLINE_QUERY === $type ) {
            $definition = $this->settings_definition( $query_obj );
            if ( is_string( $definition ) ) { return $this->core->queries()->run( $definition, $context + array( 'consumer'=>'bricks-saved-control' ) )->items; }
            if ( ! is_array( $definition ) || ! $definition ) { return array(); }
            return $this->core->queries()->run( $definition, $context + array( 'consumer'=>'bricks-inline' ) )->items;
        }

        if ( ! str_starts_with( $type, self::QUERY_PREFIX ) ) { return $results; }
        $slug = sanitize_key( substr( $type, strlen( self::QUERY_PREFIX ) ) );
        $saved = $this->repo->find( $slug );
        if ( ! $saved ) { return array(); }

        return $this->core->queries()->run(
            $saved['slug'],
            $context + array( 'consumer'=>'bricks', 'location'=>(string) ( $query_obj->element_id ?? '' ) )
        )->items;
    }

    public function loop_object( mixed $loop_object, mixed $loop_key, object $query_obj ): mixed {
        $type = (string) ( $query_obj->object_type ?? '' );
        if ( self::INLINE_QUERY === $type || str_starts_with( $type, self::QUERY_PREFIX ) ) {
            $GLOBALS['cgm_core_query_object'] = $loop_object;
        }
        return $loop_object;
    }

    public function tags( array $tags ): array {
        foreach ( $this->core->dynamic_data()->all() as $definition ) {
            $id = (string) ( $definition['id'] ?? '' );
            if ( ! $id ) { continue; }
            $tags[] = array(
                'name'  => '{cgm:' . $id . '}',
                'label' => (string) ( $definition['label'] ?? $id ),
                'group' => __( 'CGM Core', 'cgm-core' ),
            );
        }
        return $tags;
    }

    public function render_tag( mixed $value, string $tag, mixed $post ): mixed {
        if ( ! preg_match( '/^\{cgm:([^}]+)\}$/', $tag, $match ) ) { return $value; }
        $object = $this->current_object( $post );
        return $this->normalize( $this->core->dynamic_data()->resolve( $match[1], $object ) );
    }

    public function render_content( string $content, mixed $post, string $context ): string {
        $object = $this->current_object( $post );
        return preg_replace_callback(
            '/\{cgm:([a-zA-Z0-9._-]+)\}/',
            fn( array $match ): string => esc_html( (string) $this->normalize( $this->core->dynamic_data()->resolve( $match[1], $object ) ) ),
            $content
        ) ?? $content;
    }

    public function condition_groups( array $groups ): array {
        $groups[] = array( 'name'=>'cgm_core', 'label'=>__( 'CGM Core', 'cgm-core' ) );
        return $groups;
    }

    public function condition_options( array $options ): array {
        $this->dynamic_condition_map = array();
        foreach ( $this->core->dynamic_data()->all() as $definition ) {
            $id = (string) ( $definition['id'] ?? '' );
            if ( ! $id ) { continue; }
            $key = 'cgm_data_' . substr( md5( $id ), 0, 12 );
            $this->dynamic_condition_map[ $key ] = $id;
            $options[] = array(
                'key'     => $key,
                'group'   => 'cgm_core',
                'label'   => sprintf( __( 'Data: %s', 'cgm-core' ), (string) ( $definition['label'] ?? $id ) ),
                'compare' => array(
                    'type'    => 'select',
                    'options' => array(
                        '==' => __( 'is', 'cgm-core' ),
                        '!=' => __( 'is not', 'cgm-core' ),
                        '>'  => __( 'is greater than', 'cgm-core' ),
                        '>=' => __( 'is at least', 'cgm-core' ),
                        '<'  => __( 'is less than', 'cgm-core' ),
                        '<=' => __( 'is at most', 'cgm-core' ),
                        'contains' => __( 'contains', 'cgm-core' ),
                        'not_contains' => __( 'does not contain', 'cgm-core' ),
                        'exists' => __( 'exists', 'cgm-core' ),
                        'not_exists' => __( 'does not exist', 'cgm-core' ),
                    ),
                ),
                'value' => array( 'type'=>'text' ),
            );
        }

        $this->query_condition_map = array();
        foreach ( $this->repo->list() as $query ) {
            $slug = (string) ( $query['slug'] ?? '' );
            if ( ! $slug ) { continue; }
            $key = 'cgm_query_' . substr( md5( $slug ), 0, 12 );
            $this->query_condition_map[ $key ] = $slug;
            $options[] = array(
                'key'     => $key,
                'group'   => 'cgm_core',
                'label'   => sprintf( __( 'Query has results: %s', 'cgm-core' ), (string) ( $query['title'] ?? $slug ) ),
                'compare' => array( 'type'=>'select', 'options'=>array( '=='=>__( 'is', 'cgm-core' ) ) ),
                'value'   => array( 'type'=>'select', 'options'=>array( 'true'=>__( 'True', 'cgm-core' ), 'false'=>__( 'False', 'cgm-core' ) ) ),
            );
        }
        return $options;
    }

    public function condition_result( bool $result, string $condition_key, array $condition ): bool {
        // Rebuild maps for frontend requests where Bricks calls the result hook without first
        // building the options UI.
        if ( ! $this->dynamic_condition_map && ! $this->query_condition_map ) { $this->condition_options( array() ); }

        if ( isset( $this->dynamic_condition_map[ $condition_key ] ) ) {
            $actual = $this->core->dynamic_data()->resolve( $this->dynamic_condition_map[ $condition_key ], $this->current_object( null ) );
            return $this->compare( $actual, (string) ( $condition['compare'] ?? '==' ), $condition['value'] ?? '' );
        }

        if ( isset( $this->query_condition_map[ $condition_key ] ) ) {
            $has_results = $this->core->queries()->run(
                $this->query_condition_map[ $condition_key ],
                array( 'post_id'=>get_the_ID(), 'consumer'=>'bricks-condition', 'location'=>$condition_key )
            )->total > 0;
            return ( 'false' === (string) ( $condition['value'] ?? 'true' ) ) ? ! $has_results : $has_results;
        }

        return $result;
    }

    public function register_query_controls( array $controls ): array {
        $queries=array(''=>__('Choose a saved CGM Query','cgm-core'));foreach($this->repo->list() as $q)$queries[(string)$q['slug']]=(string)$q['title'];$types=array();foreach($this->core->content_types()->all() as $ct)$types[(string)$ct['id']]=(string)($ct['label']??$ct['id']);$rels=array(''=>__('None','cgm-core'));foreach($this->core->relationships()->all() as $r)$rels[(string)$r['id']]=(string)($r['label']??$r['id']);
        $controls['cgmQueryMode']=array('tab'=>'content','group'=>'query','label'=>__('CGM Query mode','cgm-core'),'type'=>'select','options'=>array('saved'=>__('Saved query','cgm-core'),'inline'=>__('Inline query','cgm-core')),'inline'=>true,'clearable'=>true);
        $controls['cgmSavedQuery']=array('tab'=>'content','group'=>'query','label'=>__('CGM saved query','cgm-core'),'type'=>'select','options'=>$queries,'required'=>array('cgmQueryMode','=','saved'));
        $controls['cgmQueryContentType']=array('tab'=>'content','group'=>'query','label'=>__('CGM content','cgm-core'),'type'=>'select','options'=>$types,'required'=>array('cgmQueryMode','=','inline'));
        $controls['cgmQueryRelationship']=array('tab'=>'content','group'=>'query','label'=>__('Relationship filter','cgm-core'),'type'=>'select','options'=>$rels,'required'=>array('cgmQueryMode','=','inline'));
        $controls['cgmQueryOperator']=array('tab'=>'content','group'=>'query','label'=>__('Operator','cgm-core'),'type'=>'select','options'=>array('='=>'=','!='=>'!=','IN'=>'IN','NOT IN'=>'NOT IN','EXISTS'=>'EXISTS','NOT EXISTS'=>'NOT EXISTS'),'required'=>array('cgmQueryMode','=','inline'));
        $controls['cgmQueryValue']=array('tab'=>'content','group'=>'query','label'=>__('Value / context token','cgm-core'),'type'=>'text','placeholder'=>'@current_post','required'=>array('cgmQueryMode','=','inline'));
        $controls['cgmQueryPath']=array('tab'=>'content','group'=>'query','label'=>__('Data path filter','cgm-core'),'type'=>'text','placeholder'=>'game.primary.developer.name','required'=>array('cgmQueryMode','=','inline'));
        $controls['cgmQueryLimit']=array('tab'=>'content','group'=>'query','label'=>__('Limit','cgm-core'),'type'=>'number','min'=>1,'max'=>100,'default'=>12,'required'=>array('cgmQueryMode','=','inline'));
        return $controls;
    }
    private function settings_definition( object $query_obj ): array|string|null {
        $settings=(array)($query_obj->settings??array());$mode=sanitize_key((string)($settings['cgmQueryMode']??''));if('saved'===$mode&&!empty($settings['cgmSavedQuery']))return sanitize_title((string)$settings['cgmSavedQuery']);if('inline'!==$mode)return null;if(!empty($settings['cgmQueryDefinition'])&&is_array($settings['cgmQueryDefinition']))return $settings['cgmQueryDefinition'];$rules=array();$path=sanitize_text_field((string)($settings['cgmQueryPath']??''));$rel=sanitize_key((string)($settings['cgmQueryRelationship']??''));$op=strtoupper((string)($settings['cgmQueryOperator']??'='));$value=$settings['cgmQueryValue']??'';if($path)$rules[]=array('type'=>'path','path'=>$path,'operator'=>$op,'value'=>$value);elseif($rel)$rules[]=array('type'=>'relationship','relationship'=>$rel,'operator'=>$op,'value'=>$value);return array('content_type'=>sanitize_key((string)($settings['cgmQueryContentType']??'post')),'filters'=>array('relation'=>'AND','rules'=>$rules),'limit'=>max(1,min(100,absint($settings['cgmQueryLimit']??12))));
    }

    public function run_inline_definition( mixed $result, array $definition, array $context = array() ): mixed {
        if ( ! $definition ) { return $result; }
        return $this->core->queries()->run( $definition, $context + array( 'consumer'=>'bricks-inline-contract' ) );
    }

    private function context( object $query_obj ): array {
        $context = array( 'post_id'=>get_the_ID() );
        if ( isset( $query_obj->settings['post_id'] ) ) { $context['post_id'] = absint( $query_obj->settings['post_id'] ); }
        if ( isset( $GLOBALS['cgm_core_query_object'] ) ) { $context['parent_query_item'] = $GLOBALS['cgm_core_query_object']; }
        return $context;
    }

    private function current_object( mixed $fallback ): mixed {
        if ( isset( $GLOBALS['cgm_core_query_object'] ) ) { return $GLOBALS['cgm_core_query_object']; }
        if ( $fallback ) { return $fallback; }
        return get_the_ID();
    }

    private function compare( mixed $actual, string $operator, mixed $expected ): bool {
        $operator = strtolower( trim( $operator ) );
        if ( is_array( $actual ) ) { $actual_string = implode( ', ', array_map( fn( $item ) => (string) $this->normalize( $item ), $actual ) ); }
        else { $actual_string = (string) $this->normalize( $actual ); }
        $expected_string = (string) $expected;
        return match ( $operator ) {
            'exists' => null !== $actual && '' !== $actual_string && array() !== $actual,
            'not_exists' => null === $actual || '' === $actual_string || array() === $actual,
            '!=' => $actual_string != $expected_string,
            '>' => is_numeric( $actual ) && is_numeric( $expected ) && (float) $actual > (float) $expected,
            '>=' => is_numeric( $actual ) && is_numeric( $expected ) && (float) $actual >= (float) $expected,
            '<' => is_numeric( $actual ) && is_numeric( $expected ) && (float) $actual < (float) $expected,
            '<=' => is_numeric( $actual ) && is_numeric( $expected ) && (float) $actual <= (float) $expected,
            'contains' => false !== stripos( $actual_string, $expected_string ),
            'not_contains' => false === stripos( $actual_string, $expected_string ),
            default => $actual_string == $expected_string,
        };
    }

    private function normalize( mixed $value ): mixed {
        if ( is_scalar( $value ) || null === $value ) { return $value; }
        if ( $value instanceof \WP_Post ) { return get_the_title( $value ); }
        if ( $value instanceof \WP_User ) { return $value->display_name; }
        if ( $value instanceof \WP_Term ) { return $value->name; }
        if ( is_array( $value ) ) { return implode( ', ', array_map( fn( $item ) => (string) $this->normalize( $item ), $value ) ); }
        return '';
    }
}
