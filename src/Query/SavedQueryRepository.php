<?php
namespace CGM\Core\Query;

final class SavedQueryRepository {
    public const POST_TYPE = 'cgm_saved_query';
    private array $code = array();
    private const USAGE_OPTION = 'cgm_core_query_usage';

    public function register_post_type(): void {
        register_post_type(self::POST_TYPE,array('labels'=>array('name'=>__('CGM Queries','cgm-core'),'singular_name'=>__('CGM Query','cgm-core')),'public'=>false,'show_ui'=>false,'show_in_rest'=>false,'supports'=>array('title','revisions'),'map_meta_cap'=>true));
        register_post_meta(self::POST_TYPE,'_cgm_query_definition',array('type'=>'object','single'=>true,'show_in_rest'=>false,'auth_callback'=>static fn()=>current_user_can('manage_cgm_queries'),'revisions_enabled'=>true));
        register_post_meta(self::POST_TYPE,'_cgm_query_public',array('type'=>'boolean','single'=>true,'show_in_rest'=>false,'default'=>false,'auth_callback'=>static fn()=>current_user_can('manage_cgm_queries'),'revisions_enabled'=>true));
    }
    public function register_code(string $id,array $definition,array $args=array()):void{$slug=sanitize_title($id);if(!$slug)return;$this->code[$slug]=array('id'=>'code:'.$slug,'slug'=>$slug,'title'=>sanitize_text_field((string)($args['title']??ucwords(str_replace(array('-','_'),' ',$slug)))),'public'=>!empty($args['public']),'definition'=>$definition,'managed_by'=>'code','readonly'=>true,'source'=>(string)($args['source']??'plugin'));}
    public function all():array{$limit=max(100,min(5000,(int)apply_filters('cgm_core/saved_query_list_limit',1000)));return get_posts(array('post_type'=>self::POST_TYPE,'post_status'=>'publish','posts_per_page'=>$limit,'orderby'=>'title','order'=>'ASC','no_found_rows'=>true,'suppress_filters'=>false));}
    public function list():array{$out=array_values($this->code);foreach($this->all() as $post){$found=$this->find($post->ID);if($found)$out[]=$found;}usort($out,static fn($a,$b)=>strcasecmp((string)$a['title'],(string)$b['title']));return $out;}
    public function find(string|int $id):?array{if(is_string($id)){if(str_starts_with($id,'code:'))$id=substr($id,5);$slug=sanitize_title($id);if(isset($this->code[$slug]))return $this->code[$slug];}$post=is_numeric($id)?get_post((int)$id):get_page_by_path(sanitize_title((string)$id),OBJECT,self::POST_TYPE);if(!$post||self::POST_TYPE!==$post->post_type||'publish'!==$post->post_status)return null;$def=get_post_meta($post->ID,'_cgm_query_definition',true);if(!is_array($def)||!$def)return null;return array('id'=>$post->ID,'slug'=>$post->post_name,'title'=>$post->post_title,'public'=>(bool)get_post_meta($post->ID,'_cgm_query_public',true),'definition'=>$def,'managed_by'=>'database','readonly'=>false,'source'=>'wordpress');}
    public function save(array $data):int|\WP_Error{$id=absint($data['id']??0);$postarr=array('ID'=>$id,'post_type'=>self::POST_TYPE,'post_status'=>'publish','post_title'=>sanitize_text_field((string)($data['title']??'')),'post_name'=>sanitize_title((string)($data['slug']??$data['title']??'')));$saved=wp_insert_post($postarr,true);if(is_wp_error($saved))return $saved;update_post_meta($saved,'_cgm_query_definition',$data['definition']??array());update_post_meta($saved,'_cgm_query_public',!empty($data['public']));return $saved;}
    public function clone(string|int $id,string $title=''):int|\WP_Error{$source=$this->find($id);if(!$source)return new \WP_Error('cgm_query_missing','Query not found.');return $this->save(array('title'=>$title?:$source['title'].' Copy','definition'=>$source['definition'],'public'=>false));}
    public function record_usage(string|int $id,string $consumer,string $location=''):void{$query=$this->find($id);if(!$query)return;$key=(string)$query['slug'];$usage=get_option(self::USAGE_OPTION,array());$usage=is_array($usage)?$usage:array();$usage[$key]=is_array($usage[$key]??null)?$usage[$key]:array();$u_key=sanitize_key($consumer).':'.md5($location);$usage[$key][$u_key]=array('consumer'=>sanitize_text_field($consumer),'location'=>sanitize_text_field($location),'last_used'=>gmdate(DATE_ATOM),'count'=>1+(int)($usage[$key][$u_key]['count']??0));uasort($usage[$key],static fn($a,$b)=>strcmp((string)($b['last_used']??''),(string)($a['last_used']??'')));$usage[$key]=array_slice($usage[$key],0,50,true);update_option(self::USAGE_OPTION,$usage,false);}
    public function usage(string|int $id):array{$query=$this->find($id);if(!$query)return array();$usage=get_option(self::USAGE_OPTION,array());return array_values((array)($usage[$query['slug']]??array()));}
    public function code_definitions():array{return $this->code;}
}
