<?php
namespace CGM\Core\Objects\Adapters;
use CGM\Core\Contracts\ObjectAdapterInterface;use CGM\Core\Objects\ObjectReference;
final class CallbackObjectAdapter implements ObjectAdapterInterface {
    public function __construct(private string $object_kind,private array $callbacks){}
    public function kind():string{return sanitize_key($this->object_kind);}
    private function call(string $key,mixed $fallback,ObjectReference $object,mixed ...$extra):mixed{return is_callable($this->callbacks[$key]??null)?call_user_func($this->callbacks[$key],$object,...$extra):$fallback;}
    public function exists(ObjectReference $object):bool{return (bool)$this->call('exists',false,$object);}public function label(ObjectReference $object):string{return (string)$this->call('label',$object->key(),$object);}public function url(ObjectReference $object):string{return (string)$this->call('url','',$object);}public function edit_url(ObjectReference $object):string{return (string)$this->call('edit_url','',$object);}public function is_public(ObjectReference $object):bool{return (bool)$this->call('is_public',false,$object);}public function property(ObjectReference $object,string $property):mixed{return $this->call('property',null,$object,$property);}public function search(string $subtype,string $search,array $args=array()):array{return is_callable($this->callbacks['search']??null)?array_values((array)call_user_func($this->callbacks['search'],$subtype,$search,$args)):array();}
}
