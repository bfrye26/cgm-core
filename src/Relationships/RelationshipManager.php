<?php
namespace CGM\Core\Relationships;

use CGM\Core\Contracts\RelationshipStoreInterface;
use CGM\Core\Contracts\QueryableRelationshipStoreInterface;
use CGM\Core\Events\EventBus;
use CGM\Core\Cache\Cache;
use CGM\Core\Support\VisibilityPolicy;
use CGM\Core\Objects\ObjectResolver;
use CGM\Core\Objects\ObjectReference;

final class RelationshipManager {
    private array $types = array();
    private array $stores = array();
    private ?ObjectResolver $objects = null;
    private ?\WP_Error $last_error = null;

    public function __construct(
        private RelationshipStoreInterface $default_store,
        private EventBus $events,
        private ?Cache $cache = null,
        private ?VisibilityPolicy $visibility = null
    ) { $this->stores['core'] = $default_store; }

    public function set_object_resolver( ObjectResolver $objects ): void { $this->objects = $objects; }
    public function register_store( string $id, RelationshipStoreInterface $store ): void { $this->stores[ sanitize_key( $id ) ] = $store; }
    public function register_type( array $definition ): void { $d = RelationshipSchema::normalize( $definition ); if ( ! $d['id'] ) { return; } $this->types[ $d['id'] ] = $d; do_action( 'cgm_core/relationship_registered', $d ); }
    public function all(): array { return $this->types; }
    public function get_type( string $id ): ?array { return $this->types[ sanitize_key( $id ) ] ?? null; }
    public function stores(): array { return array_keys( $this->stores ); }
    public function last_error(): ?\WP_Error { return $this->last_error; }
    public function store( string $id ): ?RelationshipStoreInterface { return $this->stores[ sanitize_key( $id ) ] ?? null; }
    public function queryable( string $id ): bool {
        $d = $this->get_type( $id ); if ( ! $d || array_key_exists( 'queryable', $d ) && empty( $d['queryable'] ) ) { return false; }
        $store = $this->store_for( $d );
        if ( $store instanceof CallbackRelationshipStore ) { return $store->supports_sql_condition(); }
        return $store instanceof QueryableRelationshipStoreInterface;
    }

    public function sql_property_condition( string $relationship, string $property, string $operator, mixed $value, string $source_expression, string $actual_source_type = '' ): array {
        $d=$this->get_type($relationship);if(!$d)return array('sql'=>'1=0','params'=>array());$source_type=$actual_source_type?:$d['source_type'];$store=$this->store_for($d);if(!($store instanceof QueryableRelationshipStoreInterface))return array('sql'=>'1=0','params'=>array());$compiled=$store->sql_property_condition($d['id'],$source_type,(string)$d['target_type'],$property,$operator,$value,$source_expression);return $compiled?:array('sql'=>'1=0','params'=>array());
    }
    public function sql_wrap_condition( string $relationship, string $selector, string $child_sql, array $child_params, string $source_expression, string $actual_source_type = '' ): array {
        $d=$this->get_type($relationship);if(!$d)return array('sql'=>'1=0','params'=>array());$source_type=$actual_source_type?:$d['source_type'];$store=$this->store_for($d);if(!($store instanceof QueryableRelationshipStoreInterface))return array('sql'=>'1=0','params'=>array());$compiled=$store->sql_wrap_condition($d['id'],$source_type,(string)$d['target_type'],$selector,$child_sql,$child_params,$source_expression);return $compiled?:array('sql'=>'1=0','params'=>array());
    }
    public function sql_sort_expression( string $relationship, string $property, string $selector, string $source_expression, string $actual_source_type = '' ): ?array {
        $d=$this->get_type($relationship);if(!$d)return null;$source_type=$actual_source_type?:$d['source_type'];$store=$this->store_for($d);return $store instanceof QueryableRelationshipStoreInterface?$store->sql_sort_expression($d['id'],$source_type,(string)$d['target_type'],$property,$selector,$source_expression):null;
    }

