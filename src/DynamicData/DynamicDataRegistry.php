<?php
namespace CGM\Core\DynamicData;

final class DynamicDataRegistry {
    private array $items = array();
    private $fallback_resolver = null;
    public function set_fallback_resolver( callable $resolver ): void { $this->fallback_resolver = $resolver; }
    public function register( array $definition ): void { $original=trim((string)($definition['id']??''));if(!$original)return;$definition=wp_parse_args($definition,array('label'=>$original,'type'=>'string','group'=>'CGM Core','resolve'=>null,'provider'=>'core','description'=>'','public'=>true));$definition['id']=$original;$this->items[$original]=$definition; }
    public function all(): array { return $this->items; }
    public function get( string $id ): ?array { return $this->items[ $id ] ?? null; }
    public function resolve( string $id, mixed $object = null, array $context = array() ): mixed {
        $def = $this->get( $id );
        if ( $def && is_callable( $def['resolve'] ?? null ) ) { $value = call_user_func( $def['resolve'], $object, $context, $def ); }
        elseif ( $this->fallback_resolver ) { $value = call_user_func( $this->fallback_resolver, $id, $object, $context ); }
        else { $value = null; }
        // Optional fallback when the value resolves empty.
        if ( ( null === $value || '' === $value || array() === $value ) && $def && array_key_exists( 'fallback', $def ) ) { $value = $def['fallback']; }
        // Optional formatter, e.g. date, truncation or a callable plugin transform.
        if ( $def && is_callable( $def['format'] ?? null ) && null !== $value ) { $value = call_user_func( $def['format'], $value, $object, $context, $def ); }
        return $value;
    }
    public function resolve_typed( string $id, mixed $object = null, array $context = array() ): DynamicValue {
        $def = $this->get( $id ) ?? array( 'id'=>$id, 'label'=>$id, 'type'=>'mixed', 'provider'=>'traversal' );
        return new DynamicValue( $this->resolve( $id, $object, $context ), (string) ( $def['type'] ?? 'mixed' ), $def );
    }
    public function serialize(): array { return array_map(static function($d){unset($d['resolve']);return $d;},$this->items); }
}
