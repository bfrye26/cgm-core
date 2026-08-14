<?php
namespace CGM\Core\Cache;

final class Cache {
    private const GROUP = 'cgm_core';
    private const GLOBAL_EPOCH = 'cgm_core_cache_epoch';
    private const TAG_EPOCHS = 'cgm_core_cache_tag_epochs';
    private const NS_EPOCHS = 'cgm_core_cache_namespace_epochs';

    public function get( string $key, string|array $namespace = 'default', array $dependencies = array() ): mixed {
        if ( is_array( $namespace ) ) { $dependencies = $namespace; $namespace = 'default'; }
        return wp_cache_get( $this->key( $key, $namespace, $dependencies ), self::GROUP );
    }
    public function set( string $key, mixed $value, int $ttl = 300, string|array $namespace = 'default', array $dependencies = array() ): bool {
        if ( is_array( $namespace ) ) { $dependencies = $namespace; $namespace = 'default'; }
        return wp_cache_set( $this->key( $key, $namespace, $dependencies ), $value, self::GROUP, $ttl );
    }
    public function delete( string $key, string|array $namespace = 'default', array $dependencies = array() ): bool {
        if ( is_array( $namespace ) ) { $dependencies = $namespace; $namespace = 'default'; }
        return wp_cache_delete( $this->key( $key, $namespace, $dependencies ), self::GROUP );
    }
    // ponytail: read-modify-write on a serialized options blob is not atomic —
    // concurrent bumps of the same tag can lose an increment. Bounded by the
    // query TTL ceiling (1h, QueryValidator) and the 300s relationship TTL;
    // revisit with per-tag raw-SQL counters if staleness ever matters.
    public function bump( string $tag ): void {
        $key = $this->normalize( $tag ); $epochs = get_option( self::TAG_EPOCHS, array() ); $epochs = is_array( $epochs ) ? $epochs : array();
        $epochs[ $key ] = max( 1, (int) ( $epochs[ $key ] ?? 1 ) + 1 ); update_option( self::TAG_EPOCHS, $epochs, false );
    }
    public function bump_epoch( string $namespace ): void {
        $key = $this->normalize( $namespace ); $epochs = get_option( self::NS_EPOCHS, array() ); $epochs = is_array( $epochs ) ? $epochs : array();
        $epochs[ $key ] = max( 1, (int) ( $epochs[ $key ] ?? 1 ) + 1 ); update_option( self::NS_EPOCHS, $epochs, false );
    }
    public function flush(): bool {
        $epoch = (int) get_option( self::GLOBAL_EPOCH, 1 ); update_option( self::GLOBAL_EPOCH, max( 1, $epoch + 1 ), false );
        if ( function_exists( 'wp_cache_flush_group' ) ) { wp_cache_flush_group( self::GROUP ); }
        return true;
    }
    public function epochs( array $tags ): array {
        $stored = get_option( self::TAG_EPOCHS, array() ); $stored = is_array( $stored ) ? $stored : array(); $out = array();
        foreach ( array_values( array_unique( array_filter( $tags ) ) ) as $tag ) { $key = $this->normalize( (string) $tag ); $out[ $key ] = max( 1, (int) ( $stored[ $key ] ?? 1 ) ); }
        return $out;
    }
    private function namespace_epoch( string $namespace ): int { $stored = get_option( self::NS_EPOCHS, array() ); $stored = is_array( $stored ) ? $stored : array(); return max( 1, (int) ( $stored[ $this->normalize( $namespace ) ] ?? 1 ) ); }
    private function key( string $key, string $namespace, array $dependencies ): string {
        $global = max( 1, (int) get_option( self::GLOBAL_EPOCH, 1 ) ); $ns = $this->namespace_epoch( $namespace );
        $deps = $dependencies ? ':' . md5( wp_json_encode( $this->epochs( $dependencies ) ) ) : '';
        return $global . ':' . $this->normalize( $namespace ) . ':' . $ns . $deps . ':' . $key;
    }
    private function normalize( string $value ): string { return sanitize_key( str_replace( array( ':', '.', '/' ), '_', $value ) ); }
}
