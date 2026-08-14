<?php
namespace CGM\Core\Providers\MetaBox;

use CGM\Core\Contracts\ProviderInterface;
use CGM\Core\Plugin;

final class MetaBoxProvider implements ProviderInterface {
    public function id(): string { return 'metabox'; }
    public function register( Plugin $core ): void {
        if ( ! function_exists( 'rwmb_get_object_fields' ) ) { return; }
        $core->providers()->register(array('id'=>'metabox','label'=>'Meta Box','version'=>defined('RWMB_VER')?RWMB_VER:'','capabilities'=>array('fields','dynamic_data'),'status'=>'ready'));
        foreach($core->content_types()->all() as $ct){$kind=(string)($ct['kind']??'');$sub=(string)($ct['subtype']??'');if(!in_array($kind,array('post','media','term','user'),true))continue;$object_type='media'===$kind?'post':$kind;$lookup='user'===$kind?'user':('media'===$kind?'attachment':$sub);$fields=rwmb_get_object_fields($lookup,$object_type);foreach((array)$fields as $field)$this->field($core,(array)$field,(string)$ct['id'],$object_type);}
    }
    private function field(Plugin $core,array $field,string $ct,string $object_type):void{$name=(string)($field['id']??'');if(!$name)return;$type=$this->type((string)($field['type']??'text'));$id='metabox.'.$name;$core->fields()->register(array('id'=>$id,'label'=>(string)($field['name']??$name),'provider'=>'metabox','source'=>$name,'type'=>$type,'queryable'=>!in_array((string)($field['type']??''),array('group','image_advanced','file_advanced'),true),'sortable'=>in_array($type,array('string','integer','number','datetime'),true),'content_types'=>array($ct),'operators'=>$this->operators($type),'dynamic'=>true,'public'=>(bool)apply_filters('cgm_core/metabox_field_public',false,$field,$ct)));$core->dynamic_data()->register(array('id'=>$id,'label'=>(string)($field['name']??$name),'type'=>$type,'group'=>'Meta Box','provider'=>'metabox','public'=>(bool)apply_filters('cgm_core/metabox_field_public',false,$field,$ct),'resolve'=>static function($o)use($name,$object_type){$oid=$o instanceof ObjectReference?$o->id:($o instanceof \WP_Post||$o instanceof \WP_User?$o->ID:($o instanceof \WP_Term?$o->term_id:absint($o?:get_the_ID())));return rwmb_meta($name,array('object_type'=>$object_type),$oid);}));}
    private function type(string $type):string{return match($type){'number','range','slider'=>'number','checkbox','switch'=>'boolean','date','datetime','time'=>'datetime','image','image_advanced','single_image','file','file_advanced'=>'media','post','user','taxonomy','taxonomy_advanced'=>'relationship',default=>'string'};}
    private function operators(string $type):array{return in_array($type,array('number','integer','datetime'),true)?array('=','!=','>','>=','<','<=','IN','NOT IN','BETWEEN','NOT BETWEEN','EXISTS','NOT EXISTS'):array('=','!=','LIKE','NOT LIKE','IN','NOT IN','EXISTS','NOT EXISTS');}
}
