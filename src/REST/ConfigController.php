<?php
namespace CGM\Core\REST;

use CGM\Core\Configuration\ConfigurationManager;

final class ConfigController extends BaseController {
    public function __construct( private ConfigurationManager $config ) {}

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/config/export', array(
            'methods'=>\WP_REST_Server::READABLE,
            'callback'=>fn()=>rest_ensure_response($this->config->export()),
            'permission_callback'=>fn()=>current_user_can('manage_cgm_configuration'),
        ) );
        register_rest_route( $this->namespace, '/config/validate', array(
            'methods'=>\WP_REST_Server::CREATABLE,
            'callback'=>fn($r)=>rest_ensure_response($this->config->validate((array)$r->get_param('config'))),
            'permission_callback'=>fn()=>current_user_can('manage_cgm_configuration'),
            'args'=>array('config'=>array('required'=>true,'type'=>'object')),
        ) );
        register_rest_route( $this->namespace, '/config/diff', array(
            'methods'=>\WP_REST_Server::CREATABLE,
            'callback'=>fn($r)=>rest_ensure_response($this->config->diff((array)$r->get_param('config'),(string)$r->get_param('mode'))),
            'permission_callback'=>fn()=>current_user_can('manage_cgm_configuration'),
            'args'=>array(
                'config'=>array('required'=>true,'type'=>'object'),
                'mode'=>array('type'=>'string','enum'=>array('merge','replace'),'default'=>'merge','sanitize_callback'=>'sanitize_key'),
            ),
        ) );
        register_rest_route( $this->namespace, '/config/import', array(
            'methods'=>\WP_REST_Server::CREATABLE,
            'callback'=>array($this,'import'),
            'permission_callback'=>fn()=>current_user_can('manage_cgm_configuration'),
            'args'=>array(
                'config'=>array('required'=>true,'type'=>'object'),
                'mode'=>array('type'=>'string','enum'=>array('merge','replace'),'default'=>'merge','sanitize_callback'=>'sanitize_key'),
                'dry_run'=>array('type'=>'boolean','default'=>true),
            ),
        ) );
        register_rest_route( $this->namespace, '/config/backups', array(
            'methods'=>\WP_REST_Server::READABLE,
            'callback'=>fn()=>rest_ensure_response(array('backups'=>$this->backup_rows(),'pending'=>$this->config->pending_import())),
            'permission_callback'=>fn()=>current_user_can('manage_cgm_configuration'),
        ) );
        register_rest_route( $this->namespace, '/config/rollback/(?P<id>[a-zA-Z0-9_-]+)', array(
            'methods'=>\WP_REST_Server::CREATABLE,
            'callback'=>array($this,'rollback'),
            'permission_callback'=>fn()=>current_user_can('manage_cgm_configuration'),
            'args'=>array('id'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_text_field')),
        ) );
    }

    public function import( \WP_REST_Request $request ): \WP_REST_Response {
        $result=$this->config->import((array)$request->get_param('config'),(string)$request->get_param('mode'),(bool)$request->get_param('dry_run'));
        if(!empty($result['success'])&&empty($request->get_param('dry_run')))BootstrapController::invalidate();
        return new \WP_REST_Response($result,empty($result['success'])?400:200);
    }

    public function rollback( \WP_REST_Request $request ): \WP_REST_Response {
        $result=$this->config->rollback(sanitize_text_field((string)$request['id']));
        if(!empty($result['success']))BootstrapController::invalidate();
        return new \WP_REST_Response($result,empty($result['success'])?400:200);
    }

    private function backup_rows(): array {
        $out=array();foreach($this->config->backups() as $id=>$backup)$out[]=array('id'=>$id,'created'=>(string)($backup['created']??''));return array_reverse($out);
    }
}
