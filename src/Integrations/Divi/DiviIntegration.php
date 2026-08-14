<?php
namespace CGM\Core\Integrations\Divi;

use CGM\Core\Plugin;
use CGM\Core\Query\SavedQueryRepository;
use CGM\Core\Contracts\BuilderAdapterInterface;

/** Stable Divi bridge. Does not depend on undocumented Divi internals. */
final class DiviIntegration implements BuilderAdapterInterface {
    public function id(): string { return 'divi'; }
    public function detected(): bool { return defined( 'ET_BUILDER_VERSION' ) || function_exists( 'et_setup_theme' ); }
    public function capabilities(): array { return array('dynamic-data-bridge','saved-query-bridge','condition-bridge','shortcodes','context'); }

    public function __construct( private Plugin $core, private SavedQueryRepository $repo ) {}

    public function register(): void {
        add_filter( 'cgm_core/divi/dynamic_data', fn( array $data ): array => array_merge( $data, $this->dynamic_descriptors() ) );
        add_filter( 'cgm_core/divi/saved_queries', fn( array $data ): array => array_merge( $data, $this->query_descriptors() ) );
        add_filter( 'cgm_core/divi/resolve_data', array( $this, 'resolve_data' ), 10, 4 );
        add_filter( 'cgm_core/divi/run_query', array( $this, 'run_query' ), 10, 4 );
        add_filter( 'cgm_core/divi/evaluate_condition', array( $this, 'evaluate_condition' ), 10, 5 );
        do_action( 'cgm_core/integration/divi/registered', $this->core, $this->repo );
    }

    public function resolve_data( mixed $default, string $key, int $object_id = 0, array $context = array() ): mixed {
        return $key ? $this->core->dynamic_data()->resolve( $key, $object_id ?: null, $context ) : $default;
    }
    public function run_query( mixed $default, string|int $query, int $post_id = 0, array $context = array() ): mixed {
        return '' === (string)$query ? $default : $this->core->queries()->run( $query, $context + array('post_id'=>$post_id?:get_the_ID(),'consumer'=>'divi') );
    }
    public function evaluate_condition( bool $default, string $key, string $operator, mixed $value, int $object_id = 0 ): bool {
        if(!$key)return $default; return cgm_builder_condition($key,$operator,$value,$object_id?:null);
    }
    private function dynamic_descriptors():array{$out=array();foreach($this->core->dynamic_data()->all() as $id=>$d)$out[$id]=array('id'=>$id,'label'=>$d['label']??$id,'type'=>$d['type']??'string','group'=>$d['group']??'CGM Core','shortcode'=>'[cgm_data key="'.$id.'"]');$out['__traversal__']=array('label'=>'CGM traversal path','supports_arbitrary_path'=>true,'shortcode'=>'[cgm_data key="game.primary.developer.name"]');return $out;}
    private function query_descriptors():array{$out=array();foreach($this->repo->list() as $q){$slug=(string)($q['slug']??'');if($slug)$out[$slug]=array('id'=>$q['id']??$slug,'label'=>$q['title']??$slug,'shortcode'=>'[cgm_query id="'.$slug.'"]','managed_by'=>$q['managed_by']??'database');}return $out;}
}
