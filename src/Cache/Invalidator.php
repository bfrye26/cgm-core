<?php
namespace CGM\Core\Cache;

/**
 * Invalidates Core cache dependencies when WordPress objects change.
 *
 * Public methods in this class are WordPress hook boundaries. WordPress core
 * does not guarantee identical scalar types for every action in a hook family
 * (notably deleted_post_meta passes an array of meta IDs). Keep hook boundary
 * arguments deliberately untyped and normalize only the values Core uses.
 */
final class Invalidator {
    public function __construct( private Cache $cache ) {}

    public function register(): void {
        add_action( 'save_post', array( $this, 'post' ), 100, 2 );
        add_action( 'deleted_post', array( $this, 'deleted_post' ), 100, 2 );
        add_action( 'transition_post_status', array( $this, 'status_changed' ), 100, 3 );
        add_action( 'updated_post_meta', array( $this, 'post_meta' ), 100, 4 );
        add_action( 'added_post_meta', array( $this, 'post_meta' ), 100, 4 );
        add_action( 'deleted_post_meta', array( $this, 'deleted_post_meta' ), 100, 4 );
        add_action( 'updated_user_meta', array( $this, 'user_meta' ), 100, 4 );
        add_action( 'added_user_meta', array( $this, 'user_meta' ), 100, 4 );
        add_action( 'deleted_user_meta', array( $this, 'user_meta' ), 100, 4 );
        add_action( 'updated_term_meta', array( $this, 'term_meta' ), 100, 4 );
        add_action( 'added_term_meta', array( $this, 'term_meta' ), 100, 4 );
        add_action( 'deleted_term_meta', array( $this, 'term_meta' ), 100, 4 );
        add_action( 'set_object_terms', array( $this, 'terms' ), 100, 6 );
        foreach ( array( 'created_term', 'edited_term', 'delete_term' ) as $hook ) {
            add_action( $hook, array( $this, 'term_changed' ), 100, 3 );
        }
        add_action( 'profile_update', array( $this, 'user' ), 100, 2 );
        add_action( 'user_register', array( $this, 'user_created' ), 100 );
        add_action( 'deleted_user', array( $this, 'user_created' ), 100 );
        add_action( 'cgm_core/relationship_changed', array( $this, 'relationship' ), 100, 2 );
        add_action( 'cgm_core/cache_dependency_changed', array( $this, 'dependency' ), 100, 2 );
    }

    public function post( $id, $post ): void {
        $id = absint( $id );
        if ( ! $id || ! $post instanceof \WP_Post || wp_is_post_revision( $id ) ) { return; }
        $this->cache->bump( 'post:' . $id );
        $this->cache->bump( 'content:' . sanitize_key( (string) $post->post_type ) );
        $this->cache->bump( 'post.search' );
    }

    public function deleted_post( $id, $post ): void {
        $this->post( $id, $post );
    }

    /** Trash/untrash/publish/draft changes don't fire save_post; invalidate via status transitions. */
    public function status_changed( $new_status, $old_status, $post ): void {
        if ( $post instanceof \WP_Post ) { $this->post( $post->ID, $post ); }
    }

    /**
     * added_post_meta / updated_post_meta boundary.
     *
     * @param mixed $meta_id Metadata ID supplied by WordPress. Not used.
     * @param mixed $post_id Post ID.
     * @param mixed $key     Metadata key.
     * @param mixed $value   Metadata value. Not used.
     */
    public function post_meta( $meta_id, $post_id, $key, $value = null ): void {
        $this->invalidate_post_meta( $post_id, $key );
    }

    /**
     * deleted_post_meta boundary. WordPress passes an array of deleted meta IDs
     * as argument one, even when only one row is removed.
     *
     * @param mixed $meta_ids Array of deleted metadata IDs (or scalar in custom callers).
     * @param mixed $post_id  Post ID.
     * @param mixed $key      Metadata key.
     * @param mixed $value    Deleted metadata value. Not used.
     */
    public function deleted_post_meta( $meta_ids, $post_id, $key, $value = null ): void {
        $this->invalidate_post_meta( $post_id, $key );
    }

    private function invalidate_post_meta( $post_id, $key ): void {
        $post_id = absint( $post_id );
        $key     = is_scalar( $key ) ? (string) $key : '';
        if ( ! $post_id ) { return; }

        $this->cache->bump( 'post:' . $post_id );
        if ( '' === $key ) { return; }
        // Field ids are provider-prefixed: meta.<kind>.<key> (WordPress),
        // acf.<name>, metabox.<name>. Bump every shape the query engine can
        // record so meta edits always invalidate dependent query caches.
        foreach ( array( 'meta.', 'meta.post.', 'acf.', 'metabox.' ) as $prefix ) {
            $this->cache->bump( 'field:' . $prefix . $key );
        }
    }

    /** @param mixed $meta_id Unused; hook boundary signature only. */
    public function user_meta( $meta_id, $user_id, $key ): void {
        $user_id = absint( $user_id );
        $key     = is_scalar( $key ) ? (string) $key : '';
        if ( ! $user_id ) { return; }
        $this->cache->bump( 'user:' . $user_id );
        $this->cache->bump( 'content:user' );
        if ( '' !== $key ) { $this->cache->bump( 'field:meta.user.' . $key ); }
    }

    /** @param mixed $meta_id Unused; hook boundary signature only. */
    public function term_meta( $meta_id, $term_id, $key ): void {
        $term_id = absint( $term_id );
        $key     = is_scalar( $key ) ? (string) $key : '';
        if ( ! $term_id ) { return; }
        $this->cache->bump( 'term:' . $term_id );
        if ( '' !== $key ) { $this->cache->bump( 'field:meta.term.' . $key ); }
    }

    public function terms( $object_id, $terms, $tt_ids, $tax ): void {
        $object_id = absint( $object_id );
        $tax       = is_scalar( $tax ) ? sanitize_key( (string) $tax ) : '';
        if ( $object_id ) { $this->cache->bump( 'post:' . $object_id ); }
        if ( $tax ) { $this->cache->bump( 'taxonomy:' . $tax ); }
    }

    public function term_changed( $term_id, $tt_id = 0, $tax = '' ): void {
        $term_id = absint( $term_id );
        $tax     = is_scalar( $tax ) ? sanitize_key( (string) $tax ) : '';
        if ( $term_id ) { $this->cache->bump( 'term:' . $term_id ); }
        if ( $tax ) {
            $this->cache->bump( 'taxonomy:' . $tax );
            $this->cache->bump( 'content:term_' . $tax );
        }
    }

    public function user( $id ): void {
        $id = absint( $id );
        if ( ! $id ) { return; }
        $this->cache->bump( 'user:' . $id );
        $this->cache->bump( 'content:user' );
    }

    public function user_created( $id ): void {
        $this->user( $id );
    }

    /** @param mixed $rel_id Relationship id (string) or legacy payload array. */
    public function relationship( $rel_id, $source_id = 0 ): void {
        if ( is_array( $rel_id ) ) {
            $source_id = $rel_id['source_id'] ?? 0;
            $rel_id    = $rel_id['relationship'] ?? '';
        }
        $rel = sanitize_key( (string) $rel_id );
        if ( $rel ) { $this->cache->bump( 'relationship:' . $rel ); }
        if ( absint( $source_id ) ) { $this->cache->bump( 'object:post:' . absint( $source_id ) ); }
    }

    public function dependency( $dependency ): void {
        if ( ! is_scalar( $dependency ) ) { return; }
        $dependency = (string) $dependency;
        if ( '' !== $dependency ) { $this->cache->bump( $dependency ); }
    }
}
