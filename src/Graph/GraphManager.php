<?php
namespace CGM\Core\Graph;

use CGM\Core\Plugin;

/**
 * Object/relationship graph: walk relationships from a root object and return
 * nodes + edges. Backs the admin graph visualizer and "everything connected to X".
 */
final class GraphManager {
    public function __construct( private Plugin $core ) {}

    public function graph( int $object_id, string $content_type, int $depth = 1 ): array {
        $nodes = array(); $edges = array(); $seen = array();
        $this->walk( $object_id, $content_type, max( 0, min( 4, $depth ) ), $nodes, $edges, $seen );
        return array( 'nodes' => array_values( $nodes ), 'edges' => array_values( $edges ) );
    }

    private function walk( int $id, string $type, int $depth, array &$nodes, array &$edges, array &$seen ): void {
        $key = $type . ':' . $id;
        if ( isset( $seen[ $key ] ) ) { return; }
        $seen[ $key ] = true;
        $ref = $this->core->objects()->reference( $id, $type );
        $nodes[ $key ] = array( 'id' => $id, 'type' => $type, 'label' => $ref ? $this->core->objects()->label( $ref ) : ( '#' . $id ) );
        if ( $depth <= 0 ) { return; }

        foreach ( $this->core->relationships()->all() as $rel ) {
            $rel_id = (string) $rel['id'];
            $sources = (array) ( $rel['source_types'] ?? array( $rel['source_type'] ?? '' ) );
            if ( '*' === (string) ( $rel['source_type'] ?? '' ) || in_array( $type, $sources, true ) ) {
                foreach ( $this->core->relationships()->get( $rel_id, $id, array( 'source_type' => $type ) ) as $row ) {
                    $tid = absint( $row['target_id'] ?? 0 ); $ttype = (string) ( $rel['target_type'] ?? '' );
                    if ( ! $tid || '*' === $ttype ) { continue; }
                    $edges[] = array( 'source' => $key, 'target' => $ttype . ':' . $tid, 'relationship' => $rel_id, 'label' => $rel['label'] ?? $rel_id );
                    $this->walk( $tid, $ttype, $depth - 1, $nodes, $edges, $seen );
                }
            }
            if ( (string) ( $rel['target_type'] ?? '' ) === $type ) {
                foreach ( $this->core->relationships()->get_reverse( $rel_id, $id, array( 'target_type' => $type ) ) as $row ) {
                    $sid = absint( $row['source_id'] ?? 0 ); $stype = (string) ( $row['source_type'] ?? $rel['source_type'] ?? 'post' );
                    if ( ! $sid || '*' === $stype ) { continue; }
                    $edges[] = array( 'source' => $stype . ':' . $sid, 'target' => $key, 'relationship' => $rel_id, 'label' => $rel['reverse_label'] ?? $rel['label'] ?? $rel_id );
                    $this->walk( $sid, $stype, $depth - 1, $nodes, $edges, $seen );
                }
            }
        }
    }
}
