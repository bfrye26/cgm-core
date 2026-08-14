<?php
namespace CGM\Core\REST;

use CGM\Core\Plugin;

/** Unified search facade: delegates to the active search provider. */
final class SearchController extends BaseController {
    public function __construct( private Plugin $core ) {}

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/search', array(
            'methods'=>\WP_REST_Server::READABLE, 'callback'=>array($this,'search'),
            'permission_callback'=>fn()=>current_user_can('read'),
            'args'=>array(
                'q'=>array('type'=>'string','sanitize_callback'=>'sanitize_text_field','default'=>''),
                'content_type'=>array('type'=>'string','sanitize_callback'=>'sanitize_key','default'=>'post'),
                'page'=>array('type'=>'integer','default'=>1,'sanitize_callback'=>'absint'),
                'per_page'=>array('type'=>'integer','default'=>20,'maximum'=>100,'sanitize_callback'=>'absint'),
                'filters'=>array('type'=>'object','default'=>array()),
            ),
        ) );
        register_rest_route( $this->namespace, '/facets', array(
            'methods'=>\WP_REST_Server::READABLE, 'callback'=>array($this,'facets'),
            'permission_callback'=>fn()=>current_user_can('read'),
            'args'=>array('content_type'=>array('type'=>'string','sanitize_callback'=>'sanitize_key','default'=>'post')),
        ) );
    }

    public function search( \WP_REST_Request $r ): \WP_REST_Response {
        return rest_ensure_response( $this->core->search()->search(
            (string) $r->get_param( 'q' ),
            array( 'content_type' => (string) $r->get_param( 'content_type' ), 'page' => absint( $r->get_param( 'page' ) ), 'per_page' => absint( $r->get_param( 'per_page' ) ), 'filters' => (array) $r->get_param( 'filters' ) )
        ) );
    }

    public function facets( \WP_REST_Request $r ): \WP_REST_Response {
        return rest_ensure_response( array( 'facets' => $this->core->search()->facets( '', array( 'content_type' => (string) $r->get_param( 'content_type' ) ) ) ) );
    }
}
