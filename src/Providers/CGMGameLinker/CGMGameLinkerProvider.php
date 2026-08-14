<?php
namespace CGM\Core\Providers\CGMGameLinker;
use CGM\Core\Contracts\ProviderInterface;use CGM\Core\Plugin;use CGM\Core\Relationships\CallbackRelationshipStore;
use CGM\Core\Objects\ObjectReference;
/**
 * Provider for the CGM Relationship Suite (the successor to CGM Game Linker).
 *
 * The suite still publishes the historical `CGM_GAME_LINKER_VERSION`/`CGM_Game_Linker`
 * identifiers, so detection is unchanged; the label and multi-object handling here
 * reflect the new product. Storage is connected through the suite's own
 * `cgm_game_linker/core_bridge` filter — Core never touches its private meta/tables.
 */
final class CGMGameLinkerProvider implements ProviderInterface {
    public function id():string{return 'cgm-relationship-suite';}
    public function register(Plugin $core):void{
        $detected=defined('CGM_GAME_LINKER_VERSION')||class_exists('CGM_Game_Linker')||class_exists('CGM\\GameLinker\\Plugin')||class_exists('CGM_GL_Relationships');
        if(!$detected)return;
        $is_suite=class_exists('CGM_GL_Relationships');
        $label=$is_suite?__('CGM Relationship Suite','cgm-core'):__('CGM Game Linker','cgm-core');
        $version=defined('CGM_GAME_LINKER_VERSION')?CGM_GAME_LINKER_VERSION:'';
        $bridge=apply_filters('cgm_game_linker/core_bridge',array(),$core);
        $connected=is_array($bridge)&&is_callable($bridge['get']??null)&&is_callable($bridge['get_reverse']??null)&&is_callable($bridge['replace']??null);
        $queryable=$connected&&(is_callable($bridge['sql_condition']??null)||is_callable($bridge['query_sql']??null));
        $caps=$connected?array('relationships.object','dynamic_data.object','editor.object'):array('detected.relationship_suite');
        if($queryable)$caps[]='query.object';
        $core->providers()->register(array('id'=>$this->id(),'label'=>$label,'version'=>$version,'apis'=>array('core'=>'^2.0','relationships'=>'^2.0','query'=>'^2.0'),'requires'=>array('wordpress'=>'*'),'capabilities'=>$caps,'status'=>$connected?'connected':'bridge-required','notes'=>$connected?sprintf(__('%s storage is connected through the Core bridge.','cgm-core'),$label):sprintf(__('%s is detected, but Core will not guess private storage. Expose cgm_game_linker/core_bridge.','cgm-core'),$label)));
        if(!$connected)return;

        $store=new CallbackRelationshipStore($bridge);
        $core->relationships()->register_store('cgm-relationship-suite',$store);

        $primary=sanitize_key((string)($bridge['game_post_type']??'game'));
        $object_types=array_values(array_filter(array_map('sanitize_key',(array)($bridge['object_types']??array($primary)))));
        if(!in_array($primary,$object_types,true)){$object_types[]=$primary;}

        $post_types=array_values(array_filter(array_map('sanitize_key',(array)($bridge['post_types']??array('post','review'))),fn($pt)=>$core->content_types()->has($pt)));
        $roles=array_values(array_filter(array_map('sanitize_key',(array)($bridge['roles']??array('related','reviewed','previewed','mentioned')))));
        $assign_cap=sanitize_key((string)($bridge['assign_capability']??'edit_posts'));

        foreach($object_types as $index=>$object_type){
            if(!$core->content_types()->has($object_type)&&post_type_exists($object_type)){
                $pt=get_post_type_object($object_type);
                $core->content_types()->register(array('id'=>$object_type,'label'=>$pt?->labels->singular_name?:ucwords(str_replace('_',' ',$object_type)),'plural_label'=>$pt?->labels->name?:ucwords(str_replace('_',' ',$object_type)),'kind'=>'post','subtype'=>$object_type,'provider'=>'wordpress','query_provider'=>'wordpress-posts','public'=>(bool)($pt?->public),'rest'=>(bool)($pt?->show_in_rest)));
            }
            $rel_id=(0===$index)?'game':$object_type;
            $core->relationships()->register_type(array('id'=>$rel_id,'label'=>0===$index?__('Games','cgm-core'):$pt?->labels->name??ucwords(str_replace('_',' ',$object_type)),'reverse_label'=>__('Coverage','cgm-core'),'source_type'=>'*','source_types'=>$post_types,'target_type'=>$object_type,'target_types'=>array($object_type),'store'=>'cgm-relationship-suite','provider'=>$this->id(),'public'=>true,'multiple'=>true,'ordered'=>true,'primary'=>true,'primary_max'=>1,'roles'=>$roles,'metadata_schema'=>array('display'=>array('label'=>__('Show on front end','cgm-core'),'type'=>'boolean','public'=>true)),'assign_capability'=>$assign_cap,'read_capability'=>'read','queryable'=>$queryable));
            $schema=$core->relationships()->get_type($rel_id);
            $core->editor_controls()->register(array('id'=>0===$index?'games':$object_type,'label'=>0===$index?__('Games','cgm-core'):$pt?->labels->name??ucwords(str_replace('_',' ',$object_type)),'post_types'=>$post_types,'multiple'=>true,'kind'=>'relationship','relationship'=>$rel_id,'schema'=>$schema,'get'=>static function(int $post_id)use($store,$rel_id){$out=array();foreach($store->get($rel_id,(string)get_post_type($post_id),$post_id) as $r){$p=get_post(absint($r['target_id']??0));if($p)$out[]=array('id'=>$p->ID,'label'=>get_the_title($p),'primary'=>!empty($r['primary'])||!empty($r['is_primary']),'role'=>$r['role']??'','order'=>$r['order']??$r['sort_order']??0,'meta'=>$r['meta']??array());}return $out;},'search'=>is_callable($bridge['search']??null)?$bridge['search']:static function(string $q)use($object_type){$obj=get_post_type_object($object_type);$statuses=array('publish');if($obj&&current_user_can($obj->cap->read_private_posts))$statuses=array('publish','private','draft','pending','future');$posts=get_posts(array('post_type'=>$object_type,'post_status'=>$statuses,'s'=>$q,'posts_per_page'=>30,'orderby'=>$q?'relevance':'title','order'=>'ASC','suppress_filters'=>false));return array_map(static fn($p)=>array('id'=>$p->ID,'label'=>get_the_title($p),'description'=>'#'.$p->ID),$posts);},'set'=>static fn(int $post_id,array $items)=>$store->replace($rel_id,(string)get_post_type($post_id),$post_id,$object_type,$items)));
            if(0===$index)$this->register_game_dynamic_data($core,$rel_id,$object_type);
        }
        do_action('cgm_core/relationship_suite_bridge_connected',$bridge,$core);
    }
    private function register_game_dynamic_data(Plugin $core,string $rel_id,string $object_type):void{
        foreach(array('title'=>'string','url'=>'url','image'=>'media','id'=>'integer') as $property=>$type)$core->dynamic_data()->register(array('id'=>'game.primary.'.$property,'label'=>sprintf(__('Primary game %s','cgm-core'),$property),'type'=>$type,'group'=>'CGM Relationship Suite','provider'=>'cgm-relationship-suite','resolve'=>function($o)use($core,$rel_id,$property){$id=$o instanceof ObjectReference?$o->id:($o instanceof \WP_Post?$o->ID:absint($o?:get_the_ID()));$rows=$core->relationships()->get($rel_id,$id);foreach($rows as $r){if(empty($r['primary'])&&empty($r['is_primary']))continue;$tid=absint($r['target_id']??0);return match($property){'title'=>get_the_title($tid),'url'=>get_permalink($tid),'image'=>get_post_thumbnail_id($tid),'id'=>$tid,default=>null};}return null;}));
        $core->context()->register('current_game',__('Current game','cgm-core'),function(array $ctx)use($core,$rel_id,$object_type){$pid=absint($ctx['current_query_item']??$ctx['post_id']??0);if(!$pid)return 0;$post=get_post($pid);if($post&&$post->post_type===$object_type)return $pid;foreach($core->relationships()->get($rel_id,$pid) as $r)if(!empty($r['primary'])||!empty($r['is_primary']))return absint($r['target_id']??0);return 0;});
    }
}
