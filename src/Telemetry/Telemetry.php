<?php
namespace CGM\Core\Telemetry;

use CGM\Core\Query\QueryResult;

/**
 * Lightweight, dependency-free event + query-performance recorder for the
 * control room. EventBus is dispatch-only, so this listens on the generic
 * `cgm_core/event` and `cgm_core/query_executed` hooks, buffers in-memory for
 * the request, and flushes once at shutdown.
 *
 * ponytail: bounded options (no table). If a multi-writer or scale use case
 * appears, promote to a dedicated table + index on occurred_at.
 */
final class Telemetry {
    private const ACTIVITY_OPTION = 'cgm_core_activity';
    private const PERF_OPTION = 'cgm_core_query_perf';
    private const ACTIVITY_LIMIT = 200;
    private const PERF_LIMIT = 200;

    private static array $activity = array();
    private static array $perf = array();

    public function register(): void {
        add_action( 'cgm_core/event', array( $this, 'record_event' ), 10, 3 );
        add_action( 'cgm_core/query_executed', array( $this, 'record_query' ), 10, 4 );
        add_action( 'shutdown', array( $this, 'flush' ) );
    }

    public function record_event( string $event, array $payload, array $envelope ): void {
        self::$activity[] = array(
            'event'       => $event,
            'occurred_at' => (string) ( $envelope['occurred_at'] ?? gmdate( DATE_ATOM ) ),
            'summary'     => $this->summarize( $event, $payload ),
        );
    }

    public function record_query( array $query, array $ctx, mixed $result, array $content_type ): void {
        if ( ! $result instanceof QueryResult ) { return; }
        $debug = (array) ( $result->debug ?? array() );
        $slug  = (string) ( $debug['saved_query_slug'] ?? '' );
        if ( '' === $slug ) { return; } // only saved queries; inline executions would drown the signal
        $ms    = (float) ( $debug['execution_ms_total'] ?? 0 );
        $cache = 'hit' === (string) ( $debug['cache'] ?? '' );
        self::$perf[ $slug ] = array(
            'slug'        => $slug,
            'count'       => 1 + (int) ( self::$perf[ $slug ]['count'] ?? 0 ),
            'total_ms'    => $ms + (float) ( self::$perf[ $slug ]['total_ms'] ?? 0 ),
            'slowest_ms'  => max( $ms, (float) ( self::$perf[ $slug ]['slowest_ms'] ?? 0 ) ),
            'cache_hits'  => ( $cache ? 1 : 0 ) + (int) ( self::$perf[ $slug ]['cache_hits'] ?? 0 ),
            'last_run'    => gmdate( DATE_ATOM ),
        );
    }

    public function flush(): void {
        if ( self::$activity ) {
            $stored = get_option( self::ACTIVITY_OPTION, array() );
            $stored = is_array( $stored ) ? $stored : array();
            $stored = array_merge( $stored, self::$activity );
            update_option( self::ACTIVITY_OPTION, array_slice( $stored, -self::ACTIVITY_LIMIT ), false );
        }
        if ( self::$perf ) {
            $stored = get_option( self::PERF_OPTION, array() );
            $stored = is_array( $stored ) ? $stored : array();
            foreach ( self::$perf as $slug => $row ) {
                $cur = $stored[ $slug ] ?? array( 'slug'=>$slug, 'count'=>0, 'total_ms'=>0.0, 'slowest_ms'=>0.0, 'cache_hits'=>0, 'last_run'=>'' );
                $cur['count']      = (int) ( $cur['count'] ?? 0 ) + (int) $row['count'];
                $cur['total_ms']   = (float) ( $cur['total_ms'] ?? 0 ) + (float) $row['total_ms'];
                $cur['slowest_ms'] = max( (float) ( $cur['slowest_ms'] ?? 0 ), (float) $row['slowest_ms'] );
                $cur['cache_hits'] = (int) ( $cur['cache_hits'] ?? 0 ) + (int) $row['cache_hits'];
                $cur['last_run']   = $row['last_run'];
                $stored[ $slug ]   = $cur;
            }
            uasort( $stored, static fn( $a, $b ) => (int) ( $b['count'] ?? 0 ) <=> (int) ( $a['count'] ?? 0 ) );
            update_option( self::PERF_OPTION, array_slice( $stored, 0, self::PERF_LIMIT, true ), false );
        }
        self::$activity = array();
        self::$perf = array();
    }

    public function activity( int $limit = 50 ): array {
        $stored = get_option( self::ACTIVITY_OPTION, array() );
        $stored = is_array( $stored ) ? $stored : array();
        return array_values( array_slice( array_reverse( $stored ), 0, max( 1, min( self::ACTIVITY_LIMIT, $limit ) ) ) );
    }

    public function performance(): array {
        $stored = get_option( self::PERF_OPTION, array() );
        $stored = is_array( $stored ) ? $stored : array();
        return array_values( $stored );
    }

    private function summarize( string $event, array $payload ): string {
        switch ( $event ) {
            case 'relationship.changed':
                return sprintf(
                    /* translators: 1: relationship id, 2: source type, 3: source id, 4: item count. */
                    __( 'Relationship "%1$s" updated for %2$s #%3$d (%4$d items)', 'cgm-core' ),
                    (string) ( $payload['relationship'] ?? '' ),
                    (string) ( $payload['source_type'] ?? '' ),
                    (int) ( $payload['source_id'] ?? 0 ),
                    count( (array) ( $payload['items'] ?? array() ) )
                );
            case 'content.changed':
                return sprintf(
                    /* translators: 1: object type, 2: object id. */
                    __( 'Content changed: %1$s #%2$d', 'cgm-core' ),
                    (string) ( $payload['object_type'] ?? '' ),
                    (int) ( $payload['object_id'] ?? 0 )
                );
            case 'relationship.delete.cascade':
                return sprintf(
                    /* translators: %s: relationship id. */
                    __( 'Cascade delete for relationship "%s"', 'cgm-core' ),
                    (string) ( $payload['relationship'] ?? '' )
                );
            case 'relationship.object_deleted':
                return __( 'Object deleted: relationship cleanup ran', 'cgm-core' );
            case 'activity.logged':
                $message = (string) ( $payload['message'] ?? '' );
                if ( $message ) { return $message; }
                return sprintf(
                    /* translators: %s: event type. */
                    __( '%s recorded', 'cgm-core' ),
                    (string) ( $payload['event_type'] ?? 'activity' )
                );
            default:
                return str_replace( '.', ' ', $event );
        }
    }
}