    /** Reverse reference condition (the queried object is the relationship target). */
    public function sql_reverse_condition( string $relationship, string $operator, mixed $value, string $target_expression, string $actual_target_type = '' ): array {
        $d = $this->get_type( $relationship ); if ( ! $d ) { return array( 'sql'=>'1=0', 'params'=>array() ); }
        $store = $this->store_for( $d );
        if ( $store instanceof QueryableRelationshipStoreInterface ) {
            $compiled = $store->sql_reverse_condition( $d['id'], $operator, $value, $target_expression );
            if ( $compiled ) { return $compiled; }
        }
        do_action( 'cgm_core/relationship_query_unsupported', $d, $operator, $value, $actual_target_type );
        return array( 'sql'=>'1=0', 'params'=>array(), 'unsupported'=>true );
    }

    /** Count of relationship rows for the object, forward or reverse. */
    public function sql_count_condition( string $relationship, string $operator, mixed $value, string $expression, bool $reverse = false ): array {
        $d = $this->get_type( $relationship ); if ( ! $d ) { return array( 'sql'=>'1=0', 'params'=>array() ); }
        $store = $this->store_for( $d );
        if ( $store instanceof QueryableRelationshipStoreInterface ) {
            $compiled = $store->sql_count_condition( $d['id'], $operator, $value, $expression, $reverse );
            if ( $compiled ) { return $compiled; }
        }
        return array( 'sql'=>'1=0', 'params'=>array(), 'unsupported'=>true );
    }

    public function relationship_for_path( string $segment, string $source_type ): ?array {
        $segment = sanitize_key( $segment );
        foreach ( $this->types as $d ) {
            $allowed = (array) ( $d['source_types'] ?? array( $d['source_type'] ) );
            if ( '*' !== $d['source_type'] && $d['source_type'] !== $source_type && ! in_array( $source_type, $allowed, true ) ) { continue; }
            if ( '*' === $d['source_type'] && $allowed && ! in_array( '*', $allowed, true ) && ! in_array( $source_type, $allowed, true ) ) { continue; }
            if ( $d['id'] === $segment || sanitize_key( (string) $d['label'] ) === $segment ) { return $d; }
        }
        return null;
    }

    public function can( string $action, string $id, int $source_id = 0, string $source_type_hint = '' ): bool {
        $d = $this->get_type( $id ); if ( ! $d ) { return false; }
        $cap = (string) ( $d['permissions'][ $action ] ?? ( 'assign' === $action ? $d['assign_capability'] : $d['read_capability'] ) ?? '' );
        if ( ! $cap ) { return true; }
        if ( $source_id && in_array( $action, array( 'read', 'assign' ), true ) && $this->objects ) {
            $source_type = $this->effective_source_type( $d, $source_id, $source_type_hint );
            $ct = $this->objects->content_type( $source_type );
            if ( in_array( (string) ( $ct['kind'] ?? '' ), array( 'post', 'media' ), true ) && current_user_can( 'assign' === $action ? 'edit_post' : 'read_post', $source_id ) ) { return true; }
            if ( 'user' === ( $ct['kind'] ?? '' ) && current_user_can( 'edit_user', $source_id ) ) { return true; }
        }
        return current_user_can( $cap );
    }
    public function can_read( string $id, int $source_id = 0, string $source_type = '' ): bool { return $this->can( 'read', $id, $source_id, $source_type ); }
    public function can_assign( string $id, int $source_id = 0, string $source_type = '' ): bool { return $this->can( 'assign', $id, $source_id, $source_type ); }

    /**
     * Public REST/read gate for a relationship endpoint. The relationship must be
     * declared public and the addressed source/target object must itself be
     * publicly viewable. This prevents a guessed private object ID from becoming
     * a relationship oracle.
     */
    public function public_endpoint_allowed( string $id, int $object_id, bool $reverse = false, string $type_hint = '' ): bool {
        $d = $this->get_type( $id );
        if ( ! $d || empty( $d['public'] ) || $object_id < 1 || ! $this->objects ) { return false; }
        $type = $reverse ? (string) $d['target_type'] : $this->effective_source_type( $d, $object_id, $type_hint );
        // A wildcard target cannot be proven public without a target-type hint.
        if ( '*' === $type ) { $type = sanitize_key( $type_hint ); }
        if ( ! $type ) { return false; }
        $ref = $this->objects->reference( $object_id, $type );
        return $ref && $this->objects->exists( $ref ) && $this->objects->is_public( $ref );
    }

