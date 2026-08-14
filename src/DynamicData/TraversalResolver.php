<?php
namespace CGM\Core\DynamicData;

use CGM\Core\Plugin;
use CGM\Core\Objects\ObjectReference;

final class TraversalResolver {
    public function __construct( private Plugin $core ) {}

    public function resolve( string $path, mixed $object = null, array $context = array() ): mixed {
        $segments = array_values( array_filter( explode( '.', trim( $path ) ), static fn( $v ) => '' !== $v ) );
        if ( ! $segments ) { return null; }
        $ref = $this->core->objects()->reference( $object ?: ( $context['current_query_item'] ?? $context['post_id'] ?? get_the_ID() ) );
        if ( ! $ref ) { return null; }
        return $this->walk( $ref, $segments, $context );
    }

    private function walk( ObjectReference $object, array $segments, array $context ): mixed {
        if ( ! $segments ) { return $this->core->objects()->serialize( $object ); }
        $first = array_shift( $segments );
        $relationship = $this->relationship_from_segment( $first, $object->content_type );
        if ( $relationship ) {
            $rows = $this->core->relationships()->get( (string) $relationship['id'], $object->id, array( 'public_only' => ! empty( $context['public_request'] ), 'source_type'=>$object->content_type ) );
            if ( ! $rows ) { return null; }
            $selector = $segments[0] ?? '';
            if ( 'primary' === $selector ) { array_shift( $segments ); $rows = array_values( array_filter( $rows, static fn( $r ) => ! empty( $r['primary'] ) || ! empty( $r['is_primary'] ) ) ); $rows = $rows ? array( $rows[0] ) : array(); }
            elseif ( 'first' === $selector ) { array_shift( $segments ); $rows = array( $rows[0] ); }
            elseif ( 'all' === $selector ) { array_shift( $segments ); }
            $targets = array(); foreach ( $rows as $row ) { $id = absint( $row['target_id'] ?? 0 ); if ( $id ) { $targets[] = new ObjectReference( (string) $relationship['target_type'], $id ); } }
            if ( ! $segments ) { return array_map( fn( $target ) => $this->core->objects()->serialize( $target ), $targets ); }
            $values = array_map( fn( $target ) => $this->walk( $target, $segments, $context ), $targets ); $values = array_values( array_filter( $values, static fn( $v ) => null !== $v && '' !== $v ) );
            return count( $values ) <= 1 ? ( $values[0] ?? null ) : $values;
        }
        if ( 'id' === $first ) { return $object->id; }
        if ( str_starts_with( $first, 'field:' ) ) { return $this->field_value( $object, substr( $first, 6 ) ); }
        $field = $this->core->fields()->get( $first ); if ( $field && $this->field_applies( $field, $object->content_type ) ) { return $this->field_value( $object, $first ); }
        return $this->core->objects()->property( $object, $first );
    }

    private function relationship_from_segment( string $segment, string $source_type ): ?array {
        $candidates = array( $segment );
        if ( str_ends_with( $segment, 's' ) ) { $candidates[] = substr( $segment, 0, -1 ); } else { $candidates[] = $segment . 's'; }
        foreach ( array_unique( $candidates ) as $id ) { $type = $this->core->relationships()->relationship_for_path( $id, $source_type ); if ( $type ) { return $type; } }
        return null;
    }

    private function field_value( ObjectReference $object, string $field_id ): mixed {
        $field = $this->core->fields()->get( $field_id ); if ( ! $field ) { return null; }
        $source = (string) ( $field['source'] ?? '' ); $provider = (string) ( $field['provider'] ?? '' );
        $ct = $this->core->content_types()->get( $object->content_type ); $kind = (string) ( $ct['kind'] ?? 'post' );
        if ( 'acf' === $provider && function_exists( 'get_field' ) ) { return get_field( $source, $object->id ); }
        if ( 'metabox' === $provider && function_exists( 'rwmb_meta' ) ) { return rwmb_meta( $source, array( 'object_type' => $kind ), $object->id ); }
        if ( in_array( $provider, array( 'wordpress-meta','acf','metabox' ), true ) ) {
            return match ( $kind ) { 'user' => get_user_meta( $object->id, $source, true ), 'term' => get_term_meta( $object->id, $source, true ), default => get_post_meta( $object->id, $source, true ) };
        }
        return $this->core->objects()->property( $object, $source ?: $field_id );
    }
    private function field_applies( array $field, string $content_type ): bool { $types = (array) ( $field['content_types'] ?? array( '*' ) ); return in_array( '*', $types, true ) || in_array( $content_type, $types, true ); }
}
