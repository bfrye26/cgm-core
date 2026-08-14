<?php
namespace CGM\Core\Registry;
abstract class AbstractRegistry {
    protected array $items = array();
    public function register( array $definition ): void {
        $id = sanitize_key( (string) ( $definition['id'] ?? '' ) );
        if ( ! $id ) { return; }
        $definition['id'] = $id;
        $this->items[ $id ] = $definition;
    }
    public function get( string $id ): ?array { return $this->items[ sanitize_key( $id ) ] ?? null; }
    public function all(): array { return $this->items; }
    public function has( string $id ): bool { return isset( $this->items[ sanitize_key( $id ) ] ); }
}
