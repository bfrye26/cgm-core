<?php
namespace CGM\Core\Objects;

use CGM\Core\Registry\ContentTypeRegistry;
use CGM\Core\Contracts\ObjectAdapterInterface;

final class ObjectResolver {
    /** @var array<string,ObjectAdapterInterface> */
    private array $adapters = array();

    public function __construct( private ContentTypeRegistry $content_types ) {}

    public function register_adapter( ObjectAdapterInterface $adapter ): void {
        $this->adapters[ sanitize_key( $adapter->kind() ) ] = $adapter;
    }

    public function content_type( string $id ): ?array { return $this->content_types->get( $id ); }

    public function reference( mixed $value, ?string $content_type = null ): ?ObjectReference {
        if ( $value instanceof ObjectReference ) { return $value; }
        if ( ! $content_type ) {
            if ( $value instanceof \WP_Post ) { $content_type = 'attachment' === $value->post_type && $this->content_types->has( 'media' ) ? 'media' : $value->post_type; }
            elseif ( $value instanceof \WP_User ) { $content_type = 'user'; }
            elseif ( $value instanceof \WP_Term ) { $content_type = 'term_' . $value->taxonomy; }
            elseif ( is_numeric( $value ) ) {
                $post = get_post( absint( $value ) );
                if ( $post ) { $content_type = 'attachment' === $post->post_type && $this->content_types->has( 'media' ) ? 'media' : $post->post_type; }
            }
        }
        return ObjectReference::from( $value, $content_type );
    }

    public function adapter_for( ObjectReference|string $object ): ?ObjectAdapterInterface {
        $type = $object instanceof ObjectReference ? $object->content_type : sanitize_key( $object );
        $definition = $this->content_types->get( $type );
        $kind = sanitize_key( (string) ( $definition['kind'] ?? '' ) );
        return $this->adapters[ $kind ] ?? null;
    }

    public function exists( ObjectReference $object ): bool {
        $adapter = $this->adapter_for( $object );
        return $adapter ? $adapter->exists( $object ) : false;
    }

    public function label( ObjectReference $object ): string {
        $adapter = $this->adapter_for( $object );
        return $adapter ? $adapter->label( $object ) : $object->key();
    }

    public function url( ObjectReference $object ): string {
        $adapter = $this->adapter_for( $object );
        return $adapter ? $adapter->url( $object ) : '';
    }

    public function edit_url( ObjectReference $object ): string {
        $adapter = $this->adapter_for( $object );
        return $adapter ? $adapter->edit_url( $object ) : '';
    }

    public function is_public( ObjectReference $object ): bool {
        $adapter = $this->adapter_for( $object );
        return $adapter ? $adapter->is_public( $object ) : false;
    }

    public function property( ObjectReference $object, string $property ): mixed {
        $adapter = $this->adapter_for( $object );
        return $adapter ? $adapter->property( $object, $property ) : null;
    }

    public function search( string $content_type, string $search, array $args = array() ): array {
        $definition = $this->content_types->get( $content_type );
        if ( ! $definition ) { return array(); }
        $adapter = $this->adapters[ sanitize_key( (string) ( $definition['kind'] ?? '' ) ) ] ?? null;
        if ( ! $adapter ) { return array(); }
        return $adapter->search( (string) ( $definition['subtype'] ?? $definition['taxonomy'] ?? $content_type ), $search, $args );
    }

    public function serialize( ObjectReference $object ): array {
        return array(
            'content_type' => $object->content_type,
            'id'           => $object->id,
            'label'        => $this->label( $object ),
            'url'          => $this->url( $object ),
            'edit_url'     => $this->edit_url( $object ),
            'public'       => $this->is_public( $object ),
        );
    }
}
