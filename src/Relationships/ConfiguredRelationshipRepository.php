<?php
namespace CGM\Core\Relationships;
final class ConfiguredRelationshipRepository {
    private const OPTION='cgm_core_relationship_types';private array $code=array();
    public function all():array{$rows=$this->stored();foreach($this->code as $id=>$row)$rows[$id]=$row;return $rows;}
    public function stored():array{$rows=get_option(self::OPTION,array());$rows=is_array($rows)?$rows:array();$out=array();foreach($rows as $key=>$row){if(!is_array($row))continue;$d=RelationshipSchema::normalize($row);if($d['id']){$d['managed_by']='ui';$out[$d['id']]=$d;}}return $out;}
    /** Return only relationship definitions managed through the WordPress UI. */
    public function all_ui(): array { return $this->stored(); }
    public function register_code(array $row):void{$d=RelationshipSchema::normalize($row);if($d['id']){$d['managed_by']='code';$this->code[$d['id']]=$d;}}
    public function code_definitions():array{return $this->code;}
    public function save(array $rows):bool{$clean=array();foreach($rows as $row){if(!is_array($row))continue;$d=RelationshipSchema::normalize($row);if(!$d['id']||isset($this->code[$d['id']]))continue;$d['provider']='core-config';$d['store']='core';$d['managed_by']='ui';unset($d['visibility_callback'],$d['query_sql']);$clean[$d['id']]=$d;}return update_option(self::OPTION,$clean,false);}
    public function register(RelationshipManager $manager):void{do_action('cgm_core/register_relationship_definitions',$this);foreach($this->all() as $row)$manager->register_type($row);}
}
