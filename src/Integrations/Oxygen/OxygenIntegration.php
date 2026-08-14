<?php
namespace CGM\Core\Integrations\Oxygen;

use CGM\Core\Plugin;
use CGM\Core\Query\SavedQueryRepository;
use CGM\Core\Contracts\BuilderAdapterInterface;

/**
 * Oxygen adapter.
 *
 * Oxygen 6 exposes PHP/custom-query and Dynamic Data extension workflows. Core avoids
 * private builder internals and publishes a stable data/query/condition bridge that can
 * be used directly in Oxygen PHP/query controls and by an Oxygen-side UI connector.
 */
final class OxygenIntegration implements BuilderAdapterInterface {
    public function id(): string { return 'oxygen'; }
    public function detected(): bool { return defined( 'CT_VERSION' ) || defined( 'OXYGEN_VSB_VERSION' ) || class_exists( 'Oxygen\\Builder' ); }
    public function capabilities(): array { return array('query-bridge','dynamic-data-bridge','conditions-bridge','traversal','context','shortcodes'); }

    public function __construct( private Plugin $core, private SavedQueryRepository $repo ) {}

    public function register(): void {
        add_filter( 'cgm_core/oxygen/dynamic_data', fn( array $data ): array => array_merge( $data, $this->dynamic_descriptors() ) );
        add_filter( 'cgm_core/oxygen/saved_queries', fn( array $data ): array => array_merge( $data, $this->query_descriptors() ) );
        add_filter( 'cgm_core/oxygen/conditions', fn( array $data ): array => array_merge( $data, $this->condition_descriptors() ) );
        add_filter( 'cgm_core/oxygen/resolve_data', array( $this, 'resolve_data' ), 10, 4 );
        add_filter( 'cgm_core/oxygen/run_query', array( $this, 'run_query' ), 10, 4 );
        add_filter( 'cgm_core/oxygen/evaluate_condition', array( $this, 'evaluate_condition' ), 10, 5 );
        do_action( 'cgm_core/integration/oxygen/registered', $this->core, $this->repo );
    }

    public function resolve_data( mixed $default, string $key, int $object_id = 0, array $context = array() ): mixed {
        return $key ? $this->core->dynamic_data()->resolve( $key, $object_id ?: null, $context ) : $default;
    }

    public function run_query( mixed $default, string|int $query, int $post_id = 0, array $context = array() ): mixed {
        if ( '' === (string) $query ) { return $default; }
        return $this->core->queries()->run( $query, $context + array( 'post_id'=>$post_id ?: get_the_ID(), 'consumer'=>'oxygen' ) );
    }

    public function evaluate_condition( bool $default, string $key, string $operator, mixed $value, int $object_id = 0 ): bool {
        if ( ! $key ) { return $default; }
        $actual = $this->core->dynamic_data()->resolve( $key, $object_id ?: null );
        return $this->compare( $actual, $operator, $value );
    }

    private function dynamic_descriptors(): array {
        $out = array();
        foreach ( $this->core->dynamic_data()->all() as $id => $definition ) {
            $out[ $id ] = array(
                'id'       => $id,
                'label'    => (string) ( $definition['label'] ?? $id ),
                'type'     => (string) ( $definition['type'] ?? 'string' ),
                'category' => (string) ( $definition['group'] ?? 'CGM Core' ),
                'resolver' => 'cgm_oxygen_data',
            );
        }
        $out['__traversal__'] = array( 'id'=>'__traversal__','label'=>__( 'CGM traversal path', 'cgm-core' ),'type'=>'string','category'=>'CGM Core','resolver'=>'cgm_oxygen_data','supports_arbitrary_path'=>true );
        return $out;
    }

    private function query_descriptors(): array {
        $out = array();
        foreach ( $this->repo->list() as $query ) {
            $slug = (string) ( $query['slug'] ?? '' ); if ( ! $slug ) { continue; }
            $out[ $slug ] = array( 'id'=>$query['id'] ?? $slug,'slug'=>$slug,'label'=>$query['title'] ?? $slug,'resolver'=>'cgm_oxygen_query','managed_by'=>$query['managed_by'] ?? 'database' );
        }
        return $out;
    }

    private function condition_descriptors(): array {
        return array(
            'dynamic_data' => array(
                'label'     => __( 'CGM Dynamic Data', 'cgm-core' ),
                'resolver'  => 'cgm_oxygen_condition',
                'operators' => array( '=', '!=', '>', '>=', '<', '<=', 'CONTAINS', 'NOT CONTAINS', 'EXISTS', 'NOT EXISTS' ),
            ),
        );
    }

    private function compare( mixed $actual, string $operator, mixed $expected ): bool {
        $op = strtoupper( trim( $operator ) ); $string = is_array($actual)?implode(', ',array_map('strval',$actual)):(string)$actual;
        return match($op){'EXISTS'=>null!==$actual&&''!==$string,'NOT EXISTS'=>null===$actual||''===$string,'!='=>$actual!=$expected,'>'=>$actual>$expected,'>='=>$actual>=$expected,'<'=>$actual<$expected,'<='=>$actual<=$expected,'CONTAINS'=>false!==stripos($string,(string)$expected),'NOT CONTAINS'=>false===stripos($string,(string)$expected),default=>$actual==$expected};
    }
}
