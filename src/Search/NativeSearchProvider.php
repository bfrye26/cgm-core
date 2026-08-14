<?php
namespace CGM\Core\Search;

use CGM\Core\Plugin;

/** Native WordPress search backend: WP_Query over a content type, facets over taxonomies. */
final class NativeSearchProvider implements SearchProviderInterface {
    public function __construct( private Plugin $core ) {}

    public function id(): string { return 'native'; }

    public function search( string $query, array $args = array() ): array {
        $content_type = sanitize_key( (string) ( $args['content_type'] ?? 'post' ) );
        $ct = $this->core->content_types()->get( $content_type );
        $post_type = $ct ? (string) ( $ct['subtype'] ?? $content_type ) : 'post';

        $wp = array(
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => max( 1, min( 100, absint( $args['per_page'] ?? 20 ) ) ),
            'paged'          => max( 1, absint( $args['page'] ?? 1 ) ),
        );
        if ( '' !== $query ) { $wp['s'] = $query; }

        $filters = is_array( $args['filters'] ?? null ) ? $args['filters'] : array();
        foreach ( $filters as $field => $value ) {
            if ( str_starts_with( (string) $field, 'taxonomy.' ) && $value ) {
                $wp['tax_query'][] = array( 'taxonomy' => sanitize_key( substr( (string) $field, 9 ) ), 'field' => 'slug', 'terms' => array_values( array_filter( (array) $value, 'sanitize_title' ) ) );
            }
        }

        $q = new \WP_Query( $wp );
        $items = array();
        foreach ( $q->posts as $p ) {
            $ref = $this->core->objects()->reference( $p );
            $row = $ref ? $this->core->objects()->serialize( $ref ) : array();
            $row['url'] = get_permalink( $p );
            $items[] = $row;
        }
        return array( 'items' => $items, 'total' => (int) $q->found_posts, 'page' => (int) $q->get( 'paged' ), 'per_page' => (int) $q->get( 'posts_per_page' ) );
    }

    public function facets( string $query, array $args = array() ): array {
        $content_type = sanitize_key( (string) ( $args['content_type'] ?? 'post' ) );
        $out = array();
        foreach ( $this->core->facets()->all() as $facet ) {
            $types = (array) ( $facet['content_types'] ?? array( '*' ) );
            if ( ! in_array( '*', $types, true ) && ! in_array( $content_type, $types, true ) ) { continue; }
            if ( empty( $facet['taxonomy'] ) ) { continue; }
            $terms = get_terms( array( 'taxonomy' => (string) $facet['taxonomy'], 'hide_empty' => true ) );
            if ( is_wp_error( $terms ) ) { continue; }
            $out[] = array(
                'id' => (string) ( $facet['id'] ?? $facet['taxonomy'] ),
                'label' => (string) ( $facet['label'] ?? $facet['taxonomy'] ),
                'taxonomy' => (string) $facet['taxonomy'],
                'options' => array_map( static fn( $t ) => array( 'value' => $t->slug, 'label' => $t->name, 'count' => (int) $t->count ), $terms ),
            );
        }
        return $out;
    }
}
