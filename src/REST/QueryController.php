<?php
namespace CGM\Core\REST;use CGM\Core\Plugin;use CGM\Core\Query\{QueryResult,SavedQueryRepository,QueryValidator};
final class QueryController extends BaseController {
    public function __construct(private Plugin $core,private SavedQueryRepository $repo){}
    public function register_routes():void{
        register_rest_route($this->namespace,'/queries',array(
            array('methods'=>\WP_REST_Server::READABLE,'callback'=>array($this,'list'),'permission_callback'=>fn()=>current_user_can('edit_posts')),
            array('methods'=>\WP_REST_Server::CREATABLE,'callback'=>array($this,'save'),'permission_callback'=>fn()=>current_user_can('manage_cgm_queries')&&$this->rest_nonce_ok(),'args'=>array('id'=>array('type'=>'integer','minimum'=>0,'default'=>0),'title'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_text_field'),'public'=>array('type'=>'boolean','default'=>false),'definition'=>array('required'=>true,'type'=>'object'))),
        ));
        register_rest_route($this->namespace,'/queries/(?P<id>[a-zA-Z0-9_-]+)',array('methods'=>\WP_REST_Server::READABLE,'callback'=>array($this,'get_single'),'permission_callback'=>fn()=>current_user_can('edit_posts')||current_user_can('manage_cgm_queries'),'args'=>array('id'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_text_field'))));
        register_rest_route($this->namespace,'/queries/(?P<id>[a-zA-Z0-9_-]+)/delete',array('methods'=>\WP_REST_Server::CREATABLE,'callback'=>array($this,'delete'),'permission_callback'=>fn()=>current_user_can('manage_cgm_queries')&&$this->rest_nonce_ok(),'args'=>array('id'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_text_field'))));
        register_rest_route($this->namespace,'/queries/(?P<id>[a-zA-Z0-9_-]+)/clone',array('methods'=>\WP_REST_Server::CREATABLE,'callback'=>array($this,'clone'),'permission_callback'=>fn()=>current_user_can('manage_cgm_queries')&&$this->rest_nonce_ok(),'args'=>array('id'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_text_field'))));
        register_rest_route($this->namespace,'/query/(?P<id>[a-zA-Z0-9_-]+)/export',array('methods'=>\WP_REST_Server::READABLE,'callback'=>array($this,'export'),'permission_callback'=>array($this,'permissions'),'args'=>array('id'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_key'),'format'=>array('type'=>'string','enum'=>array('csv','json'),'default'=>'csv','sanitize_callback'=>'sanitize_key'),'limit'=>array('type'=>'integer','minimum'=>1,'maximum'=>1000,'default'=>500,'sanitize_callback'=>'absint'))));
        register_rest_route($this->namespace,'/query/(?P<id>[a-zA-Z0-9_-]+)',array('methods'=>\WP_REST_Server::READABLE,'callback'=>array($this,'run'),'permission_callback'=>array($this,'permissions'),'args'=>array('id'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_key'),'post_id'=>array('type'=>'integer','minimum'=>0,'sanitize_callback'=>'absint'),'page'=>array('type'=>'integer','minimum'=>1,'maximum'=>100000,'default'=>1,'sanitize_callback'=>'absint'))));
        register_rest_route($this->namespace,'/query/test',array('methods'=>\WP_REST_Server::CREATABLE,'callback'=>array($this,'test'),'permission_callback'=>fn()=>$this->can_query(),'args'=>array('query'=>array('required'=>true,'type'=>'object'),'context'=>array('type'=>'object','default'=>array()))));
        register_rest_route($this->namespace,'/query/explain',array('methods'=>\WP_REST_Server::CREATABLE,'callback'=>array($this,'explain'),'permission_callback'=>fn()=>current_user_can('manage_cgm_queries')||current_user_can('inspect_cgm_data'),'args'=>array('query'=>array('required'=>true),'context'=>array('type'=>'object','default'=>array()))));
        register_rest_route($this->namespace,'/query/aggregate',array('methods'=>\WP_REST_Server::CREATABLE,'callback'=>array($this,'aggregate'),'permission_callback'=>fn()=>$this->can_query(),'args'=>array('query'=>array('type'=>'object'),'id'=>array('type'=>'string','sanitize_callback'=>'sanitize_text_field'),'context'=>array('type'=>'object','default'=>array()))));
    }

    public function save(\WP_REST_Request $r){
        $id=absint($r->get_param('id'));$title=sanitize_text_field((string)$r->get_param('title'));$definition=(array)$r->get_param('definition');
        if(!$title)return $this->error('missing-title',__('Name is required.','cgm-core'),400);
        // Normalize + allowlist at write time so stored definitions cannot
        // carry unknown top-level keys or malformed filters into the engine.
        $normalized=(new QueryValidator())->normalize($definition);
        $allowed=array_flip(array('content_type','status','filters','sort','limit','page','offset','search','cache','cache_ttl','display','exposed_filters','aggregate'));
        $definition=array_intersect_key($normalized,$allowed);
        if(!$definition['content_type'])$definition['content_type']='post';
        $saved=$this->repo->save(array('id'=>$id,'title'=>$title,'public'=>(bool)$r->get_param('public'),'definition'=>$definition));
        if(is_wp_error($saved))return $this->error($saved->get_error_code(),$saved->get_error_message(),400);
        $this->core->cache()->bump('saved-query:'.$saved);BootstrapController::invalidate();
        $savedQuery=$this->repo->find($saved);
        return rest_ensure_response(array('success'=>true,'id'=>$saved,'slug'=>$savedQuery['slug']??''));
    }

    public function delete(\WP_REST_Request $r){
        $raw=sanitize_text_field((string)$r['id']);$id=ctype_digit($raw)?absint($raw):0;
        $saved=$id?$this->repo->find($id):null;
        if(!$saved)return $this->error('query_not_found',__('Query not found.','cgm-core'),404);
        if(empty($saved['managed_by'])||'database'!==$saved['managed_by'])return $this->error('query_readonly',__('Code-managed queries cannot be deleted.','cgm-core'),400);
        wp_delete_post((int)$saved['id'],true);
        $this->core->cache()->bump('saved-query:'.(int)$saved['id']);BootstrapController::invalidate();
        return rest_ensure_response(array('success'=>true));
    }

    public function clone(\WP_REST_Request $r){
        $raw=sanitize_text_field((string)$r['id']);$id=ctype_digit($raw)?absint($raw):$raw;
        $cloned=$this->repo->clone($id);
        if(is_wp_error($cloned))return $this->error($cloned->get_error_code(),$cloned->get_error_message(),400);
        $this->core->cache()->bump('saved-query:'.$cloned);BootstrapController::invalidate();
        return rest_ensure_response(array('success'=>true,'id'=>$cloned));
    }
    public function list():\WP_REST_Response{$items=array_map(static fn($q)=>array('id'=>$q['id'],'slug'=>$q['slug'],'title'=>$q['title'],'public'=>$q['public'],'managed_by'=>$q['managed_by'],'readonly'=>$q['readonly']??false),$this->repo->list());return rest_ensure_response(array('items'=>$items));}
    public function get_single(\WP_REST_Request $r):\WP_REST_Response{$raw=sanitize_text_field((string)$r['id']);$id=ctype_digit($raw)?absint($raw):$raw;$saved=$this->repo->find($id);if(!$saved)return new \WP_REST_Response(array('message'=>'Query not found.'),404);return rest_ensure_response(array('id'=>$saved['id'],'slug'=>$saved['slug'],'title'=>$saved['title'],'public'=>$saved['public'],'managed_by'=>$saved['managed_by'],'readonly'=>$saved['readonly']??false,'definition'=>$saved['definition'],'usage'=>count($this->repo->usage((string)$saved['slug'])),'usage_rows'=>$this->repo->usage((string)$saved['slug'])));}
    public function export(\WP_REST_Request $r):\WP_REST_Response{$saved=$this->repo->find((string)$r['id']);if(!$saved)return new \WP_REST_Response(array('message'=>'Query not found.'),404);$definition=$saved['definition'];$definition['page']=1;$definition['limit']=min(1000,max(1,absint($r->get_param('limit'))));$ctx=array('post_id'=>absint($r->get_param('post_id')),'consumer'=>'export');$result=$this->core->queries()->run($definition,$ctx);$rows=array();foreach($result->items as $o){$ref=$this->core->objects()->reference($o);if(!$ref)continue;$rows[]=$this->core->objects()->serialize($ref);}$this->repo->record_usage((string)$r['id'],'export',(string)$r->get_param('format'));if('json'===(string)$r->get_param('format'))return rest_ensure_response(array('slug'=>$saved['slug'],'title'=>$saved['title'],'total'=>$result->total,'items'=>$rows));return $this->csv_response($saved['slug'],$rows);}
    private function csv_response(string $slug,array $rows):\WP_REST_Response{$headers=array();foreach($rows as $row)foreach(array_keys((array)$row) as $k)if(!in_array($k,$headers,true))$headers[]=$k;$out=fopen('php://temp','r+');fputcsv($out,$headers);foreach($rows as $row){$line=array();foreach($headers as $k){$v=$row[$k]??'';$v=is_scalar($v)||null===$v?(string)$v:wp_json_encode($v);if(''!==$v&&in_array($v[0],array('=','+','-','@'),true))$v="'".$v;$line[]=$v;}fputcsv($out,$line);}rewind($out);$csv=stream_get_contents($out);fclose($out);$response=new \WP_REST_Response($csv,200);$response->header('Content-Type','text/csv; charset=utf-8');$response->header('Content-Disposition','attachment; filename="'.sanitize_title($slug).'-'.gmdate('Ymd').'.csv"');return $response;}
    public function permissions(\WP_REST_Request $r):bool{if($this->can_query())return true;$saved=$this->repo->find((string)$r['id']);if(!$saved||empty($saved['public']))return false;$ct=$this->core->content_types()->get((string)($saved['definition']['content_type']??''));return !empty($ct['public']);}
    public function run(\WP_REST_Request $r):\WP_REST_Response{$public=!$this->can_query();$saved=$this->repo->find((string)$r['id']);if(!$saved)return new \WP_REST_Response(array('message'=>'Query not found.'),404);$definition=$saved['definition'];$definition['page']=max(1,absint($r->get_param('page')));$ctx=array('post_id'=>absint($r->get_param('post_id')),'public_request'=>$public);$this->repo->record_usage((string)$r['id'],'rest','/query/'.$r['id']);return rest_ensure_response($this->serialize($this->core->queries()->run($definition,$ctx)));}
    public function test(\WP_REST_Request $r):\WP_REST_Response{return rest_ensure_response($this->serialize($this->core->queries()->run((array)$r->get_param('query'),(array)$r->get_param('context'))));}
    public function explain(\WP_REST_Request $r):\WP_REST_Response{$q=$r->get_param('query');$q=is_array($q)?$q:sanitize_text_field((string)$q);return rest_ensure_response($this->core->queries()->explain($q,(array)$r->get_param('context')));}
    public function aggregate(\WP_REST_Request $r):\WP_REST_Response{$q=$r->get_param('query');$id=sanitize_text_field((string)$r->get_param('id'));if(''!==$id)$q=$id;if(is_array($q)||is_string($q)&&''!==$q)return rest_ensure_response($this->core->queries()->aggregate($q,(array)$r->get_param('context')));return new \WP_REST_Response(array('message'=>'Query is required.'),400);}
    private function serialize(QueryResult $r):array{$items=array();foreach($r->items as $o){$ref=$this->core->objects()->reference($o);if(!$ref)continue;$row=$this->core->objects()->serialize($ref);$ct=$this->core->content_types()->get($ref->content_type);$row['object']=$ct['kind']??'object';if($o instanceof \WP_Post)$row+=array('slug'=>$o->post_name,'excerpt'=>get_the_excerpt($o),'date'=>get_post_time(DATE_ATOM,true,$o),'modified'=>get_post_modified_time(DATE_ATOM,true,$o),'author'=>(int)$o->post_author,'featured_media'=>get_post_thumbnail_id($o));elseif($o instanceof \WP_Term)$row+=array('slug'=>$o->slug,'taxonomy'=>$o->taxonomy,'count'=>$o->count);$items[]=$row;}return array('items'=>$items,'total'=>$r->total,'page'=>$r->page,'per_page'=>$r->per_page,'debug'=>(current_user_can('inspect_cgm_data')||current_user_can('manage_cgm_queries'))?$r->debug:array());}
}
