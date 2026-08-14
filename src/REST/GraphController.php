<?php
namespace CGM\Core\REST;

use CGM\Core\Plugin;
use CGM\Core\Graph\GraphManager;

/** Object/relationship graph endpoint for the visualizer. */
final class GraphController extends BaseController {
    public function __construct( private Plugin $core ) {}

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/graph/(?P<content_type>[a-z0-9_-]+)/(?P<id>\d+)', array(
            'methods'=>\WP_REST_Server::READABLE, 'callback'=>array($this,'graph'),
            'permission_callback'=>fn()=>current_user_can('inspect_cgm_data')||current_user_can('inspect_cgm_core')||$this->can_manage(),
            'args'=>array(
                'content_type'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_key'),
                'id'=>array('required'=>true,'type'=>'integer','minimum'=>1,'sanitize_callback'=>'absint'),
                'depth'=>array('type'=>'integer','default'=>1,'minimum'=>0,'maximum'=>4,'sanitize_callback'=>'absint'),
            ),
        ) );
    }

    public function graph( \WP_REST_Request $r ): \WP_REST_Response {
        return rest_ensure_response( ( new GraphManager( $this->core ) )->graph( absint( $r['id'] ), (string) $r['content_type'], absint( $r->get_param( 'depth' ) ) ) );
    }
}