    public function get( string $relationship, int $source_id, array $args = array() ): array {
        $d = $this->get_type( $relationship ); $source_hint = sanitize_key( (string) ( $args['source_type'] ?? '' ) ); if ( ! $d || ( ! $this->can_read( $relationship, $source_id, $source_hint ) && empty( $args['public_only'] ) ) ) { return array(); }
        $source_type = $this->effective_source_type( $d, $source_id, $source_hint );
        $deps = array( 'relationship:' . $d['id'], 'object:' . $source_type . ':' . $source_id );
        $key = 'f:' . $d['id'] . ':' . $source_type . ':' . $source_id . ':' . md5( wp_json_encode( $args ) );
        if ( $this->cache && false !== ( $v = $this->cache->get( $key, 'relationships', $deps ) ) ) { return is_array( $v ) ? $v : array(); }
        $rows = $this->store_for( $d )->get( $d['id'], $source_type, $source_id, $args );
        $rows = $this->filter_visible( $rows, $d, false, ! empty( $args['public_only'] ) );
        if ( $this->cache ) { $this->cache->set( $key, $rows, 300, 'relationships', $deps ); }
        return $rows;
    }

    public function get_reverse( string $relationship, int $target_id, array $args = array() ): array {
        $d = $this->get_type( $relationship ); if ( ! $d ) { return array(); }
        $target_type = (string) $d['target_type'];
        $deps = array( 'relationship:' . $d['id'], 'object:' . $target_type . ':' . $target_id );
        $key = 'r:' . $d['id'] . ':' . $target_type . ':' . $target_id . ':' . md5( wp_json_encode( $args ) );
        if ( $this->cache && false !== ( $v = $this->cache->get( $key, 'relationships', $deps ) ) ) { return is_array( $v ) ? $v : array(); }
        $rows = $this->store_for( $d )->get_reverse( $d['id'], $target_type, $target_id, $args );
        $rows = $this->filter_visible( $rows, $d, true, ! empty( $args['public_only'] ) );
        if ( $this->cache ) { $this->cache->set( $key, $rows, 300, 'relationships', $deps ); }
        return $rows;
    }

    public function replace( string $relationship, int $source_id, array $items ): bool {
        $d = $this->get_type( $relationship ); if ( ! $d || ! $this->can_assign( $relationship, $source_id ) ) { return false; }
        $source_type = $this->effective_source_type( $d, $source_id );
        return $this->replace_internal( $d, $source_type, $source_id, $items );
    }

    private function replace_internal( array $d, string $source_type, int $source_id, array $items ): bool {
        $this->last_error = null;
        if ( ! $this->source_type_allowed( $d, $source_type ) ) { $this->last_error=new \WP_Error('invalid_source_type',__('Source content type is not allowed for this relationship.','cgm-core')); return false; }
        $items = $this->sanitize_items( $d, $items );
        if ( null !== $this->last_error ) { return false; }
        if ( empty( $d['multiple'] ) ) { $items = array_slice( $items, 0, 1 ); }
        if ( ! empty( $d['max_items'] ) ) { $items = array_slice( $items, 0, (int) $d['max_items'] ); }
        if ( empty( $d['primary'] ) ) { foreach ( $items as &$item ) { $item['primary'] = false; } unset( $item ); }
        elseif ( (int) $d['primary_limit'] > 0 ) { $seen = 0; foreach ( $items as &$item ) { if ( ! empty( $item['primary'] ) && ++$seen > (int) $d['primary_limit'] ) { $item['primary'] = false; } } unset( $item ); }
        $before = $this->get( $d['id'], $source_id, array( 'source_type'=>$source_type ) );
        $ok = $this->store_for( $d )->replace( $d['id'], $source_type, $source_id, (string) $d['target_type'], $items );
        if ( $ok ) {
            $this->invalidate( $d, $source_type, $source_id, array_merge( $before, $items ) );
            $payload = array( 'relationship'=>$d['id'], 'source_type'=>$source_type, 'source_id'=>$source_id, 'target_type'=>$d['target_type'], 'before'=>$before, 'items'=>$items, 'schema'=>$d, 'provider'=>$d['provider'] );
            $this->events->dispatch( 'relationship.changed', $payload );
            do_action( 'cgm_core/relationship_changed', $d['id'], $source_id, $items, $d, $before );
        }
        return $ok;
    }

