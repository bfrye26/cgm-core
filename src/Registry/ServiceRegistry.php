<?php
namespace CGM\Core\Registry;

use CGM\Core\Support\VersionConstraint;

final class ServiceRegistry {
    private array $items = array();
    private array $definitions = array();

    public function register( string $id, object|callable $service, array $definition = array() ): void {
        $id = sanitize_key( $id ); if ( ! $id ) { return; }
        $this->items[ $id ] = $service;
        $this->definitions[ $id ] = wp_parse_args( $definition, array(
            'id'=>$id, 'label'=>$id, 'version'=>'1.0', 'provider'=>'core', 'public'=>false,
            'capabilities'=>array(), 'description'=>'',
        ) );
        $this->definitions[ $id ]['capabilities'] = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $this->definitions[ $id ]['capabilities'] ) ) ) );
    }

    public function has( string $id ): bool { return isset( $this->items[ sanitize_key( $id ) ] ); }
    public function get( string $id ): object|callable|null { return $this->items[ sanitize_key( $id ) ] ?? null; }
    public function definition( string $id ): ?array { return $this->definitions[ sanitize_key($id) ] ?? null; }
    public function all(): array { return $this->items; }
    public function definitions(): array { return $this->definitions; }

    public function compatible( string $id, string $constraint = '*' ): bool {
        $definition = $this->definition( $id );
        return $definition ? VersionConstraint::matches( (string) ( $definition['version'] ?? '0' ), $constraint ) : false;
    }

    public function require( string $id, string $constraint = '*' ): object|callable|null {
        return $this->compatible( $id, $constraint ) ? $this->get( $id ) : null;
    }
}
