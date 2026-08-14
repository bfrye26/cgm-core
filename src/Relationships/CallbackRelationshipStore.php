<?php
namespace CGM\Core\Relationships;
use CGM\Core\Contracts\QueryableRelationshipStoreInterface;
final class CallbackRelationshipStore implements QueryableRelationshipStoreInterface {
    public function __construct(private array $callbacks){}
    public function supports_sql_condition():bool{return is_callable($this->callbacks['sql_condition']??$this->callbacks['query_sql']??null);}
    public function get(string $r,string $st,int $sid,array $args=array()):array{return is_callable($this->callbacks['get']??null)?array_values((array)call_user_func($this->callbacks['get'],$r,$st,$sid,$args)):array();}
    public function get_reverse(string $r,string $tt,int $tid,array $args=array()):array{return is_callable($this->callbacks['get_reverse']??null)?array_values((array)call_user_func($this->callbacks['get_reverse'],$r,$tt,$tid,$args)):array();}
    public function replace(string $r,string $st,int $sid,string $tt,array $items):bool{return is_callable($this->callbacks['replace']??null)&&(bool)call_user_func($this->callbacks['replace'],$r,$st,$sid,$tt,$items);}
    public function matching_source_ids(string $r,string $st,string $tt,string $op,mixed $v):array{return is_callable($this->callbacks['matching_source_ids']??null)?array_values(array_unique(array_map('absint',(array)call_user_func($this->callbacks['matching_source_ids'],$r,$st,$tt,$op,$v)))):array();}
    public function sql_condition(string $r,string $st,string $tt,string $op,mixed $v,string $src):?array{return $this->call_sql('sql_condition',array($r,$st,$tt,$op,$v,$src))??$this->call_sql('query_sql',array($r,$st,$tt,$op,$v,$src));}
    public function sql_property_condition(string $r,string $st,string $tt,string $property,string $op,mixed $v,string $src):?array{return $this->call_sql('sql_property_condition',array($r,$st,$tt,$property,$op,$v,$src));}
    public function sql_wrap_condition(string $r,string $st,string $tt,string $selector,string $child,array $params,string $src):?array{return $this->call_sql('sql_wrap_condition',array($r,$st,$tt,$selector,$child,$params,$src));}
    public function sql_sort_expression(string $r,string $st,string $tt,string $property,string $selector,string $src):?array{return $this->call_sql('sql_sort_expression',array($r,$st,$tt,$property,$selector,$src));}
    public function sql_reverse_condition(string $r,string $op,mixed $v,string $target):?array{return $this->call_sql('sql_reverse_condition',array($r,$op,$v,$target));}
    public function sql_count_condition(string $r,string $op,mixed $v,string $expr,bool $reverse):?array{return $this->call_sql('sql_count_condition',array($r,$op,$v,$expr,$reverse));}
    private function call_sql(string $key,array $args):?array{if(!is_callable($this->callbacks[$key]??null))return null;$x=call_user_func_array($this->callbacks[$key],$args);return is_array($x)&&isset($x['sql'])?array('sql'=>(string)$x['sql'],'params'=>array_values((array)($x['params']??array()))):null;}
}
