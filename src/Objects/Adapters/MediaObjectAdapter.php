<?php
namespace CGM\Core\Objects\Adapters;

use CGM\Core\Objects\ObjectReference;

final class MediaObjectAdapter extends PostObjectAdapter {
    public function kind(): string { return 'media'; }
    protected function post_type( ObjectReference $object ): string { return 'attachment'; }
    public function url( ObjectReference $object ): string { return (string) wp_get_attachment_url( $object->id ); }
    public function is_public( ObjectReference $object ): bool { return (bool) wp_get_attachment_url( $object->id ); }
    public function property( ObjectReference $object, string $property ): mixed {
        return match ( sanitize_key( $property ) ) {
            'url' => wp_get_attachment_url( $object->id ),
            'alt' => get_post_meta( $object->id, '_wp_attachment_image_alt', true ),
            'mime', 'mime_type' => get_post_mime_type( $object->id ),
            'caption' => wp_get_attachment_caption( $object->id ),
            default => parent::property( $object, $property ),
        };
    }
    public function search( string $subtype, string $search, array $args = array() ): array { return parent::search( 'attachment', $search, $args ); }
}
