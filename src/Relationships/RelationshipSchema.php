<?php
namespace CGM\Core\Relationships;
final class RelationshipSchema {
    public static function normalize(array $d):array{
        $id=sanitize_key((string)($d['id']??''));
        // Earlier schema revisions stored generic object kinds plus source_subtype/target_subtype. Fold those into
        // the canonical content-type IDs used by the feature-lock architecture.
        $source=sanitize_key((string)($d['source_type']??'post'));$target=sanitize_key((string)($d['target_type']??'post'));
        if('post'===$source&&!empty($d['source_subtype'])&&'*'!==$d['source_subtype'])$source=sanitize_key((string)$d['source_subtype']);
        if('post'===$target&&!empty($d['target_subtype'])&&'*'!==$d['target_subtype'])$target=sanitize_key((string)$d['target_subtype']);
        if('term'===$source&&!empty($d['source_subtype']))$source='term_'.sanitize_key((string)$d['source_subtype']);
        if('term'===$target&&!empty($d['target_subtype']))$target='term_'.sanitize_key((string)$d['target_subtype']);
        if('attachment'===$source)$source='media';if('attachment'===$target)$target='media';
        $source_types=array_values(array_unique(array_filter(array_map('sanitize_key',(array)($d['source_types']??array())))));if(!$source_types&&$source)$source_types=array($source);
        $target_types=array_values(array_unique(array_filter(array_map('sanitize_key',(array)($d['target_types']??array())))));if(!$target_types&&$target)$target_types=array($target);
        $multiple=array_key_exists('multiple',$d)?!empty($d['multiple']):true;
        $roles=array_values(array_unique(array_filter(array_map('sanitize_key',(array)($d['roles']??array())))));
        $meta=array();$raw=(array)($d['metadata_schema']??$d['meta_schema']??$d['metadata']??array());
        foreach($raw as $key=>$value){if(is_int($key)){$key=sanitize_key((string)$value);$value=array('type'=>'string');}else{$key=sanitize_key((string)$key);$value=is_array($value)?$value:array('type'=>(string)$value);}if(!$key)continue;$value=wp_parse_args($value,array('label'=>ucwords(str_replace('_',' ',$key)),'type'=>'string','options'=>array(),'public'=>true,'required'=>false));if('select'===$value['type']&&array_is_list((array)$value['options']))$value['options']=array_combine($value['options'],$value['options'])?:array();$meta[$key]=$value;}
        $permissions=(array)($d['permissions']??array());
        if(!empty($d['cross_site'])&&empty($d['store']))$d['store']='network';
        return wp_parse_args(array_merge($d,array(
            'id'=>$id,'source_type'=>$source,'target_type'=>$target,'source_types'=>$source_types,'target_types'=>$target_types,'multiple'=>$multiple,
            'cardinality'=>sanitize_key((string)($d['cardinality']??($multiple?'many_to_many':'many_to_one'))),
            'roles'=>$roles,'metadata_schema'=>$meta,'meta_schema'=>$meta,'permissions'=>wp_parse_args($permissions,array('read'=>'read','assign'=>'edit_posts','manage'=>'manage_cgm_relationships')),
            'assign_capability'=>sanitize_key((string)($d['assign_capability']??$permissions['assign']??'edit_posts')),
            'read_capability'=>sanitize_key((string)($d['read_capability']??$permissions['read']??'read')),
            'manage_capability'=>sanitize_key((string)($d['manage_capability']??$permissions['manage']??'manage_cgm_relationships')),
            'max_items'=>max(0,absint($d['max_items']??(!$multiple?1:0))),
            'primary_max'=>max(0,absint($d['primary_max']??$d['primary_limit']??1)),'primary_limit'=>max(0,absint($d['primary_limit']??$d['primary_max']??1)),
        )),array(
            'label'=>ucwords(str_replace('_',' ',$id)),'reverse_label'=>__('Related content','cgm-core'),'store'=>'core','provider'=>'core',
            'public'=>true,'public_role'=>true,'ordered'=>true,'primary'=>false,'queryable'=>true,'delete_behavior'=>'detach','visibility_callback'=>null,'managed_by'=>'provider','cross_site'=>false
        ));
    }
    public static function validate_item(array $item,array $schema):array{
        $errors=array();
        if(!absint($item['id']??$item['target_id']??0))$errors[]='missing_target';
        $role=sanitize_key((string)($item['role']??''));
        if($role&&!empty($schema['roles'])&&!in_array($role,(array)$schema['roles'],true))$errors[]='invalid_role';
        $src=(array)($item['meta']??array());
        foreach((array)($schema['metadata_schema']??array()) as $key=>$def){
            $def=(array)$def;if(array_key_exists($key,$item))$src[$key]=$item[$key];
            if(!array_key_exists($key,$src)){
                if(array_key_exists('default',$def))$src[$key]=$def['default'];
                elseif(!empty($def['required']))$errors[]='missing_metadata:'.$key;
                continue;
            }
            if('select'===(string)($def['type']??'')&&!array_key_exists((string)$src[$key],(array)($def['options']??array())))$errors[]='invalid_metadata:'.$key;
        }
        return array_values(array_unique($errors));
    }
    public static function sanitize_item(array $item,array $schema,int $index=0):array{
        $out=array('id'=>absint($item['id']??$item['target_id']??0),'role'=>sanitize_key((string)($item['role']??'')),'order'=>intval($item['order']??$item['sort_order']??$index),'primary'=>!empty($item['primary'])||!empty($item['is_primary']),'meta'=>array());
        $src=(array)($item['meta']??array());
        foreach((array)$schema['metadata_schema'] as $key=>$def){
            $def=(array)$def;if(array_key_exists($key,$item))$src[$key]=$item[$key];
            if(!array_key_exists($key,$src)&&array_key_exists('default',$def))$src[$key]=$def['default'];
            if(!array_key_exists($key,$src))continue;
            $out['meta'][$key]=self::sanitize_meta($src[$key],$def);$out[$key]=$out['meta'][$key];
        }
        return $out;
    }
    public static function sanitize_meta(mixed $v,array $def):mixed{return match((string)($def['type']??'string')){'boolean'=>(bool)$v,'integer'=>intval($v),'number'=>(float)$v,'array'=>array_values((array)$v),'url'=>esc_url_raw((string)$v),'textarea'=>sanitize_textarea_field((string)$v),'select'=>array_key_exists((string)$v,(array)($def['options']??array()))?(string)$v:'',default=>sanitize_text_field((string)$v)};}
}