    public function get_for_object( string $relationship, ObjectReference|array|string $source, array $args = array() ): array {
        $ref = $source instanceof ObjectReference ? $source : ( $this->objects ? $this->objects->reference( $source ) : ObjectReference::from( $source ) );
        if ( ! $ref ) { return array(); }
        $args['source_type'] = $ref->content_type;
        return $this->get( $relationship, $ref->id, $args );
    }

    public function replace_for_object( string $relationship, ObjectReference|array|string $source, array $items ): bool {
        $ref = $source instanceof ObjectReference ? $source : ( $this->objects ? $this->objects->reference( $source ) : ObjectReference::from( $source ) );
        if ( ! $ref ) { return false; }
        $definition = $this->get_type( $relationship );
        if ( ! $definition || ! $this->source_type_allowed( $definition, $ref->content_type ) || ! $this->can_assign( $relationship, $ref->id, $ref->content_type ) ) { return false; }
        return $this->replace_internal( $definition, $ref->content_type, $ref->id, $items );
    }

    public function add( string $relationship, int $source_id, array|int $item ): bool { $items = $this->get( $relationship, $source_id ); $items[] = is_array( $item ) ? $item : array( 'id'=>$item ); return $this->replace( $relationship, $source_id, $items ); }
    public function remove( string $relationship, int $source_id, int $target_id ): bool { $items = array_values( array_filter( $this->get( $relationship, $source_id ), static fn($r)=>absint($r['target_id']??$r['id']??0)!==$target_id ) ); return $this->replace( $relationship, $source_id, $items ); }

    public function primary( string $relationship, int $source_id, string $source_type = '' ): ?ObjectReference {
        $d = $this->get_type( $relationship ); if ( ! $d || ! $this->objects ) { return null; }
        $rows = $this->get( $relationship, $source_id, $source_type ? array( 'source_type'=>$source_type ) : array() ); if ( ! $rows ) { return null; }
        $row = null; foreach ( $rows as $candidate ) { if ( ! empty( $candidate['primary'] ) || ! empty( $candidate['is_primary'] ) ) { $row = $candidate; break; } }
        $row ??= $rows[0]; $id = absint( $row['target_id'] ?? 0 ); return $id ? $this->objects->reference( $id, (string) $d['target_type'] ) : null;
    }

    public function objects( string $relationship, int $source_id, array $args = array() ): array {
        $d = $this->get_type( $relationship ); if ( ! $d || ! $this->objects ) { return array(); }
        $out = array(); foreach ( $this->get( $relationship, $source_id, $args ) as $row ) { $ref = $this->objects->reference( absint($row['target_id']??0), (string)$d['target_type'] ); if ( $ref ) { $out[] = $ref; } } return $out;
    }

    public function sql_condition( string $relationship, string $operator, mixed $value, string $source_expression, string $actual_source_type = '' ): array {
        $d = $this->get_type( $relationship ); if ( ! $d || empty( $d['queryable'] ) ) { return array( 'sql'=>'1=0', 'params'=>array() ); }
        $source_type = '*' === (string) $d['source_type'] && $actual_source_type ? sanitize_key( $actual_source_type ) : (string) $d['source_type'];
        $store = $this->store_for( $d );
        if ( $store instanceof QueryableRelationshipStoreInterface ) { $compiled = $store->sql_condition( $d['id'], $source_type, (string)$d['target_type'], $operator, $value, $source_expression ); if ( $compiled ) { return $compiled; } }
        do_action( 'cgm_core/relationship_query_unsupported', $d, $operator, $value, $source_type );
        return array( 'sql'=>'1=0', 'params'=>array(), 'unsupported'=>true );
    }

    public function matching_source_ids( string $relationship, string $operator, mixed $value, int $limit = 5000, string $actual_source_type = '' ): array {
        $d = $this->get_type( $relationship ); if ( ! $d ) { return array(); }
        $source_type = '*' === (string)$d['source_type'] && $actual_source_type ? sanitize_key($actual_source_type) : (string)$d['source_type'];
        $store = $this->store_for( $d );
        if ( method_exists( $store, 'matching_source_ids' ) ) { $ids = array_values(array_unique(array_map('absint',(array)$store->matching_source_ids($d['id'],$source_type,(string)$d['target_type'],$operator,$value)))); return array_slice($ids,0,$limit); }
        return array();
    }

