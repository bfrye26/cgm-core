<?php
namespace CGM\Core\REST;

use CGM\Core\Plugin;

/** Read workflow states and transition objects between them. */
final class WorkflowController extends BaseController {
    public function __construct( private Plugin $core ) {}

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/workflow/states', array(
            'methods'=>\WP_REST_Server::READABLE, 'callback'=>fn()=>rest_ensure_response(array('states'=>$this->core->workflow()->states())),
            'permission_callback'=>fn()=>$this->can_view(),
        ) );
        register_rest_route( $this->namespace, '/workflow/transition', array(
            'methods'=>\WP_REST_Server::CREATABLE, 'callback'=>array($this,'transition'),
            'permission_callback'=>fn()=>current_user_can('edit_posts')||$this->can_manage(),
            'args'=>array(
                'object_id'=>array('required'=>true,'type'=>'integer','minimum'=>1,'sanitize_callback'=>'absint'),
                'state'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_key'),
            ),
        ) );
        register_rest_route( $this->namespace, '/workflow/scheduled', array(
            'methods'=>\WP_REST_Server::READABLE, 'callback'=>array($this,'scheduled'),
            'permission_callback'=>fn()=>$this->can_view(),
        ) );
        register_rest_route( $this->namespace, '/workflow/schedule', array(
            'methods'=>\WP_REST_Server::CREATABLE, 'callback'=>array($this,'schedule'),
            'permission_callback'=>fn()=>current_user_can('edit_posts')||$this->can_manage(),
            'args'=>array(
                'object_id'=>array('required'=>true,'type'=>'integer','minimum'=>1,'sanitize_callback'=>'absint'),
                'state'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_key'),
                'at'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_text_field'),
            ),
        ) );
        register_rest_route( $this->namespace, '/workflow/scheduled/(?P<id>[a-zA-Z0-9_-]+)', array(
            'methods'=>\WP_REST_Server::CREATABLE, 'callback'=>array($this,'cancel'),
            'permission_callback'=>fn()=>current_user_can('edit_posts')||$this->can_manage(),
            'args'=>array('id'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_key')),
        ) );
        register_rest_route( $this->namespace, '/workflow/auto-transitions', array(
            array(
                'methods'=>\WP_REST_Server::READABLE, 'callback'=>array($this,'auto_transitions'),
                'permission_callback'=>fn()=>$this->can_view(),
            ),
            array(
                'methods'=>\WP_REST_Server::CREATABLE, 'callback'=>array($this,'save_auto_transitions'),
                'permission_callback'=>fn()=>$this->can_manage(),
                'args'=>array('rules'=>array('required'=>true,'type'=>'array','maxItems'=>100)),
            ),
        ) );
        register_rest_route( $this->namespace, '/workflow/auto-transitions/run', array(
            'methods'=>\WP_REST_Server::CREATABLE, 'callback'=>array($this,'run_auto_transitions'),
            'permission_callback'=>fn()=>$this->can_manage(),
        ) );
    }

    private function can_view(): bool {
        return current_user_can( 'edit_posts' ) || current_user_can( 'inspect_cgm_core' ) || $this->can_manage();
    }

    public function transition( \WP_REST_Request $r ): \WP_REST_Response {
        $ok = $this->core->workflow()->transition( absint( $r->get_param( 'object_id' ) ), sanitize_key( (string) $r->get_param( 'state' ) ) );
        return $ok
            ? rest_ensure_response( array( 'success' => true, 'state' => $this->core->workflow()->get_state( absint( $r->get_param( 'object_id' ) ) ) ) )
            : new \WP_REST_Response( array( 'success' => false, 'message' => __( 'Transition failed.', 'cgm-core' ) ), 400 );
    }

    public function scheduled( \WP_REST_Request $r ): \WP_REST_Response {
        return rest_ensure_response( array( 'items' => $this->core->workflow()->scheduled() ) );
    }

    public function schedule( \WP_REST_Request $r ): \WP_REST_Response {
        $at = sanitize_text_field( (string) $r->get_param( 'at' ) );
        try {
            $dt = new \DateTimeImmutable( $at, wp_timezone() );
            $timestamp = $dt->getTimestamp();
        } catch ( \Exception $e ) {
            return new \WP_REST_Response( array( 'success' => false, 'message' => __( 'Scheduled time is not valid.', 'cgm-core' ) ), 400 );
        }
        $result = $this->core->workflow()->schedule_transition( absint( $r->get_param( 'object_id' ) ), sanitize_key( (string) $r->get_param( 'state' ) ), $timestamp );
        if ( is_wp_error( $result ) ) { return new \WP_REST_Response( array( 'success' => false, 'message' => $result->get_error_message() ), 400 ); }
        return rest_ensure_response( array( 'success' => true ) );
    }

    public function cancel( \WP_REST_Request $r ): \WP_REST_Response {
        $ok = $this->core->workflow()->cancel_scheduled( (string) $r['id'] );
        return $ok ? rest_ensure_response( array( 'success' => true ) ) : new \WP_REST_Response( array( 'success' => false, 'message' => __( 'Scheduled transition not found.', 'cgm-core' ) ), 404 );
    }

    public function auto_transitions( \WP_REST_Request $r ): \WP_REST_Response {
        return rest_ensure_response( array( 'rules' => $this->core->workflow()->auto_transitions() ) );
    }

    public function save_auto_transitions( \WP_REST_Request $r ): \WP_REST_Response {
        $ok = $this->core->workflow()->save_auto_transitions( (array) $r->get_param( 'rules' ) );
        return $ok ? rest_ensure_response( array( 'success' => true ) ) : new \WP_REST_Response( array( 'success' => false, 'message' => __( 'Auto-transition rules could not be saved.', 'cgm-core' ) ), 400 );
    }

    public function run_auto_transitions( \WP_REST_Request $r ): \WP_REST_Response {
        return rest_ensure_response( array( 'transitioned' => $this->core->workflow()->run_auto_transitions() ) );
    }
}
