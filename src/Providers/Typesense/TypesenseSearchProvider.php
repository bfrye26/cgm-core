<?php
namespace CGM\Core\Providers\Typesense;

use CGM\Core\Plugin;
use CGM\Core\Search\SearchProviderInterface;
use CGM\Core\Search\NativeSearchProvider;

/**
 * Bridges Codemanas\Typesense\Services\SearchProvider into Core's unified search
 * facade. Falls back to the native WordPress provider whenever Typesense is not
 * configured or throws, so search never degrades to empty results.
 */
final class TypesenseSearchProvider implements SearchProviderInterface {
    private NativeSearchProvider $native;

    public function __construct( private Plugin $core ) {
        $this->native = new NativeSearchProvider( $core );
    }

    public function id(): string { return 'typesense'; }

    public function search( string $query, array $args = array() ): array {
        if ( ! $this->is_configured() ) { return $this->native->search( $query, $args ); }
        try {
            $provider = new \Codemanas\Typesense\Services\SearchProvider();
            $result = $provider->query( array(
                'query'      => $query,
                'post_types' => array( sanitize_key( (string) ( $args['content_type'] ?? 'post' ) ) ),
                'limit'      => max( 1, min( 100, absint( $args['per_page'] ?? 20 ) ) ),
                'page'       => max( 1, absint( $args['page'] ?? 1 ) ),
            ) );
            $items = array();
            foreach ( (array) ( $result['items'] ?? array() ) as $it ) {
                $items[] = array(
                    'id'        => (int) ( $it['id'] ?? 0 ),
                    'label'     => (string) ( $it['title'] ?? '' ),
                    'title'     => (string) ( $it['title'] ?? '' ),
                    'url'       => (string) ( $it['url'] ?? '' ),
                    'post_type' => (string) ( $it['post_type'] ?? '' ),
                    'excerpt'   => (string) ( $it['excerpt'] ?? '' ),
                    'thumbnail' => (string) ( $it['thumbnail'] ?? '' ),
                );
            }
            return array(
                'items' => $items, 'total' => (int) ( $result['found'] ?? 0 ),
                'page' => absint( $args['page'] ?? 1 ), 'per_page' => absint( $args['per_page'] ?? 20 ),
            );
        } catch ( \Throwable $e ) {
            return $this->native->search( $query, $args );
        }
    }

    public function facets( string $query, array $args = array() ): array {
        // Facet definitions are engine-agnostic (taxonomy-backed); reuse them.
        return $this->native->facets( $query, $args );
    }

    private function is_configured(): bool {
        if ( ! class_exists( '\Codemanas\Typesense\Backend\Admin' ) ) { return false; }
        $settings = \Codemanas\Typesense\Backend\Admin::get_default_settings();
        return ! empty( $settings['node'] ) && ! empty( $settings['search_api_key'] );
    }
}
