<?php
namespace CGM\Core\REST;

use CGM\Core\Plugin;

/** Read, write and manage automation rules. */
final class RuleController extends BaseController {
    public function __construct( private Plugin $core ) {}

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/rules', array(
            array(
                'methods'=>\WP_REST_Server::READABLE, 'callback'=>array($this,'list'),
                'permission_callback'=>fn()=>$this->can_view(),
            ),
            array(
                'methods'=>\WP_REST_Server::CREATABLE, 'callback'=>array($this,'save'),
                'permission_callback'=>fn()=>current_user_can('manage_cgm_core')&&$this->rest_nonce_ok(),
                'args'=>array('rules'=>array('required'=>true,'type'=>'array','maxItems'=>200)),
            ),
        ) );
    }

    private function can_view(): bool {
        return current_user_can( 'manage_cgm_core' ) || current_user_can( 'inspect_cgm_core' );
    }

    public function list( \WP_REST_Request $r ): \WP_REST_Response {
        $events = array();
        foreach ( $this->core->events()->schemas() as $event => $schema ) {
            $events[] = array( 'id' => $event, 'label' => (string) ( $schema['description'] ?? str_replace( '.', ' ', $event ) ) );
        }
        return rest_ensure_response( array(
            'rules'   => array_values( $this->core->rules()->all() ),
            'events'  => $events,
            'actions' => array_values( $this->core->rules()->actions() ),
        ) );
    }

    public function save( \WP_REST_Request $r ): \WP_REST_Response {
        $ok = $this->core->rules()->save( (array) $r->get_param( 'rules' ) );
        return $ok ? rest_ensure_response( array( 'success' => true ) ) : new \WP_REST_Response( array( 'success' => false, 'message' => __( 'Rules could not be saved.', 'cgm-core' ) ), 400 );
    }
}
