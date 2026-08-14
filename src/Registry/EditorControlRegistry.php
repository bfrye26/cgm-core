<?php
namespace CGM\Core\Registry;
final class EditorControlRegistry extends AbstractRegistry {
    public function for_post_type( string $post_type ): array {
        return array_values( array_filter( $this->items, static function( array $item ) use ( $post_type ): bool {
            $types = (array) ( $item['post_types'] ?? array( 'post' ) );
            return in_array( $post_type, $types, true );
        } ) );
    }
}
