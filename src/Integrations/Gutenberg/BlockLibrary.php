<?php
namespace CGM\Core\Integrations\Gutenberg;

use CGM\Core\Plugin;

/** Server-rendered block library: related content + smart collection. */
final class BlockLibrary {
    public function __construct( private Plugin $core ) {}

    public function register(): void {
        add_action( 'init', array( $this, 'blocks' ), 45 );
    }

    public function blocks(): void {
        register_block_type( CGM_CORE_PATH . 'blocks/related-content', array( 'render_callback' => array( $this, 'related' ) ) );
        register_block_type( CGM_CORE_PATH . 'blocks/smart-collection', array( 'render_callback' => array( $this, 'collection' ) ) );
    }

    public function related( array $a, string $content, \WP_Block $block ): string {
        $rel     = sanitize_key( (string) ( $a['relationship'] ?? 'game' ) );
        $limit   = max( 0, absint( $a['limit'] ?? 5 ) );
        $heading = sanitize_text_field( (string) ( $a['heading'] ?? '' ) );
        $post_id = absint( $block->context['postId'] ?? get_the_ID() );
        if ( ! $post_id || ! $rel ) { return ''; }
        $rows = $this->core->relationships()->get( $rel, $post_id );
        $html = '';
        if ( $heading ) { $html .= '<h2 class="wp-block-heading">' . esc_html( $heading ) . '</h2>'; }
        $html .= '<ul class="cgm-related">';
        $count = 0;
        foreach ( $rows as $r ) {
            if ( $limit && $count++ >= $limit ) { break; }
            $tid = absint( $r['target_id'] ?? 0 );
            if ( ! $tid ) { continue; }
            $url = get_permalink( $tid );
            $html .= '<li>' . ( $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $tid ) ) . '</a>' : esc_html( get_the_title( $tid ) ) ) . '</li>';
        }
        return $html . '</ul>';
    }

    public function collection( array $a, string $content, \WP_Block $block ): string {
        $query_id = sanitize_text_field( (string) ( $a['queryId'] ?? '' ) );
        if ( ! $query_id ) { return is_admin() ? '' : ''; }
        $atts = ' id="' . esc_attr( $query_id ) . '"';
        if ( ! empty( $a['filters'] ) ) { $atts .= ' filters="1"'; }
        if ( ! empty( $a['limit'] ) ) { $atts .= ' limit="' . absint( $a['limit'] ) . '"'; }
        return do_shortcode( '[cgm_view' . $atts . ']' );
    }
}
