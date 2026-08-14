<?php
namespace CGM\Core\REST;

use CGM\Core\Plugin;
use CGM\Core\Index\IndexManager;

/** Read the registered search-index definitions and trigger rebuilds. */
final class IndexController extends BaseController {
    public function __construct( private Plugin $core ) {}

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/indexes', array(
            'methods'=>\WP_REST_Server::READABLE, 'callback'=>fn()=>rest_ensure_response(array('items'=>array_values($this->core->indexes()->all()))),
            'permission_callback'=>fn()=>$this->can_view(),
        ) );
        register_rest_route( $this->namespace, '/indexes/rebuild', array(
            'methods'=>\WP_REST_Server::CREATABLE, 'callback'=>fn($r)=>rest_ensure_response(array('rebuilding'=>(new IndexManager($this->core))->rebuild(sanitize_key((string)$r->get_param('index'))))),
            'permission_callback'=>fn()=>$this->can_manage()&&$this->rest_nonce_ok(),
            'args'=>array('index'=>array('type'=>'string','sanitize_callback'=>'sanitize_key','default'=>'')),
        ) );
    }

    private function can_view(): bool {
        return current_user_can( 'inspect_cgm_data' ) || current_user_can( 'inspect_cgm_core' ) || $this->can_manage();
    }
}
