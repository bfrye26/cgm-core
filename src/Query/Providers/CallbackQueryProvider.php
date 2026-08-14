<?php
namespace CGM\Core\Query\Providers;
use CGM\Core\Contracts\QueryProviderInterface;use CGM\Core\Query\QueryResult;
final class CallbackQueryProvider implements QueryProviderInterface {
    public function __construct(private string $provider_id,private array $callbacks,private array $content_types=array()){}
    public function id():string{return sanitize_key($this->provider_id);}public function supports(array $ct):bool{return in_array((string)($ct['id']??''),$this->content_types,true)||is_callable($this->callbacks['supports']??null)&&(bool)call_user_func($this->callbacks['supports'],$ct);}
    public function run(array $query,array $context,array $content_type):QueryResult{$r=is_callable($this->callbacks['run']??null)?call_user_func($this->callbacks['run'],$query,$context,$content_type):null;if($r instanceof QueryResult)return $r;if(is_array($r)&&isset($r['items']))return new QueryResult((array)$r['items'],(int)($r['total']??count($r['items'])),(int)($query['page']??1),(int)($query['limit']??10),(array)($r['debug']??array()));return new QueryResult(array(),0,(int)($query['page']??1),(int)($query['limit']??10),array('provider'=>$this->id(),'error'=>'Provider did not return a QueryResult.'));}
    public function explain(array $query,array $context,array $content_type):array{return is_callable($this->callbacks['explain']??null)?(array)call_user_func($this->callbacks['explain'],$query,$context,$content_type):array('provider'=>$this->id(),'strategy'=>'callback-provider');}
}
