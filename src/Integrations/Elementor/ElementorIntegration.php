<?php
namespace CGM\Core\Integrations\Elementor;

use CGM\Core\Plugin;
use CGM\Core\Query\SavedQueryRepository;
use CGM\Core\Contracts\BuilderAdapterInterface;

/** Elementor adapter using Elementor's public Dynamic Tags and custom query APIs. */
final class ElementorIntegration implements BuilderAdapterInterface {
    public function id(): string { return 'elementor'; }
    public function detected(): bool { return defined( 'ELEMENTOR_VERSION' ) || did_action( 'elementor/init' ); }
    public function capabilities(): array { return array('dynamic-tag','saved-query-id','traversal','context'); }

    public function __construct( private Plugin $core, private SavedQueryRepository $repo ) {}

    public function register(): void {
        add_action( 'elementor/dynamic_tags/register', array( $this, 'dynamic_tags' ) );

        foreach ( $this->repo->list() as $saved ) {
            $slug = sanitize_key( (string) ( $saved['slug'] ?? '' ) );
            if ( ! $slug || ! $this->is_post_query( $saved ) ) { continue; }
            add_action( 'elementor/query/cgm_' . $slug, function( $query ) use ( $saved, $slug ): void {
                if ( ! is_object( $query ) || ! method_exists( $query, 'set' ) ) { return; }
                $result = $this->core->queries()->run(
                    $slug,
                    array( 'post_id'=>get_the_ID(), 'consumer'=>'elementor', 'location'=>'query-id:cgm_' . $slug )
                );
                $ids = array_values( array_filter( array_map(
                    static fn( $item ): int => $item instanceof \WP_Post ? (int) $item->ID : 0,
                    $result->items
                ) ) );
                $query->set( 'post__in', $ids ?: array( 0 ) );
                $query->set( 'orderby', 'post__in' );
                $query->set( 'posts_per_page', max( 1, count( $ids ) ) );
            } );
        }

        add_filter( 'cgm_core/elementor/dynamic_data', fn( array $items ): array => array_merge( $items, $this->core->dynamic_data()->serialize() ) );
        add_filter( 'cgm_core/elementor/saved_queries', fn( array $items ): array => array_merge( $items, $this->query_descriptors() ) );
        do_action( 'cgm_core/integration/elementor/registered', $this->core, $this->repo );
    }

    public function dynamic_tags( $manager ): void {
        if ( ! class_exists( CGMDynamicTag::class ) ) { return; }
        if ( method_exists( $manager, 'register_group' ) ) {
            $manager->register_group( 'cgm-core', array( 'title'=>__( 'CGM Core', 'cgm-core' ) ) );
        }
        if ( method_exists( $manager, 'register' ) ) { $manager->register( new CGMDynamicTag() ); }
    }

    private function is_post_query( array $saved ): bool {
        $definition = (array) ( $saved['definition'] ?? array() );
        $content = $this->core->content_types()->get( (string) ( $definition['content_type'] ?? 'post' ) );
        return $content && in_array( (string) ( $content['kind'] ?? '' ), array( 'post','media' ), true );
    }

    private function query_descriptors(): array {
        $out = array();
        foreach ( $this->repo->list() as $saved ) {
            $slug = (string) ( $saved['slug'] ?? '' );
            if ( ! $slug ) { continue; }
            $out[ $slug ] = array(
                'id'         => $saved['id'] ?? $slug,
                'slug'       => $slug,
                'label'      => (string) ( $saved['title'] ?? $slug ),
                'query_id'   => 'cgm_' . sanitize_key( $slug ),
                'managed_by' => $saved['managed_by'] ?? 'database',
                'post_query' => $this->is_post_query( $saved ),
            );
        }
        return $out;
    }
}
