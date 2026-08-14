<?php
namespace CGM\Core\REST;

use CGM\Core\Plugin;
use CGM\Core\Bulk\BulkManager;

/** Preview and run rule actions against a query's result set. */
final class BulkController extends BaseController {
    public function __construct( private Plugin $core ) {}

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/bulk/preview', array(
            'methods'=>\WP_REST_Server::CREATABLE, 'callback'=>array($this,'preview'),
            'permission_callback'=>fn()=>current_user_can('manage_cgm_queries')||current_user_can('manage_cgm_core'),
            'args'=>array('query'=>array('required'=>true),'actions'=>array('type'=>'array','default'=>array())),
        ) );
        register_rest_route( $this->namespace, '/bulk/run', array(
            'methods'=>\WP_REST_Server::CREATABLE, 'callback'=>array($this,'run'),
            'permission_callback'=>fn()=>current_user_can('manage_cgm_queries')||current_user_can('manage_cgm_core'),
            'args'=>array('query'=>array('required'=>true),'actions'=>array('required'=>true,'type'=>'array','maxItems'=>50)),
        ) );
    }

    private function query( \WP_REST_Request $r ): string|array {
        $q = $r->get_param( 'query' );
        return is_array( $q ) ? (array) $q : sanitize_text_field( (string) $q );
    }

    public function preview( \WP_REST_Request $r ): \WP_REST_Response {
        return rest_ensure_response( ( new BulkManager( $this->core ) )->preview( $this->query( $r ) ) );
    }

    public function run( \WP_REST_Request $r ): \WP_REST_Response {
        return rest_ensure_response( ( new BulkManager( $this->core ) )->run( $this->query( $r ), (array) $r->get_param( 'actions' ) ) );
    }
}
