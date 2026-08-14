<?php
namespace CGM\Core\Bulk;

use CGM\Core\Plugin;

/**
 * Views Bulk Operations equivalent: resolve a saved query (or inline definition)
 * to a set of objects, then run a list of rule actions against each object.
 */
final class BulkManager {
    private const MAX_ITEMS = 1000;

    public function __construct( private Plugin $core ) {}

    /** Resolve the query to {id, type} pairs (bounded). */
    public function resolve( string|array $query ): array {
        $result = $this->core->queries()->run( $query, array( 'consumer' => 'bulk' ) );
        $type = (string) ( $result->debug['normalized']['content_type'] ?? '' );
        $out = array();
        foreach ( $result->items as $item ) {
            $ref = $this->core->objects()->reference( $item, $type ?: null );
            if ( $ref ) { $out[] = array( 'id' => $ref->id, 'type' => $ref->content_type ); }
            if ( count( $out ) >= self::MAX_ITEMS ) { break; }
        }
        return $out;
    }

    public function preview( string|array $query ): array {
        $objects = $this->resolve( $query );
        $sample = array();
        foreach ( array_slice( $objects, 0, 5 ) as $o ) {
            $sample[] = array( 'id' => $o['id'], 'type' => $o['type'], 'label' => $this->core->objects()->label( $this->core->objects()->reference( $o['id'], $o['type'] ) ) );
        }
        return array( 'count' => count( $objects ), 'sample' => $sample );
    }

    public function run( string|array $query, array $actions ): array {
        $objects = $this->resolve( $query );
        $succeeded = 0; $failed = 0;
        foreach ( $objects as $o ) {
            try {
                $this->core->rules()->execute( $actions, array( 'object_id' => $o['id'], 'object_type' => $o['type'] ), 'bulk.operation' );
                $succeeded++;
            } catch ( \Throwable $e ) {
                $failed++;
            }
        }
        return array( 'processed' => count( $objects ), 'succeeded' => $succeeded, 'failed' => $failed );
    }
}
