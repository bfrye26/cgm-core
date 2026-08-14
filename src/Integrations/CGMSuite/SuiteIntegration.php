<?php
namespace CGM\Core\Integrations\CGMSuite;
use CGM\Core\Plugin;
final class SuiteIntegration {
 public function __construct(private Plugin $core){}
  public function register():void{$this->core->events()->listen('relationship.changed',array($this,'relationship_changed'));add_action('save_post',array($this,'content_changed'),200,3);add_action('edited_term',fn($id)=>$this->dispatch_object('term',$id),200);add_action('profile_update',fn($id)=>$this->dispatch_object('user',$id),200);}
 public function relationship_changed(array $p):void{$id=absint($p['source_id']??0);$type=sanitize_key((string)($p['source_type']??'post'));do_action('cgm_core/suite/relationship_changed',$p);if($id&&post_type_exists($type))do_action('cgm_core/suite/reindex_post',$id,$p);do_action('cgm_core/suite/seo_recalculate',$type,$id,$p);do_action('cgm_core/suite/purge_object',$type,$id,$p);}
 public function content_changed(int $id,\WP_Post $post,bool $update):void{if(wp_is_post_revision($id)||wp_is_post_autosave($id))return;$this->dispatch_object($post->post_type,$id);}
 private function dispatch_object(string $type,int $id):void{$payload=array('object_type'=>$type,'object_id'=>$id);$this->core->events()->dispatch('content.changed',$payload);do_action('cgm_core/suite/content_changed',$payload);do_action('cgm_core/suite/reindex_object',$type,$id);do_action('cgm_core/suite/seo_recalculate',$type,$id,$payload);do_action('cgm_core/suite/purge_object',$type,$id,$payload);}
}
