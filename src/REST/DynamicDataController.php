<?php
namespace CGM\Core\REST;use CGM\Core\Plugin;
final class DynamicDataController extends BaseController {
    public function __construct(private Plugin $core){}
    public function register_routes():void{register_rest_route($this->namespace,'/data/(?P<key>[a-zA-Z0-9._:-]+)',array('methods'=>\WP_REST_Server::READABLE,'callback'=>array($this,'get'),'permission_callback'=>array($this,'permission'),'args'=>array('key'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_text_field'),'object_id'=>array('type'=>'integer','minimum'=>0,'sanitize_callback'=>'absint'),'content_type'=>array('type'=>'string','sanitize_callback'=>'sanitize_key'),'post_id'=>array('type'=>'integer','minimum'=>0,'sanitize_callback'=>'absint'))));}
    public function permission(\WP_REST_Request $r):bool{
        $id=absint($r->get_param('object_id'));$ct=sanitize_key((string)$r->get_param('content_type'));$key=(string)$r['key'];
        $dynamic=$this->core->dynamic_data()->get($key);
        $key_public=$dynamic&&!empty($dynamic['public']);
        // Site-wide keys with no object: public keys only, otherwise privileged.
        if(!$id)return $key_public||current_user_can('inspect_cgm_data');
        $ref=$this->core->objects()->reference($id,$ct?:null);if(!$ref)return false;
        if(!$this->object_readable($ref,$id))return false;
        // Public key on a readable object: anyone. Non-public key: privileged
        // only — never grant via edit_posts (leaks emails/private field values).
        return $key_public||current_user_can('inspect_cgm_data');
    }
    private function object_readable(\CGM\Core\Objects\ObjectReference $ref,int $id):bool{
        if(!$this->core->objects()->exists($ref))return false;
        $def=$this->core->content_types()->get($ref->content_type);
        return match($def['kind']??'post'){'post','media'=>current_user_can('read_post',$id),'user'=>current_user_can('list_users')||get_current_user_id()===$id,'term'=>current_user_can('manage_categories'),default=>current_user_can('inspect_cgm_data')};
    }
    public function get(\WP_REST_Request $r):\WP_REST_Response{$id=absint($r->get_param('object_id'));$ct=sanitize_key((string)$r->get_param('content_type'));$object=$id?($this->core->objects()->reference($id,$ct?:null)?:$id):null;$value=$this->core->dynamic_data()->resolve((string)$r['key'],$object,array('post_id'=>absint($r->get_param('post_id'))));return rest_ensure_response(array('value'=>$value,'type'=>$this->core->dynamic_data()->get((string)$r['key'])['type']??'mixed'));}
}
