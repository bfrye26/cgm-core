<?php
namespace CGM\Core\Integrations\Mosaic;

use CGM\Core\Plugin;
use CGM\Core\Query\SavedQueryRepository;
use CGM\Core\Contracts\BuilderAdapterInterface;

/** Stable Mosaic data/query source bridge, ready for Mosaic's public extension layer. */
final class MosaicIntegration implements BuilderAdapterInterface {
    public function id(): string { return 'mosaic'; }
    public function detected(): bool { return defined( 'MOSAIC_BUILDER_VERSION' ) || class_exists( 'Mosaic\\Builder' ); }
    public function capabilities(): array { return array('dynamic-source-bridge','query-source-bridge','condition-bridge','shortcodes','context'); }

    public function __construct( private Plugin $core, private SavedQueryRepository $repo ) {}
    public function register():void{
        add_filter('cgm_core/mosaic/dynamic_sources',fn(array $d):array=>array_merge($d,$this->dynamic_sources()));
        add_filter('cgm_core/mosaic/query_sources',fn(array $d):array=>array_merge($d,$this->query_sources()));
        add_filter('cgm_core/mosaic/resolve_data',array($this,'resolve_data'),10,4);
        add_filter('cgm_core/mosaic/run_query',array($this,'run_query'),10,4);
        add_filter('cgm_core/mosaic/evaluate_condition',array($this,'evaluate_condition'),10,5);
        do_action('cgm_core/integration/mosaic/registered',$this->core,$this->repo);
    }
    public function resolve_data(mixed $default,string $key,int $object_id=0,array $context=array()):mixed{return $key?$this->core->dynamic_data()->resolve($key,$object_id?:null,$context):$default;}
    public function run_query(mixed $default,string|int $query,int $post_id=0,array $context=array()):mixed{return ''===(string)$query?$default:$this->core->queries()->run($query,$context+array('post_id'=>$post_id?:get_the_ID(),'consumer'=>'mosaic'));}
    public function evaluate_condition(bool $default,string $key,string $operator,mixed $value,int $object_id=0):bool{return $key?cgm_builder_condition($key,$operator,$value,$object_id?:null):$default;}
    private function dynamic_sources():array{$out=array();foreach($this->core->dynamic_data()->all() as $id=>$d)$out[$id]=array('id'=>$id,'label'=>$d['label']??$id,'type'=>$d['type']??'string','provider'=>'cgm-core','resolver'=>'cgm_data');$out['__traversal__']=array('id'=>'__traversal__','label'=>'CGM traversal path','provider'=>'cgm-core','supports_arbitrary_path'=>true,'resolver'=>'cgm_data');return $out;}
    private function query_sources():array{$out=array();foreach($this->repo->list() as $q){$slug=(string)($q['slug']??'');if($slug)$out[$slug]=array('id'=>$q['id']??$slug,'label'=>$q['title']??$slug,'provider'=>'cgm-core','resolver'=>'cgm_query');}return $out;}
}
