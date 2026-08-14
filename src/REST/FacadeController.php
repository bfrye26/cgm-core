<?php
namespace CGM\Core\REST;

use CGM\Core\Plugin;

/** Discoverable unified facade + notification inbox. */
final class FacadeController extends BaseController {
    public function __construct( private Plugin $core ) {}

    public function register_routes(): void {
        register_rest_route( 'cgm/v1', '/', array(
            'methods'=>\WP_REST_Server::READABLE, 'callback'=>array($this,'manifest'),
            'permission_callback'=>fn()=>current_user_can('read'),
        ) );
        register_rest_route( $this->namespace, '/notifications', array(
            'methods'=>\WP_REST_Server::READABLE, 'callback'=>fn()=>rest_ensure_response(array('items'=>$this->core->notifications()->all())),
            'permission_callback'=>fn()=>$this->can_manage()||current_user_can('inspect_cgm_core'),
        ) );
        register_rest_route( $this->namespace, '/notifications/(?P<id>[a-zA-Z0-9_-]+)', array(
            'methods'=>\WP_REST_Server::CREATABLE, 'callback'=>array($this,'dismiss'),
            'permission_callback'=>fn()=>$this->can_manage(),
            'args'=>array('id'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_key')),
        ) );
    }

    public function manifest(): \WP_REST_Response {
        $namespaces = apply_filters( 'cgm_core/facade_namespaces', array() );
        return rest_ensure_response( array(
            'name' => 'CGM Core unified facade',
            'version' => CGM_CORE_VERSION,
            'core' => array(
                'search' => '/cgm-core/v1/search',
                'query' => '/cgm-core/v1/query/{id}',
                'data' => '/cgm-core/v1/data/{key}',
                'objects' => '/cgm-core/v1/objects/search',
                'graph' => '/cgm-core/v1/graph/{content_type}/{id}',
            ),
            'suite' => array_values( $namespaces ),
        ) );
    }

    public function dismiss( \WP_REST_Request $r ): \WP_REST_Response {
        $this->core->notifications()->dismiss( (string) $r['id'] );
        return rest_ensure_response( array( 'success' => true ) );
    }
}
