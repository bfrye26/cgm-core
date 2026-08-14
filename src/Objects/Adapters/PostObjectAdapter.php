<?php
namespace CGM\Core\Objects\Adapters;

use CGM\Core\Contracts\ObjectAdapterInterface;
use CGM\Core\Objects\ObjectReference;

class PostObjectAdapter implements ObjectAdapterInterface {
    public function kind(): string { return 'post'; }
    protected function post_type( ObjectReference $object ): string { return $object->content_type; }
    public function exists( ObjectReference $object ): bool { $post = get_post( $object->id ); return $post instanceof \WP_Post && $post->post_type === $this->post_type( $object ); }
    public function label( ObjectReference $object ): string { return (string) get_the_title( $object->id ); }
    public function url( ObjectReference $object ): string { return (string) get_permalink( $object->id ); }
    public function edit_url( ObjectReference $object ): string { return (string) ( get_edit_post_link( $object->id, '' ) ?: '' ); }
    public function is_public( ObjectReference $object ): bool { $post = get_post( $object->id ); return $post instanceof \WP_Post && is_post_publicly_viewable( $post ); }
    public function property( ObjectReference $object, string $property ): mixed {
        $post = get_post( $object->id );
        if ( ! $post ) { return null; }
        return match ( sanitize_key( $property ) ) {
            'id' => (int) $post->ID,
            'title', 'name' => get_the_title( $post ),
            'slug' => (string) $post->post_name,
            'url', 'permalink' => get_permalink( $post ),
            'excerpt' => get_the_excerpt( $post ),
            'content' => (string) $post->post_content,
            'date' => (string) $post->post_date,
            'modified' => (string) $post->post_modified,
            'author_id' => (int) $post->post_author,
            'image', 'featured_image', 'featured_image_id' => (int) get_post_thumbnail_id( $post ),
            default => get_post_meta( $post->ID, $property, true ),
        };
    }
    public function search( string $subtype, string $search, array $args = array() ): array {
        $post_type = post_type_exists( $subtype ) ? $subtype : 'post';
        $object = get_post_type_object( $post_type );
        $statuses = array( 'publish' );
        if ( $object && isset( $object->cap->read_private_posts ) && current_user_can( $object->cap->read_private_posts ) ) {
            $statuses = array( 'publish', 'private', 'draft', 'pending', 'future' );
        }
        $posts = get_posts( array(
            'post_type'        => $post_type,
            'post_status'      => $statuses,
            'posts_per_page'   => min( 100, max( 1, absint( $args['limit'] ?? 30 ) ) ),
            's'                => sanitize_text_field( $search ),
            'orderby'          => $search ? 'relevance' : 'title',
            'order'            => 'ASC',
            'suppress_filters' => false,
        ) );
        return array_map( static fn( $post ) => array( 'id' => (int) $post->ID, 'label' => get_the_title( $post ), 'description' => '#' . $post->ID ), $posts );
    }
}
