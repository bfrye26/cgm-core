<?php
namespace CGM\Core\Relationships;

use CGM\Core\Plugin;
use CGM\Core\Objects\ObjectReference;

/** Turns every registered relationship into reusable dynamic data, context and editor capabilities. */
final class RelationshipFeatures {
    public function __construct( private Plugin $core ) {}

    public function register(): void {
        foreach ( $this->core->relationships()->all() as $id => $type ) {
            $this->register_dynamic_data( (string) $id, $type );
            $this->register_context( (string) $id, $type );
            if ( 'core' === ( $type['store'] ?? 'core' ) ) { $this->register_editor_controls( (string) $id, $type ); }
        }
    }

    private function source_ref( mixed $object ): ?ObjectReference {
        return $this->core->objects()->reference( $object ?: ( $GLOBALS['cgm_core_query_object'] ?? get_the_ID() ) );
    }

    private function rows( string $id, mixed $object ): array {
        $ref = $this->source_ref( $object );
        if ( ! $ref ) { return array(); }
        return $this->core->relationships()->get( $id, $ref->id, array( 'source_type'=>$ref->content_type, 'public_only'=>! current_user_can( 'inspect_cgm_data' ) ) );
    }

    private function register_dynamic_data( string $id, array $type ): void {
        $label = (string) ( $type['label'] ?? $id );
        $base = array( 'group'=>'Relationships', 'provider'=>(string)($type['provider']??'core'), 'public'=>!empty($type['public']) );

        $this->core->dynamic_data()->register( array_merge( $base, array(
            'id'=>'relationship.'.$id.'.ids', 'label'=>sprintf( __( '%s IDs', 'cgm-core' ), $label ), 'type'=>'array',
            'resolve'=>fn( mixed $object ): array => array_values( array_filter( array_map( static fn( array $row ): int => absint($row['target_id']??0), $this->rows($id,$object) ) ) ),
        ) ) );
        $this->core->dynamic_data()->register( array_merge( $base, array(
            'id'=>'relationship.'.$id.'.count', 'label'=>sprintf( __( '%s count', 'cgm-core' ), $label ), 'type'=>'integer',
            'resolve'=>fn( mixed $object ): int => count( $this->rows($id,$object) ),
        ) ) );
        $this->core->dynamic_data()->register( array_merge( $base, array(
            'id'=>'relationship.'.$id.'.labels', 'label'=>sprintf( __( '%s labels', 'cgm-core' ), $label ), 'type'=>'string',
            'resolve'=>function( mixed $object ) use($id,$type):string { $labels=array(); foreach($this->rows($id,$object) as $row){$target=absint($row['target_id']??0);if(!$target)continue;$ref=new ObjectReference((string)$type['target_type'],$target);$labels[]=$this->core->objects()->label($ref);}return implode(', ',array_filter($labels)); },
        ) ) );
        $this->core->dynamic_data()->register( array_merge( $base, array(
            'id'=>'relationship.'.$id.'.objects', 'label'=>sprintf( __( '%s objects', 'cgm-core' ), $label ), 'type'=>'array',
            'resolve'=>function( mixed $object ) use($id,$type):array { $out=array();foreach($this->rows($id,$object) as $row){$target=absint($row['target_id']??0);if(!$target)continue;$ref=new ObjectReference((string)$type['target_type'],$target);$serialized=$this->core->objects()->serialize($ref);$serialized['relationship']=$row;$out[]=$serialized;}return $out; },
        ) ) );

        if ( ! empty( $type['primary'] ) ) {
            foreach ( array('id','label','url','image') as $property ) {
                $this->core->dynamic_data()->register( array_merge( $base, array(
                    'id'=>'relationship.'.$id.'.primary.'.$property,
                    'label'=>sprintf( __( 'Primary %1$s %2$s', 'cgm-core' ), $label, $property ),
                    'type'=>'id'===$property?'integer':('image'===$property?'media':('url'===$property?'url':'string')),
                    'resolve'=>function( mixed $object ) use($id,$property):mixed { $source=$this->source_ref($object);if(!$source)return null;$ref=$this->core->relationships()->primary($id,$source->id,$source->content_type);if(!$ref)return null;return match($property){'id'=>$ref->id,'label'=>$this->core->objects()->label($ref),'url'=>$this->core->objects()->url($ref),'image'=>$this->core->objects()->property($ref,'featured_image_id'),default=>null}; },
                ) ) );
            }
        }
    }

    private function register_context( string $id, array $type ): void {
        if ( empty( $type['primary'] ) ) { return; }
        $key='current_'.sanitize_key($id);
        $this->core->context()->register($key,sprintf(__( 'Current %s','cgm-core'),strtolower((string)($type['label']??$id))),function(array $context)use($id,$type):int{
            $current=$context['current_query_object']??($context['current_query_item']??$context['post_id']??0);
            $ref=$this->core->objects()->reference($current,isset($context['current_query_type'])?(string)$context['current_query_type']:null);
            if(!$ref&&absint($context['post_id']??0))$ref=$this->core->objects()->reference(absint($context['post_id']));
            if(!$ref)return 0;
            if($ref->content_type===(string)$type['target_type'])return $ref->id;
            $primary=$this->core->relationships()->primary($id,$ref->id,$ref->content_type);
            return $primary?$primary->id:0;
        });
    }

    private function register_editor_controls( string $id, array $type ): void {
        $source_ids=(array)($type['source_types']??array($type['source_type']??''));
        if('*'!==($type['source_type']??'')&&!in_array((string)$type['source_type'],$source_ids,true))$source_ids[]=(string)$type['source_type'];
        foreach(array_unique(array_filter($source_ids)) as $source_id){
            if('*'===$source_id)continue;$source=$this->core->content_types()->get($source_id);$target=$this->core->content_types()->get((string)$type['target_type']);
            if(!$source||!$target||!in_array((string)($source['kind']??''),array('post','media'),true))continue;
            $post_type='media'===($source['kind']??'')?'attachment':(string)($source['subtype']??$source['id']);
            $this->core->editor_controls()->register(array(
                'id'=>'relationship_'.$id.'_'.sanitize_key($source_id),'label'=>(string)($type['label']??$id),'post_types'=>array($post_type),'multiple'=>!empty($type['multiple']),'kind'=>'relationship','relationship'=>$id,'schema'=>$type,
                'get'=>function(int $post_id)use($id,$type,$source_id):array{$out=array();foreach($this->core->relationships()->get($id,$post_id,array('source_type'=>$source_id)) as $row){$target_id=absint($row['target_id']??0);if(!$target_id)continue;$ref=$this->core->objects()->reference($target_id,(string)$type['target_type']);if(!$ref)continue;$out[]=array('id'=>$target_id,'label'=>$this->core->objects()->label($ref),'primary'=>!empty($row['primary'])||!empty($row['is_primary']),'role'=>(string)($row['role']??''),'order'=>intval($row['order']??$row['sort_order']??0),'meta'=>(array)($row['meta']??array()));}return $out;},
                'search'=>fn(string $search):array=>$this->core->objects()->search((string)$type['target_type'],$search,array('limit'=>30)),
                'set'=>fn(int $post_id,array $items):bool=>$this->core->relationships()->replace_for_object($id,new ObjectReference($source_id,$post_id),$items),
            ));
        }
    }
}