    private function sanitize_items( array $d, array $items ): array {
        $out = array(); $seen = array();
        foreach ( array_values($items) as $index=>$item ) {
            $raw=is_array($item)?$item:array('id'=>$item);$errors=RelationshipSchema::validate_item($raw,$d);
            if($errors){$this->last_error=new \WP_Error('invalid_relationship_item',__('Relationship item failed schema validation.','cgm-core'),array('index'=>$index,'errors'=>$errors));return array();}
            $row = RelationshipSchema::sanitize_item( $raw, $d, $index ); $id = absint($row['id']??0);
            if ( isset($seen[$id]) ) { continue; }
            if ( $this->objects && '*' !== (string)$d['target_type'] ) { $ref = $this->objects->reference( $id, (string)$d['target_type'] ); if ( ! $ref || ! $this->objects->exists($ref) ) { $this->last_error=new \WP_Error('relationship_target_missing',__('Relationship target does not exist.','cgm-core'),array('index'=>$index,'target_id'=>$id,'target_type'=>$d['target_type']));return array(); } }
            $out[]=$row; $seen[$id]=true;
        }
        return $out;
    }

    public function deletion_blockers( ObjectReference $object ): array {
        $blockers=array();
        foreach($this->types as $d){
            if('restrict'!==(string)($d['delete_behavior']??'detach'))continue;
            if(!$this->object_participates($d,$object->content_type))continue;
            if($this->relationship_has_object($d,$object))$blockers[]=$d;
        }
        return $blockers;
    }

    public function purge_object( ObjectReference $object ): array {
        $result=array('detached'=>array(),'cascaded'=>array(),'failed'=>array());
        foreach($this->types as $d){
            if(!$this->object_participates($d,$object->content_type))continue;
            $store=$this->store_for($d);$behavior=(string)($d['delete_behavior']??'detach');
            if('cascade'===$behavior){
                $payload=array('relationship'=>$d['id'],'object'=>$object->jsonSerialize(),'schema'=>$d,'store'=>$d['store']);
                $this->events->dispatch('relationship.delete.cascade',$payload);do_action('cgm_core/relationship_delete_cascade',$payload,$store);
                $result['cascaded'][]=$d['id'];
            }
            $ok=$this->detach_object_from_store($store,$d,$object);
            if($ok){$result['detached'][]=$d['id'];$this->invalidate($d,$object->content_type,$object->id,array());}
            else{$result['failed'][]=$d['id'];}
        }
        return $result;
    }

    private function relationship_has_object( array $d, ObjectReference $object ): bool {
        $store=$this->store_for($d);
        if(method_exists($store,'count_for_object'))return 0<(int)$store->count_for_object($d['id'],$object->content_type,$object->id);
        if($this->source_type_matches($d,$object->content_type)&&$store->get($d['id'],$object->content_type,$object->id))return true;
        if($this->target_type_matches($d,$object->content_type)&&$store->get_reverse($d['id'],$object->content_type,$object->id))return true;
        return false;
    }

    private function detach_object_from_store( RelationshipStoreInterface $store, array $d, ObjectReference $object ): bool {
        if(method_exists($store,'delete_for_object'))return false!==$store->delete_for_object($d['id'],$object->content_type,$object->id);
        $ok=true;
        if($this->source_type_matches($d,$object->content_type))$ok=$store->replace($d['id'],$object->content_type,$object->id,(string)$d['target_type'],array())&&$ok;
        if($this->target_type_matches($d,$object->content_type)){
            foreach((array)$store->get_reverse($d['id'],$object->content_type,$object->id) as $reverse){
                $source_type=sanitize_key((string)($reverse['source_type']??$d['source_type']));$source_id=absint($reverse['source_id']??0);if(!$source_id)continue;
                $items=array_values(array_filter((array)$store->get($d['id'],$source_type,$source_id),static fn($row)=>absint($row['target_id']??0)!==$object->id));
                $ok=$store->replace($d['id'],$source_type,$source_id,(string)$d['target_type'],$items)&&$ok;
            }
        }
        return $ok;
    }

