<?php
namespace CGM\Core\Context;

use CGM\Core\Objects\ObjectReference;

/** Resolves reusable Drupal-style contextual values for queries and builders. */
final class ContextResolver {
    private array $custom = array();

    public function register( string $key, string $label, callable $resolver ): void {
        $key = sanitize_key( ltrim( $key, '@' ) );
        if ( ! $key ) { return; }
        $this->custom[ $key ] = array( 'label'=>$label, 'resolve'=>$resolver );
    }

    public function resolve( array $context = array() ): array {
        $query_object = $context['current_query_object'] ?? ( $GLOBALS['cgm_core_query_object'] ?? null );
        $query_ref = $this->reference_like( $query_object );

        $post_id = absint( $context['post_id'] ?? 0 );
        if ( ! $post_id ) { $post_id = absint( get_queried_object_id() ?: get_the_ID() ); }
        $post = $post_id ? get_post( $post_id ) : null;
        $queried = get_queried_object();

        $current_query_item = $context['current_query_item'] ?? ( $query_ref ? $query_ref->id : 0 );
        $parent_query = $context['parent_query_item'] ?? null;
        $parent_ref = $this->reference_like( $parent_query );

        $base = array(
            'post_id'              => $post_id,
            'current_post'         => $post_id,
            'current_parent'       => $post ? (int) $post->post_parent : 0,
            'current_post_type'    => $post ? (string) $post->post_type : '',
            'current_user'         => get_current_user_id(),
            'current_author'       => $post ? (int) $post->post_author : 0,
            'current_term'         => $queried instanceof \WP_Term ? (int) $queried->term_id : 0,
            'current_taxonomy'     => $queried instanceof \WP_Term ? (string) $queried->taxonomy : '',
            'current_archive'      => is_archive() ? 1 : 0,
            'current_query_item'   => $query_ref ? $query_ref->id : absint( $current_query_item ),
            'current_query_type'   => $query_ref ? $query_ref->content_type : sanitize_key( (string) ( $context['current_query_type'] ?? '' ) ),
            'current_query_object' => $query_ref ?: $query_object,
            'parent_query_item'    => $parent_ref ? $parent_ref->id : absint( $parent_query ),
            'parent_query_type'    => $parent_ref ? $parent_ref->content_type : sanitize_key( (string) ( $context['parent_query_type'] ?? '' ) ),
        );
        $resolved = array_merge( $base, $context );
        // Normalize query-object aliases after merge so explicit WP objects still become reusable references.
        if ( $query_ref ) {
            $resolved['current_query_item'] = $query_ref->id;
            $resolved['current_query_type'] = $query_ref->content_type;
            $resolved['current_query_object'] = $query_ref;
        }
        if ( $parent_ref ) {
            $resolved['parent_query_item'] = $parent_ref->id;
            $resolved['parent_query_type'] = $parent_ref->content_type;
        }

        foreach ( $this->custom as $key => $definition ) {
            if ( array_key_exists( $key, $context ) ) { continue; }
            try { $resolved[ $key ] = call_user_func( $definition['resolve'], $resolved ); }
            catch ( \Throwable $e ) { $resolved[ $key ] = 0; }
        }
        return apply_filters( 'cgm_core/context', $resolved );
    }

    public function tokens(): array {
        $tokens = array(
            '@current_post'       => __( 'Current post', 'cgm-core' ),
            '@current_parent'     => __( 'Current parent', 'cgm-core' ),
            '@current_user'       => __( 'Current user', 'cgm-core' ),
            '@current_author'     => __( 'Current author', 'cgm-core' ),
            '@current_term'       => __( 'Current term', 'cgm-core' ),
            '@current_query_item' => __( 'Current query item', 'cgm-core' ),
            '@parent_query_item'  => __( 'Parent query item', 'cgm-core' ),
        );
        foreach ( $this->custom as $key => $definition ) { $tokens[ '@' . $key ] = (string) $definition['label']; }
        return apply_filters( 'cgm_core/context_tokens', $tokens );
    }

    public function registered_keys(): array { return array_keys( $this->custom ); }

    /** Replace contextual tokens recursively. Object references resolve to their numeric ID for query comparisons. */
    public function replace_tokens( mixed $value, array $context ): mixed {
        if ( is_array( $value ) ) {
            foreach ( $value as $k => $v ) { $value[ $k ] = $this->replace_tokens( $v, $context ); }
            return $value;
        }
        if ( ! is_string( $value ) || ! str_starts_with( $value, '@' ) ) { return $value; }
        if ( str_starts_with( $value, '@query:' ) ) {
            return sanitize_text_field( (string) get_query_var( sanitize_key( substr( $value, 7 ) ), '' ) );
        }
        if ( str_starts_with( $value, '@url:' ) ) {
            $key = sanitize_key( substr( $value, 5 ) );
            return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
        }
        $resolved = $context[ substr( $value, 1 ) ] ?? $value;
        if ( $resolved instanceof ObjectReference ) { return $resolved->id; }
        if ( $resolved instanceof \WP_Post || $resolved instanceof \WP_User ) { return (int) $resolved->ID; }
        if ( $resolved instanceof \WP_Term ) { return (int) $resolved->term_id; }
        return $resolved;
    }

    private function reference_like( mixed $value ): ?ObjectReference {
        if ( $value instanceof ObjectReference ) { return $value; }
        if ( $value instanceof \WP_Post ) { return new ObjectReference( 'attachment' === $value->post_type ? 'media' : $value->post_type, (int) $value->ID ); }
        if ( $value instanceof \WP_User ) { return new ObjectReference( 'user', (int) $value->ID ); }
        if ( $value instanceof \WP_Term ) { return new ObjectReference( 'term_' . $value->taxonomy, (int) $value->term_id ); }
        if ( is_array( $value ) ) { return ObjectReference::from( $value ); }
        if ( is_string( $value ) && str_contains( $value, ':' ) ) { return ObjectReference::from( $value ); }
        return null;
    }
}
