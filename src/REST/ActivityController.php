<?php
namespace CGM\Core\REST;

use CGM\Core\Telemetry\Telemetry;

/** Read-only activity + query-performance feed for the control room. */
final class ActivityController extends BaseController {
    public function __construct( private Telemetry $telemetry ) {}

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/activity', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => fn( $r ) => rest_ensure_response( array( 'activity' => $this->telemetry->activity( absint( $r->get_param( 'limit' ) ) ) ) ),
            'permission_callback' => fn() => $this->can_view(),
            'args'                => array( 'limit' => array( 'type'=>'integer', 'default'=>50, 'minimum'=>1, 'maximum'=>200, 'sanitize_callback'=>'absint' ) ),
        ) );
        register_rest_route( $this->namespace, '/performance', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => fn() => rest_ensure_response( array( 'queries' => $this->telemetry->performance() ) ),
            'permission_callback' => fn() => $this->can_view(),
        ) );
    }

    private function can_view(): bool {
        return current_user_can( 'inspect_cgm_data' ) || current_user_can( 'inspect_cgm_core' ) || $this->can_manage() || current_user_can( 'manage_cgm_queries' );
    }
}
