<?php
namespace CGM\Core\Providers\CGMAuthors;
use CGM\Core\Contracts\QueryableRelationshipStoreInterface;
final class CGMAuthorsRelationshipStore implements QueryableRelationshipStoreInterface {
    private string $meta_key='_cgm_authors';
    public function get(string $relationship,string $source_type,int $source_id,array $args=array()):array{$ids=$this->ids($source_id);$primary=(int)get_post_field('post_author',$source_id);if('co_authors'===$relationship)$ids=array_values(array_filter($ids,fn($id)=>$id!==$primary));$out=array();foreach($ids as $i=>$id)$out[]=array('relationship_key'=>$relationship,'source_type'=>$source_type,'source_id'=>$source_id,'target_type'=>'user','target_id'=>$id,'role'=>$id===$primary?'primary':'author','sort_order'=>$i,'order'=>$i,'is_primary'=>$id===$primary,'primary'=>$id===$primary,'meta'=>array());return $out;}
    public function get_reverse(string $relationship,string $target_type,int $target_id,array $args=array()):array{global $wpdb;$patterns=$this->serialized_patterns($target_id);$likes=array();$params=array($this->meta_key);foreach($patterns as $pattern){$likes[]='meta_value LIKE %s';$params[]='%'.$wpdb->esc_like($pattern).'%';}$sql="SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key=%s AND (".implode(' OR ',$likes).')';$posts=$wpdb->get_col($wpdb->prepare($sql,...$params));if('authors'===$relationship)$posts=array_merge((array)$posts,(array)$wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_author=%d",$target_id)));$out=array();foreach(array_values(array_unique(array_map('absint',$posts))) as $post_id){foreach($this->get($relationship,(string)get_post_type($post_id),$post_id) as $row)if((int)$row['target_id']===$target_id)$out[]=$row;}return $out;}
    public function replace(string $relationship,string $source_type,int $source_id,string $target_type,array $items):bool{$incoming=array_values(array_unique(array_filter(array_map(static fn($i)=>absint(is_array($i)?($i['id']??$i['target_id']??0):$i),$items))));$primary=(int)get_post_field('post_author',$source_id);if('co_authors'===$relationship&&$primary)$incoming=array_values(array_unique(array_merge(array($primary),$incoming)));$old=get_post_meta($source_id,$this->meta_key,true);$value=$this->preserve_shape($old,$incoming);return false!==update_post_meta($source_id,$this->meta_key,$value);}
    public function matching_source_ids(string $relationship,string $source_type,string $target_type,string $operator,mixed $value):array{global $wpdb;$ids=array_values(array_filter(array_map('absint',is_array($value)?$value:preg_split('/[\s,]+/',(string)$value))));if(!$ids)return array();$parts=array();$params=array();foreach($ids as $id){$patterns=$this->serialized_patterns($id);$sub=array();foreach($patterns as $pattern){$sub[]='pm.meta_value LIKE %s';$params[]='%'.$wpdb->esc_like($pattern).'%';}$parts[]='('.implode(' OR ',$sub).')';}$sql="SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm WHERE pm.meta_key=%s AND (".implode(' OR ',$parts).')';array_unshift($params,$this->meta_key);$found=array_map('absint',(array)$wpdb->get_col($wpdb->prepare($sql,...$params)));if('authors'===$relationship){$ph=implode(',',array_fill(0,count($ids),'%d'));$found=array_merge($found,array_map('absint',(array)$wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_author IN ({$ph})",...$ids))));}$found=array_values(array_unique($found));if('co_authors'===$relationship)$found=array_values(array_filter($found,function($post_id)use($ids){$primary=(int)get_post_field('post_author',$post_id);$co=array_diff($this->ids($post_id),array($primary));return (bool)array_intersect($co,$ids);}));return $found;}
    public function sql_condition(string $relationship,string $source_type,string $target_type,string $operator,mixed $value,string $source_expression):?array{global $wpdb;$op=strtoupper($operator);$metaExists="EXISTS (SELECT 1 FROM {$wpdb->postmeta} ca_pm WHERE ca_pm.post_id={$source_expression} AND ca_pm.meta_key=%s AND ca_pm.meta_value NOT IN ('','a:0:{}'))";if('EXISTS'===$op)return array('sql'=>'co_authors'===$relationship?$metaExists:"({$metaExists} OR EXISTS (SELECT 1 FROM {$wpdb->posts} ca_p WHERE ca_p.ID={$source_expression} AND ca_p.post_author>0))",'params'=>array($this->meta_key));if('NOT EXISTS'===$op){$x=$this->sql_condition($relationship,$source_type,$target_type,'EXISTS','',$source_expression);return array('sql'=>'NOT ('.$x['sql'].')','params'=>$x['params']);}$ids=array_values(array_filter(array_map('absint',is_array($value)?$value:preg_split('/[\s,]+/',(string)$value))));if(!$ids)return array('sql'=>in_array($op,array('!=','NOT IN'),true)?'1=1':'1=0','params'=>array());$or=array();$params=array();foreach($ids as $id){$sub=array();foreach($this->serialized_patterns($id) as $pattern){$sub[]='ca_pm.meta_value LIKE %s';$params[]='%'.$wpdb->esc_like($pattern).'%';}$or[]='('.implode(' OR ',$sub).')';}$meta="EXISTS (SELECT 1 FROM {$wpdb->postmeta} ca_pm WHERE ca_pm.post_id={$source_expression} AND ca_pm.meta_key=%s AND (".implode(' OR ',$or).'))';array_unshift($params,$this->meta_key);$ph=implode(',',array_fill(0,count($ids),'%d'));if('authors'===$relationship){$positive='('.$meta." OR EXISTS (SELECT 1 FROM {$wpdb->posts} ca_p WHERE ca_p.ID={$source_expression} AND ca_p.post_author IN ({$ph})))";$params=array_merge($params,$ids);}else{$positive='('.$meta." AND NOT EXISTS (SELECT 1 FROM {$wpdb->posts} ca_p WHERE ca_p.ID={$source_expression} AND ca_p.post_author IN ({$ph})))";$params=array_merge($params,$ids);}return array('sql'=>in_array($op,array('!=','NOT IN'),true)?'NOT '.$positive:$positive,'params'=>$params);}
    public function sql_property_condition(string $relationship,string $source_type,string $target_type,string $property,string $operator,mixed $value,string $source_expression):?array{
        $property=sanitize_key(str_replace(array('meta.','meta:'),'',$property));
        if(in_array($property,array('target','target_id'),true))return $this->sql_condition($relationship,$source_type,$target_type,$operator,$value,$source_expression);
        // The compatibility store derives the primary author from native post_author.
        if(in_array($property,array('primary','is_primary'),true)&&'authors'===$relationship){
            global $wpdb;$want=filter_var($value,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);$want=null===$want?(bool)$value:$want;
            $sql="EXISTS (SELECT 1 FROM {$wpdb->posts} cap WHERE cap.ID={$source_expression} AND cap.post_author>0)";
            return array('sql'=>$want?$sql:'NOT '.$sql,'params'=>array());
        }
        if('role'===$property&&'authors'===$relationship){
            $role=sanitize_key((string)$value);if(in_array(strtoupper($operator),array('=','IN'),true)&&'primary'===$role){
                global $wpdb;return array('sql'=>"EXISTS (SELECT 1 FROM {$wpdb->posts} cap WHERE cap.ID={$source_expression} AND cap.post_author>0)",'params'=>array());
            }
        }
        return null;
    }
    public function sql_wrap_condition(string $relationship,string $source_type,string $target_type,string $selector,string $child_sql,array $child_params,string $source_expression):?array{
        // Native primary-author traversal is safe and fully SQL-bound. The legacy
        // serialized co-author list intentionally does not attempt SQL extraction;
        // a formal CGM Authors bridge can supply richer traversal without guessing storage.
        if('authors'!==$relationship||!in_array($selector,array('primary','first'),true))return null;
        global $wpdb;$child=str_replace('{{TARGET_ID}}','caw.post_author',$child_sql);
        return array('sql'=>"EXISTS (SELECT 1 FROM {$wpdb->posts} caw WHERE caw.ID={$source_expression} AND caw.post_author>0 AND ({$child}))",'params'=>$child_params);
    }
    public function sql_sort_expression(string $relationship,string $source_type,string $target_type,string $property,string $selector,string $source_expression):?array{
        if('authors'!==$relationship||!in_array($property,array('target','target_id'),true))return null;
        global $wpdb;return array('sql'=>"(SELECT cas.post_author FROM {$wpdb->posts} cas WHERE cas.ID={$source_expression} LIMIT 1)",'params'=>array());
    }
    /** Reverse reference (target=user). Only the native primary-author relationship is SQL-compilable. */
    public function sql_reverse_condition(string $relationship,string $operator,mixed $value,string $target_expression):?array{
        if('authors'!==$relationship)return null;
        global $wpdb;$op=strtoupper($operator);
        if(in_array($op,array('EXISTS','NOT EXISTS'),true)){$sql="EXISTS (SELECT 1 FROM {$wpdb->posts} ca_p WHERE ca_p.post_author={$target_expression})";return array('sql'=>('NOT EXISTS'===$op?'NOT ':'').$sql,'params'=>array());}
        $ids=array_values(array_filter(array_map('absint',is_array($value)?$value:preg_split('/[\s,]+/',(string)$value))));if(!$ids)return array('sql'=>in_array($op,array('!=','NOT IN'),true)?'1=1':'1=0','params'=>array());
        $ph=implode(',',array_fill(0,count($ids),'%d'));$sql="EXISTS (SELECT 1 FROM {$wpdb->posts} ca_p WHERE ca_p.ID IN ({$ph}) AND ca_p.post_author={$target_expression})";if(in_array($op,array('!=','NOT IN'),true))$sql='NOT '.$sql;return array('sql'=>$sql,'params'=>$ids);
    }
    public function sql_count_condition(string $relationship,string $operator,mixed $value,string $expression,bool $reverse):?array{
        if('authors'!==$relationship)return null;
        global $wpdb;$op=strtoupper($operator);if(!in_array($op,array('=','!=','>','>=','<','<='),true))$op='=';
        $where=$reverse?"ca_p.post_author={$expression}":"ca_p.ID={$expression} AND ca_p.post_author>0";
        return array('sql'=>" (SELECT COUNT(*) FROM {$wpdb->posts} ca_p WHERE {$where}) {$op} %d ",'params'=>array(max(0,(int)$value)));
    }
    private function ids(int $post_id):array{$raw=get_post_meta($post_id,$this->meta_key,true);$ids=array();if(is_array($raw)&&isset($raw['author_ids'])&&is_array($raw['author_ids']))$raw=$raw['author_ids'];foreach((array)$raw as $item){if(is_scalar($item))$id=absint($item);elseif(is_array($item))$id=absint($item['id']??$item['user_id']??$item['author_id']??0);else$id=0;if($id&&get_userdata($id))$ids[]=$id;}if(!$ids){$native=(int)get_post_field('post_author',$post_id);if($native)$ids[]=$native;}return array_values(array_unique($ids));}
    private function preserve_shape(mixed $old,array $ids):array{if(is_array($old)&&array_key_exists('author_ids',$old)){$old['author_ids']=$ids;return $old;}if(is_array($old)&&$old){$first=reset($old);if(is_array($first)){return array_map(static fn($id)=>array('id'=>$id),$ids);}}return $ids;}
    private function serialized_patterns(int $id):array{return array('i:'.$id.';','"'.$id.'"','s:'.strlen((string)$id).':"'.$id.'"');}
}
