<?php
namespace CGM\Core\REST;

use CGM\Core\Plugin;
use CGM\Core\Objects\ObjectReference;

final class RelationshipController extends BaseController {
    public function __construct( private Plugin $core ) {}

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/relationships/definitions', array(
            'methods'=>\WP_REST_Server::CREATABLE, 'callback'=>array($this,'save_definitions'),
            'permission_callback'=>fn()=>current_user_can('manage_cgm_relationships')&&$this->rest_nonce_ok(),
            'args'=>array('rows'=>array('required'=>true,'type'=>'array','maxItems'=>500)),
        ) );
        register_rest_route( $this->namespace, '/relationships/(?P<relationship>[a-z0-9_-]+)/(?P<source_id>\d+)', array(
            array(
                'methods'=>\WP_REST_Server::READABLE, 'callback'=>array($this,'get'), 'permission_callback'=>array($this,'can_read'),
                'args'=>$this->source_args(),
            ),
            array(
                'methods'=>\WP_REST_Server::EDITABLE, 'callback'=>array($this,'replace'), 'permission_callback'=>array($this,'can_edit'),
                'args'=>array_merge($this->source_args(),array('items'=>array('required'=>true,'type'=>'array','maxItems'=>500,'items'=>array('type'=>array('object','integer'))))),
            ),
        ) );
        register_rest_route( $this->namespace, '/relationships/(?P<relationship>[a-z0-9_-]+)/reverse/(?P<target_id>\d+)', array(
            'methods'=>\WP_REST_Server::READABLE, 'callback'=>array($this,'reverse'), 'permission_callback'=>array($this,'can_reverse'),
            'args'=>array(
                'relationship'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_key'),
                'target_id'=>array('required'=>true,'type'=>'integer','minimum'=>1,'sanitize_callback'=>'absint'),
                'target_type'=>array('type'=>'string','sanitize_callback'=>'sanitize_key'),
                'public_only'=>array('type'=>'boolean','default'=>false),
            ),
        ) );
    }

    private function source_args(): array {
        return array(
            'relationship'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_key'),
            'source_id'=>array('required'=>true,'type'=>'integer','minimum'=>1,'sanitize_callback'=>'absint'),
            'source_type'=>array('type'=>'string','sanitize_callback'=>'sanitize_key'),
            'public_only'=>array('type'=>'boolean','default'=>false),
        );
    }

    public function can_read( \WP_REST_Request $r ): bool {
        $rel=(string)$r['relationship']; $source=absint($r['source_id']); $type=sanitize_key((string)$r->get_param('source_type'));
        if((bool)$r->get_param('public_only'))return $this->core->relationships()->public_endpoint_allowed($rel,$source,false,$type);
        return $this->core->relationships()->can_read($rel,$source,$type);
    }
    public function can_edit( \WP_REST_Request $r ): bool {
        return $this->rest_nonce_ok() && $this->core->relationships()->can_assign((string)$r['relationship'],absint($r['source_id']),sanitize_key((string)$r->get_param('source_type')));
    }
    public function can_reverse( \WP_REST_Request $r ): bool {
        $rel=(string)$r['relationship'];$target=absint($r['target_id']);$type=sanitize_key((string)$r->get_param('target_type'));
        if((bool)$r->get_param('public_only'))return $this->core->relationships()->public_endpoint_allowed($rel,$target,true,$type);
        // Unprivileged reverse reads must pass the relationship's read cap;
        // rows are additionally filtered by target readability in get_reverse().
        return current_user_can('inspect_cgm_data')||$this->core->relationships()->can_read($rel);
    }

    public function get( \WP_REST_Request $r ): \WP_REST_Response {
        $rel=(string)$r['relationship'];$source=absint($r['source_id']);$type=sanitize_key((string)$r->get_param('source_type'));$public=(bool)$r->get_param('public_only')||!$this->can_query();
        return rest_ensure_response(array('schema'=>$this->schema($rel,$public),'source'=>array('content_type'=>$type,'id'=>$source),'items'=>$this->core->relationships()->get($rel,$source,array('public_only'=>$public,'source_type'=>$type))));
    }
    public function reverse( \WP_REST_Request $r ): \WP_REST_Response {
        $rel=(string)$r['relationship'];$target=absint($r['target_id']);$public=(bool)$r->get_param('public_only')||!$this->can_query();
        return rest_ensure_response(array('schema'=>$this->schema($rel,$public),'items'=>$this->core->relationships()->get_reverse($rel,$target,array('public_only'=>$public))));
    }
    private function schema( string $relationship, bool $public ): ?array {
        $d=$this->core->relationships()->get_type($relationship);if(!$d||!$public)return $d;
        $metadata=array_filter((array)($d['metadata_schema']??array()),static fn($def)=>!empty($def['public']));
        return array('id'=>$d['id'],'label'=>$d['label'],'reverse_label'=>$d['reverse_label'],'source_type'=>$d['source_type'],'target_type'=>$d['target_type'],'multiple'=>!empty($d['multiple']),'ordered'=>!empty($d['ordered']),'primary'=>!empty($d['primary']),'roles'=>!empty($d['public_role'])?(array)$d['roles']:array(),'metadata_schema'=>$metadata,'public'=>true);
    }

    public function replace( \WP_REST_Request $r ): \WP_REST_Response {
        $rel=(string)$r['relationship'];$source=absint($r['source_id']);$type=sanitize_key((string)$r->get_param('source_type'));
        $definition=$this->core->relationships()->get_type($rel);if(!$definition)return new \WP_REST_Response(array('code'=>'relationship_not_found','message'=>__('Relationship not found.','cgm-core')),404);
        if(!$type)$type='*'===(string)($definition['source_type']??'')?'':(string)$definition['source_type'];
        $ok=$type?$this->core->relationships()->replace_for_object($rel,new ObjectReference($type,$source),(array)$r->get_param('items')):$this->core->relationships()->replace($rel,$source,(array)$r->get_param('items'));
        if(!$ok){$error=$this->core->relationships()->last_error();return new \WP_REST_Response(array('success'=>false,'code'=>$error?->get_error_code()?:'relationship_write_failed','message'=>$error?->get_error_message()?:__('Relationship could not be saved.','cgm-core'),'data'=>$error?->get_error_data()),400);}
        return rest_ensure_response(array('success'=>true,'items'=>$this->core->relationships()->get($rel,$source,array('source_type'=>$type))));
    }

    /** Persist the WordPress-UI-managed relationship model (Core-owned schema). */
    public function save_definitions( \WP_REST_Request $r ): \WP_REST_Response {
        $clean = array();
        foreach ( (array) $r->get_param( 'rows' ) as $row ) {
            if ( ! is_array( $row ) ) { continue; }
            $id = sanitize_key( (string) ( $row['id'] ?? '' ) );
            if ( ! $id ) { continue; }
            $metadata = array();
            foreach ( (array) ( $row['metadata_schema'] ?? array() ) as $key => $meta_row ) {
                if ( ! is_array( $meta_row ) ) { continue; }
                $key = sanitize_key( (string) $key );
                if ( ! $key ) { continue; }
                $options = is_array( $meta_row['options'] ?? null ) ? $meta_row['options'] : array();
                $clean_options = array();
                foreach ( $options as $value => $label ) {
                    $clean_options[ sanitize_text_field( (string) $value ) ] = sanitize_text_field( (string) $label );
                }
                $metadata[ $key ] = array(
                    'label'    => sanitize_text_field( (string) ( $meta_row['label'] ?? $key ) ),
                    'type'     => sanitize_key( (string) ( $meta_row['type'] ?? 'string' ) ),
                    'options'  => $clean_options,
                    'public'   => ! empty( $meta_row['public'] ),
                    'required' => ! empty( $meta_row['required'] ),
                );
            }
            $source_type = sanitize_key( (string) ( $row['source_type'] ?? 'post' ) );
            if ( '*' === (string) ( $row['source_type'] ?? '' ) ) { $source_type = '*'; }
            $source_types = array_values( array_filter( array_map( 'sanitize_key', (array) ( $row['source_types'] ?? array() ) ) ) );
            if ( '*' !== $source_type && ! in_array( $source_type, $source_types, true ) ) { $source_types[] = $source_type; }
            $delete_behavior = sanitize_key( (string) ( $row['delete_behavior'] ?? 'detach' ) );
            if ( ! in_array( $delete_behavior, array( 'detach', 'restrict', 'cascade' ), true ) ) { $delete_behavior = 'detach'; }
            $clean[] = array(
                'id'                => $id,
                'label'             => sanitize_text_field( (string) ( $row['label'] ?? $id ) ),
                'reverse_label'     => sanitize_text_field( (string) ( $row['reverse_label'] ?? 'Related content' ) ),
                'source_type'       => $source_type,
                'source_types'      => $source_types,
                'target_type'       => sanitize_key( (string) ( $row['target_type'] ?? 'post' ) ),
                'cardinality'       => sanitize_key( (string) ( $row['cardinality'] ?? 'many_to_many' ) ),
                'multiple'          => ! empty( $row['multiple'] ),
                'ordered'           => ! empty( $row['ordered'] ),
                'primary'           => ! empty( $row['primary'] ),
                'queryable'         => ! empty( $row['queryable'] ),
                'public'            => ! empty( $row['public'] ),
                'cross_site'        => ! empty( $row['cross_site'] ),
                'max_items'         => max( 0, absint( $row['max_items'] ?? 0 ) ),
                'primary_max'       => max( 0, absint( $row['primary_max'] ?? 1 ) ),
                'delete_behavior'   => $delete_behavior,
                'roles'             => array_values( array_filter( array_map( 'sanitize_key', (array) ( $row['roles'] ?? array() ) ) ) ),
                'metadata_schema'   => $metadata,
                'assign_capability' => sanitize_key( (string) ( $row['assign_capability'] ?? 'edit_posts' ) ),
                'read_capability'   => sanitize_key( (string) ( $row['read_capability'] ?? 'read' ) ),
            );
        }
        if ( ! $this->core->configured_relationships()->save( $clean ) ) {
            return new \WP_REST_Response( array( 'success'=>false, 'code'=>'relationship-save-failed', 'message'=>__( 'Relationship model could not be saved.', 'cgm-core' ) ), 400 );
        }
        $this->core->cache()->bump( 'relationship-schema' ); BootstrapController::invalidate();
        return rest_ensure_response( array( 'success'=>true ) );
    }
}
