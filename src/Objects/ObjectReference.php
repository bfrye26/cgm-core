<?php
namespace CGM\Core\Objects;

final class ObjectReference implements \JsonSerializable {
    public function __construct(
        public readonly string $content_type,
        public readonly int $id
    ) {}

    public static function from( mixed $value, ?string $content_type = null ): ?self {
        if ( $value instanceof self ) { return $value; }
        if ( $value instanceof \WP_Post ) { return new self( $content_type ?: $value->post_type, (int) $value->ID ); }
        if ( $value instanceof \WP_User ) { return new self( $content_type ?: 'user', (int) $value->ID ); }
        if ( $value instanceof \WP_Term ) { return new self( $content_type ?: 'term_' . $value->taxonomy, (int) $value->term_id ); }
        if ( is_array( $value ) ) {
            $type = sanitize_key( (string) ( $value['content_type'] ?? $value['type'] ?? $content_type ?? '' ) );
            $id   = absint( $value['id'] ?? $value['object_id'] ?? 0 );
            return $type && $id ? new self( $type, $id ) : null;
        }
        if ( is_numeric( $value ) && $content_type ) {
            $id = absint( $value );
            return $id ? new self( sanitize_key( $content_type ), $id ) : null;
        }
        if ( is_string( $value ) && str_contains( $value, ':' ) ) {
            [ $type, $id ] = array_pad( explode( ':', $value, 2 ), 2, '' );
            $id = absint( $id );
            $type = sanitize_key( $type );
            return $type && $id ? new self( $type, $id ) : null;
        }
        return null;
    }

    public function key(): string { return $this->content_type . ':' . $this->id; }
    public function jsonSerialize(): array { return array( 'content_type' => $this->content_type, 'id' => $this->id ); }
}
