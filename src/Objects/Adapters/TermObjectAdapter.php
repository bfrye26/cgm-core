<?php
namespace CGM\Core\Objects\Adapters;

use CGM\Core\Contracts\ObjectAdapterInterface;
use CGM\Core\Objects\ObjectReference;

final class TermObjectAdapter implements ObjectAdapterInterface {
    public function kind(): string { return 'term'; }
    private function taxonomy( ObjectReference $object ): string {
        return str_starts_with( $object->content_type, 'term_' ) ? substr( $object->content_type, 5 ) : $object->content_type;
    }
    public function exists( ObjectReference $object ): bool { $t = get_term( $object->id, $this->taxonomy( $object ) ); return $t instanceof \WP_Term; }
    public function label( ObjectReference $object ): string { $t = get_term( $object->id, $this->taxonomy( $object ) ); return $t instanceof \WP_Term ? $t->name : ''; }
    public function url( ObjectReference $object ): string { $url = get_term_link( $object->id, $this->taxonomy( $object ) ); return is_wp_error( $url ) ? '' : (string) $url; }
    public function edit_url( ObjectReference $object ): string { $url = get_edit_term_link( $object->id, $this->taxonomy( $object ) ); return $url ? (string) $url : ''; }
    public function is_public( ObjectReference $object ): bool { $tax = get_taxonomy( $this->taxonomy( $object ) ); return $tax && $tax->public && $this->exists( $object ); }
    public function property( ObjectReference $object, string $property ): mixed {
        $t = get_term( $object->id, $this->taxonomy( $object ) ); if ( ! $t instanceof \WP_Term ) { return null; }
        return match ( sanitize_key( $property ) ) {
            'id' => (int) $t->term_id,
            'name', 'title' => (string) $t->name,
            'slug' => (string) $t->slug,
            'description' => (string) $t->description,
            'count' => (int) $t->count,
            'parent' => (int) $t->parent,
            'url', 'permalink' => $this->url( $object ),
            default => get_term_meta( $t->term_id, $property, true ),
        };
    }
    public function search( string $subtype, string $search, array $args = array() ): array {
        if ( ! taxonomy_exists( $subtype ) ) { return array(); }
        $terms = get_terms( array( 'taxonomy' => $subtype, 'hide_empty' => false, 'search' => sanitize_text_field( $search ), 'number' => min( 100, max( 1, absint( $args['limit'] ?? 30 ) ) ) ) );
        if ( is_wp_error( $terms ) ) { return array(); }
        return array_map( static fn( $t ) => array( 'id' => (int) $t->term_id, 'label' => $t->name, 'description' => '#' . $t->term_id ), $terms );
    }
}
