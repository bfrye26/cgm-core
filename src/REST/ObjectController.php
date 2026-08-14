<?php
namespace CGM\Core\REST;use CGM\Core\Plugin;
final class ObjectController extends BaseController {
    public function __construct(private Plugin $core){}
    public function register_routes():void{register_rest_route($this->namespace,'/objects/search',array('methods'=>\WP_REST_Server::READABLE,'callback'=>array($this,'search'),'permission_callback'=>fn()=>current_user_can('edit_posts')||current_user_can('inspect_cgm_data'),'args'=>array('content_type'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_key'),'search'=>array('type'=>'string','sanitize_callback'=>'sanitize_text_field','default'=>''),'limit'=>array('type'=>'integer','minimum'=>1,'maximum'=>100,'default'=>30,'sanitize_callback'=>'absint'))));register_rest_route($this->namespace,'/objects/(?P<content_type>[a-z0-9_-]+)/(?P<id>\d+)',array('methods'=>\WP_REST_Server::READABLE,'callback'=>array($this,'get'),'permission_callback'=>fn($r)=>current_user_can('inspect_cgm_data')||$this->can_read_object((string)$r['content_type'],absint($r['id'])),'args'=>array('content_type'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_key'),'id'=>array('required'=>true,'type'=>'integer','minimum'=>1,'sanitize_callback'=>'absint'))));}
    private function can_read_object(string $content_type,int $id):bool{
        $ref=$this->core->objects()->reference($id,$content_type);if(!$ref||!$this->core->objects()->exists($ref))return false;
        $def=$this->core->content_types()->get($ref->content_type);
        return match($def['kind']??'post'){'post','media'=>current_user_can('read_post',$id),'user'=>current_user_can('list_users')||get_current_user_id()===$id,'term'=>current_user_can('manage_categories'),default=>false};
    }
    public function search(\WP_REST_Request $r):\WP_REST_Response{return rest_ensure_response(array('items'=>$this->core->objects()->search((string)$r['content_type'],(string)$r->get_param('search'),array('limit'=>absint($r->get_param('limit'))))));}
    public function get(\WP_REST_Request $r):\WP_REST_Response{$ref=$this->core->objects()->reference(absint($r['id']),(string)$r['content_type']);if(!$ref||!$this->core->objects()->exists($ref))return new \WP_REST_Response(array('message'=>'Object not found.'),404);return rest_ensure_response($this->core->objects()->serialize($ref));}
}