    private function object_participates( array $d, string $content_type ): bool { return $this->source_type_matches($d,$content_type)||$this->target_type_matches($d,$content_type); }
    private function source_type_matches( array $d, string $content_type ): bool { return $this->source_type_allowed($d,$content_type); }
    private function target_type_matches( array $d, string $content_type ): bool { $target=(string)($d['target_type']??'');$allowed=(array)($d['target_types']??array());return '*'===$target||$target===$content_type||in_array($content_type,$allowed,true); }

    private function store_for( array $d ): RelationshipStoreInterface { return $this->stores[ sanitize_key((string)$d['store']) ] ?? $this->default_store; }
    private function effective_source_type( array $d, int $source_id, string $hint = '' ): string {
        $hint = sanitize_key( $hint );
        if ( $hint && $this->source_type_allowed( $d, $hint ) ) { return $hint; }
        if ( '*' !== (string) $d['source_type'] ) { return (string) $d['source_type']; }
        $ref = $this->objects?->reference( $source_id );
        return $ref ? $ref->content_type : ( (array) ( $d['source_types'] ?? array() ) ? (string) reset( $d['source_types'] ) : 'post' );
    }
    private function source_type_allowed( array $d, string $source_type ): bool {
        if ( '*' !== (string) $d['source_type'] && (string) $d['source_type'] === $source_type ) { return true; }
        $allowed = (array) ( $d['source_types'] ?? array() );
        return '*' === (string) $d['source_type'] ? ( ! $allowed || in_array( '*', $allowed, true ) || in_array( $source_type, $allowed, true ) ) : in_array( $source_type, $allowed, true );
    }

    private function filter_visible( array $rows, array $d, bool $reverse, bool $public ): array {
        if ( ! $public && ! is_callable($d['visibility_callback']??null) ) { return $rows; }
        $rows=array_values( array_filter( $rows, function($row)use($d,$reverse,$public){
            $id=absint($row[$reverse?'source_id':'target_id']??0); $type=(string)$d[$reverse?'source_type':'target_type'];
            if ( '*' === $type && $reverse ) { $type = sanitize_key((string)($row['source_type']??'post')); }
            if ( is_callable($d['visibility_callback']??null) && !call_user_func($d['visibility_callback'],$id,$type,$row,$public) ) { return false; }
            if ( $public && $this->objects && '*' !== $type ) { $ref=$this->objects->reference($id,$type); return $ref && $this->objects->is_public($ref); }
            return !$public || !$this->visibility || $this->visibility->is_public($type,$id);
        } ) );
        return $public?array_map(fn($row)=>$this->public_row($row,$d),$rows):$rows;
    }

    private function public_row( array $row, array $d ): array {
        $public_meta=array();foreach((array)($d['metadata_schema']??array()) as $key=>$def){if(empty($def['public']))continue;if(array_key_exists($key,(array)($row['meta']??array())))$public_meta[$key]=$row['meta'][$key];}
        $safe=array(
            'source_type'=>sanitize_key((string)($row['source_type']??'')),'source_id'=>absint($row['source_id']??0),
            'target_type'=>sanitize_key((string)($row['target_type']??$d['target_type']??'')),'target_id'=>absint($row['target_id']??0),
            'order'=>intval($row['order']??$row['sort_order']??0),'sort_order'=>intval($row['sort_order']??$row['order']??0),
            'primary'=>!empty($row['primary'])||!empty($row['is_primary']),'is_primary'=>!empty($row['primary'])||!empty($row['is_primary']),'meta'=>$public_meta,
        );
        if(!empty($d['public_role']))$safe['role']=sanitize_key((string)($row['role']??''));
        foreach($public_meta as $key=>$value)$safe[$key]=$value;
        return $safe;
    }

    private function invalidate( array $d, string $source_type, int $source_id, array $items ): void {
        if ( ! $this->cache ) { return; }
        $this->cache->bump( 'relationship:'.$d['id'] ); $this->cache->bump( 'object:'.$source_type.':'.$source_id );
        foreach($items as $item){$target=absint($item['target_id']??$item['id']??0);if($target)$this->cache->bump('object:'.$d['target_type'].':'.$target);}
        do_action('cgm_core/cache_dependency_changed','relationship:'.$d['id'],array('source_id'=>$source_id,'items'=>$items));
    }
}
