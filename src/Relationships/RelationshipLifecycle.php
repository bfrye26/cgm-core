<?php
namespace CGM\Core\Relationships;

use CGM\Core\Events\EventBus;
use CGM\Core\Objects\ObjectReference;

/**
 * Applies relationship integrity rules when WordPress objects are deleted.
 *
 * Core-owned relationships are detached automatically. Provider-owned stores
 * are updated through their public store contract. A provider can opt into
 * cascade behaviour by listening to the versioned Core event/action.
 */
final class RelationshipLifecycle {
    public function __construct( private RelationshipManager $relationships, private EventBus $events ) {}

    public function register(): void {
        add_filter( 'pre_delete_post', array( $this, 'pre_delete_post' ), 10, 3 );
        add_action( 'before_delete_post', array( $this, 'before_delete_post' ), 10, 2 );
        add_filter( 'map_meta_cap', array( $this, 'map_meta_cap' ), 20, 4 );
        add_action( 'delete_user', array( $this, 'delete_user' ), 10, 3 );
        add_action( 'wpmu_delete_user', array( $this, 'wpmu_delete_user' ), 10, 1 );
        add_action( 'pre_delete_term', array( $this, 'pre_delete_term' ), 10, 2 );
    }

    public function pre_delete_post( mixed $delete, \WP_Post $post, bool $force_delete ): mixed {
        if ( null !== $delete ) { return $delete; }
        $ref = $this->post_reference( $post );
        $blockers = $this->relationships->deletion_blockers( $ref );
        if ( ! $blockers ) { return null; }
        do_action( 'cgm_core/relationship_delete_restricted', $ref, $blockers );
        return false;
    }

    /** Block user deletion through WordPress's object-level delete_user meta capability. */
    public function map_meta_cap( array $caps, string $cap, int $user_id, array $args ): array {
        if ( 'delete_user' !== $cap || empty( $args[0] ) ) { return $caps; }
        $ref = new ObjectReference( 'user', absint( $args[0] ) );
        return $this->relationships->deletion_blockers( $ref ) ? array( 'do_not_allow' ) : $caps;
    }

    public function before_delete_post( int $post_id, \WP_Post $post ): void {
        $this->purge( $this->post_reference( $post ) );
    }

    public function delete_user( int $user_id, ?int $reassign = null, ?\WP_User $user = null ): void {
        $this->purge( new ObjectReference( 'user', $user_id ) );
    }

    public function wpmu_delete_user( int $user_id ): void {
        $this->purge( new ObjectReference( 'user', $user_id ) );
    }

    public function pre_delete_term( int $term_id, string $taxonomy ): void {
        $ref = new ObjectReference( 'term_' . sanitize_key( $taxonomy ), $term_id );
        $blockers = $this->relationships->deletion_blockers( $ref );
        if ( $blockers ) {
            /**
             * WordPress exposes pre_delete_term as an action, not a short-circuit
             * filter. Surface the restriction to integrations and allow a site to
             * decide whether to abort its own term-delete workflow.
             */
            do_action( 'cgm_core/relationship_delete_restricted', $ref, $blockers );
        }
        $this->purge( $ref );
    }

    private function purge( ObjectReference $ref ): void {
        $result = $this->relationships->purge_object( $ref );
        $payload = array( 'object'=>$ref->jsonSerialize(), 'result'=>$result );
        $this->events->dispatch( 'relationship.object_deleted', $payload );
        do_action( 'cgm_core/relationship_object_deleted', $ref, $result );
    }

    private function post_reference( \WP_Post $post ): ObjectReference {
        return new ObjectReference( 'attachment' === $post->post_type ? 'media' : $post->post_type, (int) $post->ID );
    }
}
