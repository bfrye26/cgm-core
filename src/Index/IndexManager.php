<?php
namespace CGM\Core\Index;

use CGM\Core\Plugin;

/**
 * Fans content/relationship changes out to `index.rebuild` events per registered
 * index, and provides the rebuild-all trigger consumed by the REST controller.
 */
final class IndexManager {
    public function __construct( private Plugin $core ) {}

    public function register(): void {
        $this->core->events()->register( 'index.rebuild', '1.0', array( 'index', 'object_type', 'object_id' ) );
        $this->core->events()->listen( 'content.changed', array( $this, 'content_changed' ) );
        $this->core->events()->listen( 'relationship.changed', array( $this, 'relationship_changed' ) );
    }

    public function content_changed( array $payload ): void {
        $this->fan_out( sanitize_key( (string) ( $payload['object_type'] ?? '' ) ), absint( $payload['object_id'] ?? 0 ) );
    }

    public function relationship_changed( array $payload ): void {
        $this->fan_out( sanitize_key( (string) ( $payload['source_type'] ?? '' ) ), absint( $payload['source_id'] ?? 0 ) );
    }

    /** Dispatch a rebuild event for every index that covers the given object type. */
    private function fan_out( string $type, int $id ): void {
        if ( ! $type || ! $id ) { return; }
        foreach ( $this->core->indexes()->all() as $index ) {
            $types = (array) ( $index['content_types'] ?? array( '*' ) );
            if ( ! in_array( '*', $types, true ) && ! in_array( $type, $types, true ) ) { continue; }
            $this->core->events()->dispatch( 'index.rebuild', array( 'index' => $index['id'], 'object_type' => $type, 'object_id' => $id ) );
        }
    }

    /** Full rebuild trigger. Returns the number of indexes queued. */
    public function rebuild( string $index = '' ): int {
        $count = 0;
        foreach ( $this->core->indexes()->all() as $idx ) {
            if ( '' !== $index && $index !== (string) $idx['id'] ) { continue; }
            $this->core->events()->dispatch( 'index.rebuild', array( 'index' => $idx['id'], 'object_type' => '*', 'object_id' => 0, 'full' => true ) );
            $count++;
        }
        return $count;
    }
}
