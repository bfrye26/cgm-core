<?php
namespace CGM\Core\Query;

use CGM\Core\Plugin;
use CGM\Core\Cache\Cache;

final class QueryEngine {
    public function __construct(
        private Plugin $core,
        private SavedQueryRepository $repository,
        private QueryValidator $validator,
        private Cache $cache
    ) {}

    public function run( array|string|int $query, array $context = array() ): QueryResult {
        $started = microtime( true ); $saved = null;
        if ( is_string( $query ) || is_int( $query ) ) {
            $saved = $this->repository->find( $query );
            if ( ! $saved ) { return new QueryResult( array(), 0, 1, 0, array( 'error'=>'Query not found.' ) ); }
            $query = $saved['definition'];
            $this->repository->record_usage( $saved['slug'], (string) ( $context['consumer'] ?? 'php' ), (string) ( $context['location'] ?? '' ) );
        }
        $query = $this->validator->normalize( $query );
        $ctx = $this->core->context()->resolve( $context );
        $query = $this->core->context()->replace_tokens( $query, $ctx );
        $content_type = $this->core->content_types()->get( (string) $query['content_type'] );
        if ( ! $content_type ) { return new QueryResult( array(), 0, 1, 0, array( 'error'=>'Unsupported content type.' ) ); }

        $public_only = ! empty( $ctx['public_request'] );
        if ( $public_only && empty( $content_type['public'] ) ) { return new QueryResult( array(), 0, 1, (int)$query['limit'], array( 'error'=>'This content type is not publicly queryable.' ) ); }
        if ( in_array( (string) ( $content_type['kind'] ?? '' ), array( 'post','media' ), true ) && ( $public_only || ! current_user_can( 'edit_posts' ) ) ) {
            $query['status'] = 'media' === ( $content_type['kind'] ?? '' ) ? array( 'inherit' ) : array( 'publish' );
        }

        $provider = $this->core->query_providers()->for_content_type( $content_type );
        if ( ! $provider ) { return new QueryResult( array(), 0, 1, 0, array( 'error'=>'No query provider is registered for this content type.' ) ); }
        $unsupported_relationships = $this->unsupported_relationships( (array) $query['filters'], (string) $content_type['id'] );
        if ( $unsupported_relationships ) {
            return new QueryResult( array(), 0, (int)$query['page'], (int)$query['limit'], array(
                'error' => 'One or more relationship providers do not expose a query-safe SQL condition contract.',
                'unsupported_relationships' => $unsupported_relationships,
            ) );
        }
        $dependencies = $this->dependencies( $query, $content_type );
        $cache_key = 'query:' . md5( wp_json_encode( array( $query, $this->cache_context($ctx), $provider->id(), $public_only ) ) );
        if ( $query['cache'] && false !== ( $cached = $this->cache->get( $cache_key, 'query', $dependencies ) ) && $cached instanceof QueryResult ) {
            $cached->debug['cache'] = 'hit'; $cached->debug['execution_ms'] = round( ( microtime(true)-$started )*1000, 2 ); return $cached;
        }

        $result = $provider->run( $query, $ctx, $content_type );
        $provider_deps = (array) ( $result->debug['dependencies'] ?? array() );
        $dependencies = array_values( array_unique( array_merge( $dependencies, $provider_deps ) ) );
        if ( $public_only ) {
            $result->items = $this->public_filter( $result->items, $content_type );
            if ( 1 === $result->page && count($result->items) < $result->per_page ) { $result->total = count($result->items); }
        }
        $result->debug['saved_query'] = $saved['id'] ?? null;
        $result->debug['saved_query_slug'] = $saved['slug'] ?? null;
        $result->debug['normalized'] = $query;
        $result->debug['context'] = $ctx;
        $result->debug['dependencies'] = $dependencies;
        $result->debug['cache'] = 'miss';
        $result->debug['execution_ms_total'] = round( ( microtime(true)-$started )*1000, 2 );
        if ( $query['cache'] ) { $this->cache->set( $cache_key, $result, max(15,absint($query['cache_ttl']??120)), 'query', $dependencies ); }
        do_action( 'cgm_core/query_executed', $query, $ctx, $result, $content_type );
        return $result;
    }

    public function explain( array|string|int $query, array $context = array() ): array {
        $saved = null;
        if ( is_string($query) || is_int($query) ) { $saved=$this->repository->find($query); if(!$saved)return array('error'=>'Query not found.'); $query=$saved['definition']; }
        $query=$this->validator->normalize($query); $ctx=$this->core->context()->resolve($context); $query=$this->core->context()->replace_tokens($query,$ctx);
        $content_type=$this->core->content_types()->get((string)$query['content_type']); if(!$content_type)return array('error'=>'Unsupported content type.');
        $provider=$this->core->query_providers()->for_content_type($content_type); if(!$provider)return array('error'=>'No query provider.');
        $unsupported_relationships=$this->unsupported_relationships((array)$query['filters'],(string)$content_type['id']);
        return array(
            'saved_query'=>$saved?array_intersect_key($saved,array_flip(array('id','slug','title','managed_by'))):null,
            'query'=>$query,'context'=>$ctx,'content_type'=>$content_type,'provider'=>$provider->id(),
            'dependencies'=>$this->dependencies($query,$content_type),'unsupported_relationships'=>$unsupported_relationships,'explain'=>$unsupported_relationships?array('status'=>'unsupported-relationship-query'):$provider->explain($query,$ctx,$content_type),
        );
    }

