<?php
namespace CGM\Core\Support;

use CGM\Core\Contracts\VisibilityPolicyInterface;

/**
 * Lightweight fallback visibility policy used only when an object adapter is
 * unavailable. ObjectResolver is the preferred visibility authority.
 */
final class VisibilityPolicy implements VisibilityPolicyInterface {
    public function is_public( string $object_type, int $object_id ): bool { return $this->can_read( $object_type, $object_id, true ); }
    public function can_read( string $object_type, int $object_id, bool $public_only = false ): bool {
        $object_type = sanitize_key( $object_type );
        if ( $object_id < 1 ) { return false; }

        if ( 'user' === $object_type ) {
            $user = get_userdata( $object_id );
            if ( ! $user ) { return false; }
            if ( ! $public_only ) { return current_user_can( 'list_users' ) || get_current_user_id() === $object_id; }
            $post_types = get_post_types( array( 'public'=>true ), 'names' );
            return (bool) get_posts( array( 'author'=>$object_id, 'post_type'=>$post_types, 'post_status'=>'publish', 'posts_per_page'=>1, 'fields'=>'ids', 'no_found_rows'=>true ) );
        }

        if ( str_starts_with( $object_type, 'term_' ) ) {
            $taxonomy = substr( $object_type, 5 );
            $term = get_term( $object_id, $taxonomy );
            if ( ! $term instanceof \WP_Term || is_wp_error( $term ) ) { return false; }
            if ( ! $public_only ) { return true; }
            $tax = get_taxonomy( $taxonomy );
            return (bool) ( $tax && $tax->public );
        }

        $post = get_post( $object_id );
        if ( ! $post ) { return false; }
        if ( $public_only ) { return is_post_publicly_viewable( $post ); }
        return current_user_can( 'read_post', $post->ID ) || is_post_publicly_viewable( $post );
    }
}
