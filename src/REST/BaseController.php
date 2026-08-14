<?php
namespace CGM\Core\REST;
abstract class BaseController {
    protected string $namespace='cgm-core/v1';
    protected function can_manage():bool{return current_user_can('manage_cgm_core');}
    protected function can_query():bool{return current_user_can('edit_posts')||current_user_can('manage_cgm_queries');}
    protected function error(string $code,string $message,int $status=400):\WP_Error{return new \WP_Error($code,$message,array('status'=>$status));}
    /** Nonce check for state-changing routes. Stateless auth (application passwords/JWT) has no cookie and is exempt. */
    protected function rest_nonce_ok():bool{
        if(!is_user_logged_in()||empty($_COOKIE[LOGGED_IN_COOKIE]))return true;
        return (bool)wp_verify_nonce((string)($_SERVER['HTTP_X_WP_NONCE']??''),'wp_rest');
    }
}
