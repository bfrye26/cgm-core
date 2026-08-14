<?php
namespace CGM\Core\Support;

use CGM\Core\Plugin;

/**
 * Capability-mapping layer: maps Core operations (read/edit content, read/edit
 * field, read view) to WordPress capabilities, with a filter override. Content
 * types fall back to their native CPT capabilities; fields carry optional
 * read_capability/edit_capability overrides.
 */
final class Permissions {
    public function __construct( private Plugin $core ) {}

    public function content_read( string $type ): string {
        $ct = $this->core->content_types()->get( $type );
        $kind = $ct ? (string) ( $ct['kind'] ?? '' ) : '';
        if ( 'user' === $kind ) { return 'list_users'; }
        if ( 'term' === $kind ) { return 'manage_categories'; }
        if ( $ct ) {
            $obj = get_post_type_object( (string) ( $ct['subtype'] ?? $type ) );
            if ( $obj && ! empty( $obj->cap->read_private_posts ) ) { return (string) $obj->cap->read_private_posts; }
        }
        return 'read';
    }

    public function content_edit( string $type ): string {
        $ct = $this->core->content_types()->get( $type );
        $kind = $ct ? (string) ( $ct['kind'] ?? '' ) : '';
        if ( 'user' === $kind ) { return 'edit_users'; }
        if ( 'term' === $kind ) { return 'manage_categories'; }
        if ( $ct ) {
            $obj = get_post_type_object( (string) ( $ct['subtype'] ?? $type ) );
            if ( $obj && ! empty( $obj->cap->edit_posts ) ) { return (string) $obj->cap->edit_posts; }
        }
        return 'edit_posts';
    }

    public function field_read( string $field_id ): string {
        $f = $this->core->fields()->get( $field_id );
        return $f ? (string) ( $f['read_capability'] ?? 'read' ) : 'read';
    }

    public function field_edit( string $field_id ): string {
        $f = $this->core->fields()->get( $field_id );
        return $f ? (string) ( $f['edit_capability'] ?? 'edit_posts' ) : 'edit_posts';
    }

    public function can( string $operation, string $resource, int $object_id = 0 ): bool {
        $cap = match ( $operation ) {
            'read_content' => $this->content_read( $resource ),
            'edit_content' => $this->content_edit( $resource ),
            'read_field'   => $this->field_read( $resource ),
            'edit_field'   => $this->field_edit( $resource ),
            'read_view'    => 'read',
            default        => 'manage_cgm_core',
        };
        $cap = apply_filters( 'cgm_core/capability_map', $cap, $operation, $resource, $object_id );
        return $object_id ? current_user_can( $cap, $object_id ) : current_user_can( $cap );
    }
}
