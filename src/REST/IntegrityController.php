<?php
namespace CGM\Core\REST;

use CGM\Core\Plugin;
use CGM\Core\Relationships\CoreRelationshipStore;

/**
 * Read-only integrity audit + guarded repair over Core-owned relationship rows.
 *
 * Provider-owned stores are deliberately not scanned — their storage is owned by
 * their plugin. Only the `core` store (the dedicated relationship table) is
 * directly auditable here.
 */
final class IntegrityController extends BaseController {
    private const LIMIT = 5000;

    public function __construct( private Plugin $core ) {}

    public function register_routes(): void {
        register_rest_route( $this->namespace, '/integrity/overview', array(
            'methods'=>\WP_REST_Server::READABLE, 'callback'=>array($this,'overview'),
            'permission_callback'=>fn()=>$this->can_view(),
        ) );
        register_rest_route( $this->namespace, '/integrity/(?P<id>[a-z0-9_-]+)/issues', array(
            'methods'=>\WP_REST_Server::READABLE, 'callback'=>array($this,'issues'),
            'permission_callback'=>fn()=>$this->can_view(),
            'args'=>array( 'id'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_key'), 'type'=>array('type'=>'string','enum'=>array('orphan_target','orphan_source','cardinality'),'default'=>'orphan_target','sanitize_callback'=>'sanitize_key') ),
        ) );
        register_rest_route( $this->namespace, '/integrity/(?P<id>[a-z0-9_-]+)/repair', array(
            'methods'=>\WP_REST_Server::CREATABLE, 'callback'=>array($this,'repair'),
            'permission_callback'=>fn()=>current_user_can('manage_cgm_relationships')&&$this->rest_nonce_ok(),
            'args'=>array( 'id'=>array('required'=>true,'type'=>'string','sanitize_callback'=>'sanitize_key'), 'apply'=>array('type'=>'boolean','default'=>false) ),
        ) );
    }

    private function can_view(): bool {
        return current_user_can( 'inspect_cgm_data' ) || current_user_can( 'inspect_cgm_core' ) || $this->can_manage() || current_user_can( 'manage_cgm_relationships' );
    }

    public function overview( \WP_REST_Request $r ): \WP_REST_Response {
        $items = array();
        foreach ( $this->core->relationships()->all() as $d ) {
            $store = $this->core->relationships()->store( (string) ( $d['store'] ?? 'core' ) );
            if ( ! $store instanceof CoreRelationshipStore ) {
                $items[] = array( 'id'=>$d['id'], 'label'=>$d['label'] ?? $d['id'], 'store'=>$d['store'] ?? 'core', 'scannable'=>false, 'links'=>0, 'orphan_targets'=>0, 'orphan_sources'=>0, 'cardinality_violations'=>0 );
                continue;
            }
            $id = (string) $d['id'];
            $items[] = array(
                'id' => $id, 'label' => $d['label'] ?? $id, 'store' => $d['store'] ?? 'core', 'scannable' => true,
                'links' => $store->count_links( $id ),
                'orphan_targets' => count( $this->orphan_targets( $store, $id ) ),
                'orphan_sources' => count( $this->orphan_sources( $store, $id ) ),
                'cardinality_violations' => count( $this->cardinality_violations( $store, $id, $d ) ),
            );
        }
        return rest_ensure_response( array( 'items' => $items ) );
    }

    public function issues( \WP_REST_Request $r ): \WP_REST_Response {
        $id = (string) $r['id'];
        $d = $this->core->relationships()->get_type( $id );
        if ( ! $d ) { return new \WP_REST_Response( array( 'message' => __( 'Relationship not found.', 'cgm-core' ) ), 404 ); }
        $store = $this->core->relationships()->store( (string) ( $d['store'] ?? 'core' ) );
        if ( ! $store instanceof CoreRelationshipStore ) { return rest_ensure_response( array( 'items' => array(), 'note' => 'provider-owned' ) ); }

        $type = (string) $r->get_param( 'type' );
        $items = match ( $type ) {
            'orphan_source' => $this->orphan_sources( $store, $id ),
            'cardinality'   => $this->cardinality_violations( $store, $id, $d ),
            default         => $this->orphan_targets( $store, $id ),
        };
        return rest_ensure_response( array( 'items' => $items, 'count' => count( $items ) ) );
    }

    public function repair( \WP_REST_Request $r ): \WP_REST_Response {
        $id = (string) $r['id'];
        $d = $this->core->relationships()->get_type( $id );
        if ( ! $d ) { return new \WP_REST_Response( array( 'message' => __( 'Relationship not found.', 'cgm-core' ) ), 404 ); }
        $store = $this->core->relationships()->store( (string) ( $d['store'] ?? 'core' ) );
        if ( ! $store instanceof CoreRelationshipStore ) { return new \WP_REST_Response( array( 'success'=>false, 'code'=>'not_scannable', 'message'=>__( 'This relationship is provider-owned and cannot be repaired here.', 'cgm-core' ) ), 400 ); }

        $orphans = $this->orphan_targets( $store, $id );
        $apply = (bool) $r->get_param( 'apply' );
        if ( ! $orphans ) { return rest_ensure_response( array( 'success'=>true, 'removed'=>0, 'dry_run'=>!$apply ) ); }
        if ( ! $apply ) { return rest_ensure_response( array( 'success'=>true, 'dry_run'=>true, 'would_remove'=>count( $orphans ), 'targets'=>$orphans ) ); }

        $by_type = array();
        foreach ( $orphans as $orphan ) { $by_type[ (string) $orphan['target_type'] ][] = (int) $orphan['target_id']; }
        $removed = 0;
        foreach ( $by_type as $target_type => $ids ) {
            $result = $store->purge_missing_targets( $id, $target_type, $ids );
            if ( false !== $result ) { $removed += (int) $result; }
        }
        if ( $removed ) {
            $this->core->cache()->bump( 'relationship:' . $id );
            $this->core->events()->dispatch( 'relationship.object_deleted', array( 'relationship'=>$id, 'removed'=>$removed ) );
        }
        return rest_ensure_response( array( 'success'=>true, 'removed'=>$removed, 'dry_run'=>false ) );
    }

    private function orphan_targets( CoreRelationshipStore $store, string $id ): array {
        $out = array();
        foreach ( $store->distinct_targets( $id, self::LIMIT ) as $row ) {
            $type = (string) $row['target_type'];
            if ( '*' === $type ) { continue; }
            $ref = $this->core->objects()->reference( (int) $row['target_id'], $type );
            if ( ! $ref || ! $this->core->objects()->exists( $ref ) ) { $out[] = array( 'target_type'=>$type, 'target_id'=>(int) $row['target_id'] ); }
        }
        return $out;
    }

    private function orphan_sources( CoreRelationshipStore $store, string $id ): array {
        $out = array();
        foreach ( $store->distinct_sources( $id, self::LIMIT ) as $row ) {
            $type = (string) $row['source_type'];
            if ( '*' === $type ) { continue; }
            $ref = $this->core->objects()->reference( (int) $row['source_id'], $type );
            if ( ! $ref || ! $this->core->objects()->exists( $ref ) ) { $out[] = array( 'source_type'=>$type, 'source_id'=>(int) $row['source_id'] ); }
        }
        return $out;
    }

    private function cardinality_violations( CoreRelationshipStore $store, string $id, array $d ): array {
        $max = (int) ( $d['max_items'] ?? 0 );
        $allowed = $max > 0 ? $max : ( empty( $d['multiple'] ) ? 1 : 0 );
        if ( $allowed < 1 ) { return array(); }
        $out = array();
        foreach ( $store->source_target_counts( $id, self::LIMIT ) as $row ) {
            if ( (int) $row['cnt'] > $allowed ) { $out[] = array( 'source_type'=>$row['source_type'], 'source_id'=>(int) $row['source_id'], 'count'=>(int) $row['cnt'], 'limit'=>$allowed ); }
        }
        return $out;
    }
}