    public function ids_for( array|string|int $query, array $context = array() ): array {
        return array_values( array_filter( array_map( static function($item):int {
            if($item instanceof \WP_Post||$item instanceof \WP_User)return (int)$item->ID;
            if($item instanceof \WP_Term)return (int)$item->term_id;
            return absint($item);
        }, $this->run($query,$context)->items ) ) );
    }

    /** Aggregate a query: COUNT/SUM/AVG/MIN/MAX grouped by a field or taxonomy. */
    public function aggregate( array|string|int $query, array $context = array() ): array {
        if ( is_string( $query ) || is_int( $query ) ) {
            $saved = $this->repository->find( $query );
            if ( ! $saved ) { return array( 'error' => 'Query not found.' ); }
            $query = $saved['definition'];
        }
        $query = $this->validator->normalize( $query );
        $ctx = $this->core->context()->resolve( $context );
        $query = $this->core->context()->replace_tokens( $query, $ctx );
        $content_type = $this->core->content_types()->get( (string) $query['content_type'] );
        if ( ! $content_type ) { return array( 'error' => 'Unsupported content type.' ); }
        $provider = $this->core->query_providers()->for_content_type( $content_type );
        if ( ! $provider || ! method_exists( $provider, 'run_aggregate' ) ) { return array( 'error' => 'Aggregation is not supported for this content type.' ); }
        return $provider->run_aggregate( $query, $ctx, $content_type );
    }

    private function dependencies( array $query, array $content_type ): array {
        $deps=array('content:'.(string)$content_type['id']);
        if(''!==(string)($query['search']??''))$deps[]='content.search';
        $source_type=(string)$content_type['id'];
        $add_path=function(string $path)use(&$deps,$source_type):void{
            $path=trim($path);if(''===$path)return;$deps[]='path:'.$path;
            $segments=array_values(array_filter(array_map('trim',explode('.',$path))));
            if('relationship'===($segments[0]??''))array_shift($segments);
            $rel=$segments?$this->core->relationships()->relationship_for_path((string)$segments[0],$source_type):null;
            if($rel)$deps[]='relationship:'.sanitize_key((string)$rel['id']);
        };
        $walk=function(array $group)use(&$walk,&$deps,$add_path):void{
            foreach((array)($group['rules']??array()) as $rule){
                if(!is_array($rule))continue;
                if(isset($rule['rules'])||isset($rule['relation'])){$walk($rule);continue;}
                $type=sanitize_key((string)($rule['type']??'field'));
                if(in_array($type,array('relationship','relationship_property','relationship_reverse','relationship_count'),true)&&($rule['relationship']??''))$deps[]='relationship:'.sanitize_key((string)$rule['relationship']);
                elseif('path'===$type&&($rule['path']??''))$add_path((string)$rule['path']);
                elseif('taxonomy'===$type&&($rule['taxonomy']??''))$deps[]='taxonomy:'.sanitize_key((string)$rule['taxonomy']);
                elseif($rule['field']??'')$deps[]='field:'.sanitize_text_field((string)$rule['field']);
            }
        };
        $walk((array)$query['filters']);
        foreach((array)$query['sort'] as $sort){
            if(($sort['path']??'')!=='')$add_path((string)$sort['path']);
            elseif($sort['field']??'')$deps[]='field:'.sanitize_text_field((string)$sort['field']);
        }
        return array_values(array_unique($deps));
    }

    private function unsupported_relationships( array $group, string $source_type = '' ): array {
        $out=array();
        foreach((array)($group['rules']??array()) as $rule){
            if(!is_array($rule))continue;
            if(isset($rule['rules'])||isset($rule['relation'])){$out=array_merge($out,$this->unsupported_relationships($rule,$source_type));continue;}
            $type=sanitize_key((string)($rule['type']??'field'));
            if(in_array($type,array('relationship','relationship_property','relationship_reverse','relationship_count'),true)){
                $id=sanitize_key((string)($rule['relationship']??''));
                if($id&&!$this->core->relationships()->queryable($id))$out[]=$id;
                continue;
            }
            if('path'===$type&&($rule['path']??'')){
                $segments=array_values(array_filter(array_map('trim',explode('.',(string)$rule['path']))));
                if('relationship'===($segments[0]??''))array_shift($segments);
                $rel=$segments?$this->core->relationships()->relationship_for_path((string)$segments[0],$source_type):null;
                if($rel&&!$this->core->relationships()->queryable((string)$rel['id']))$out[]=(string)$rel['id'];
            }
        }
        return array_values(array_unique($out));
    }

    private function public_filter( array $items, array $content_type ): array {
        $out=array(); foreach($items as $item){$ref=$this->core->objects()->reference($item,(string)$content_type['id']);if($ref&&$this->core->objects()->is_public($ref))$out[]=$item;} return $out;
    }
    private function cache_context( array $ctx ): array {
        $keys=array('current_post','current_parent','current_user','current_author','current_term','current_taxonomy','current_query_item','parent_query_item','public_request');
        foreach($this->core->context()->registered_keys() as $key)$keys[]=$key; return array_intersect_key($ctx,array_flip(array_unique($keys)));
    }
}
