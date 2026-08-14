<?php
namespace CGM\Core\Objects\Adapters;

use CGM\Core\Contracts\ObjectAdapterInterface;
use CGM\Core\Objects\ObjectReference;

final class UserObjectAdapter implements ObjectAdapterInterface {
    public function kind(): string { return 'user'; }
    public function exists( ObjectReference $object ): bool { return (bool) get_userdata( $object->id ); }
    public function label( ObjectReference $object ): string { $u = get_userdata( $object->id ); return $u ? (string) $u->display_name : ''; }
    public function url( ObjectReference $object ): string { return (string) get_author_posts_url( $object->id ); }
    public function edit_url( ObjectReference $object ): string { return current_user_can( 'edit_user', $object->id ) ? (string) get_edit_user_link( $object->id ) : ''; }
    public function is_public( ObjectReference $object ): bool {
        if ( ! get_userdata( $object->id ) ) { return false; }
        $types = get_post_types( array( 'public'=>true ), 'names' );
        $ids = get_posts( array( 'author'=>$object->id, 'post_type'=>array_values($types), 'post_status'=>'publish', 'posts_per_page'=>1, 'fields'=>'ids', 'no_found_rows'=>true, 'suppress_filters'=>false ) );
        return ! empty( $ids );
    }
    public function property( ObjectReference $object, string $property ): mixed {
        $u = get_userdata( $object->id ); if ( ! $u ) { return null; }
        return match ( sanitize_key( $property ) ) {
            'id' => (int) $u->ID,
            'name', 'display_name' => (string) $u->display_name,
            'login' => (string) $u->user_login,
            'email' => current_user_can( 'list_users' ) ? (string) $u->user_email : '',
            'url', 'permalink' => get_author_posts_url( $u->ID ),
            'bio', 'description' => get_user_meta( $u->ID, 'description', true ),
            'avatar', 'avatar_url' => get_avatar_url( $u->ID ),
            'roles' => (array) $u->roles,
            default => get_user_meta( $u->ID, $property, true ),
        };
    }
    public function search( string $subtype, string $search, array $args = array() ): array {
        $users = get_users( array(
            'search'         => $search ? '*' . sanitize_text_field( $search ) . '*' : '',
            'search_columns' => array( 'display_name', 'user_login', 'user_email' ),
            'number'         => min( 100, max( 1, absint( $args['limit'] ?? 30 ) ) ),
            'orderby'        => 'display_name',
        ) );
        return array_map( static fn( $u ) => array( 'id' => (int) $u->ID, 'label' => $u->display_name, 'description' => current_user_can( 'list_users' ) ? $u->user_email : '' ), $users );
    }
}
