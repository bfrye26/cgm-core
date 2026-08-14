<?php
namespace CGM\Core\Rules;

use CGM\Core\Plugin;
use CGM\Core\Objects\ObjectReference;

/**
 * Event → conditions → actions automation, the Drupal-Rules equivalent.
 *
 * Rules are stored as a bounded option (no table). Evaluation is re-entrancy
 * guarded so a rule that dispatches an event cannot recurse without limit.
 * Built-in actions: dispatch, reindex, purge; plugins register more via
 * `cgm_register_rule_action()`.
 */
final class RuleEngine {
    private const OPTION = 'cgm_core_rules';
    private const MAX_DEPTH = 5;

    private array $actions = array();
    private static int $depth = 0;

    public function __construct( private Plugin $core ) {}

    public function register(): void {
        add_action( 'cgm_core/event', array( $this, 'evaluate' ), 10, 3 );
        // Deferred dispatch for the `schedule` action.
        add_action( 'cgm_core/scheduled_rule', array( $this, 'run_scheduled' ), 10, 2 );
    }

    public function run_scheduled( string $event, array $payload ): void {
        $this->core->events()->dispatch( sanitize_key( $event ), (array) $payload );
    }

    public function register_action( string $id, string $label, callable $callback ): void {
        $this->actions[ sanitize_key( $id ) ] = array( 'label' => $label, 'callback' => $callback );
    }

    /** @return array<string,array{id:string,label:string}> */
    public function actions(): array {
        $out = array();
        foreach ( $this->actions as $id => $a ) { $out[ $id ] = array( 'id' => $id, 'label' => $a['label'] ); }
        return $out;
    }

    public function all(): array { $r = get_option( self::OPTION, array() ); return is_array( $r ) ? $r : array(); }

    public function save( array $rules ): bool {
        $clean = array();
        foreach ( $rules as $rule ) {
            if ( ! is_array( $rule ) || empty( $rule['id'] ) ) { continue; }
            $clean[] = array(
                'id'         => sanitize_key( (string) $rule['id'] ),
                'label'      => sanitize_text_field( (string) ( $rule['label'] ?? $rule['id'] ) ),
                'event'      => sanitize_key( (string) ( $rule['event'] ?? '*' ) ),
                'enabled'    => ! empty( $rule['enabled'] ),
                'conditions' => $this->sanitize_conditions( (array) ( $rule['conditions'] ?? array() ) ),
                'actions'    => $this->sanitize_actions( (array) ( $rule['actions'] ?? array() ) ),
            );
        }
        return update_option( self::OPTION, $clean, false );
    }

    public function evaluate( string $event, array $payload, array $envelope ): void {
        if ( self::$depth >= self::MAX_DEPTH ) { return; }
        self::$depth++;
        try {
            foreach ( $this->all() as $rule ) {
                if ( ! is_array( $rule ) || empty( $rule['enabled'] ) ) { continue; }
                if ( '*' !== ( $rule['event'] ?? '*' ) && ( $rule['event'] ?? '' ) !== $event ) { continue; }
                if ( $this->matches( (array) ( $rule['conditions'] ?? array() ), $payload, $event ) ) {
                    $this->run_actions( (array) ( $rule['actions'] ?? array() ), $payload, $event );
                }
            }
        } finally {
            self::$depth--;
        }
    }

    /**
     * Run a raw (unsanitized) action list against a synthesized payload. Used by
     * bulk operations; rules persist sanitized actions, bulk callers do not.
     */
    public function execute( array $actions, array $payload, string $event = 'bulk.operation' ): void {
        $this->run_actions( $this->sanitize_actions( $actions ), $payload, $event );
    }

    private function matches( array $conditions, array $payload, string $event ): bool {
        if ( ! $conditions ) { return true; }
        [ $id, $type ] = $this->object_from_payload( $payload, $event );
        foreach ( $conditions as $c ) {
            if ( ! is_array( $c ) ) { continue; }
            $cond_type = sanitize_key( (string) ( $c['type'] ?? 'field' ) );
            if ( 'relationship' === $cond_type ) {
                $rel    = sanitize_key( (string) ( $c['relationship'] ?? '' ) );
                $op     = strtoupper( (string) ( $c['operator'] ?? 'EXISTS' ) );
                $exists = $id && $rel && (bool) $this->core->relationships()->get( $rel, $id );
                if ( 'EXISTS' === $op && ! $exists ) { return false; }
                if ( 'NOT EXISTS' === $op && $exists ) { return false; }
                continue;
            }
            $field = (string) ( $c['field'] ?? $c['path'] ?? '' );
            if ( ! $field ) { continue; }
            $op    = (string) ( $c['operator'] ?? '=' );
            $value = $c['value'] ?? '';
            $object = $id && $type ? new ObjectReference( $type, $id ) : null;
            if ( ! cgm_builder_condition( $field, $op, $value, $object, array( 'post_id' => $id ) ) ) { return false; }
        }
        return true;
    }

    private function object_from_payload( array $payload, string $event ): array {
        $id   = absint( $payload['object_id'] ?? $payload['source_id'] ?? $payload['post_id'] ?? 0 );
        $type = sanitize_key( (string) ( $payload['object_type'] ?? $payload['source_type'] ?? 'post' ) );
        return array( $id, $type );
    }

    private function run_actions( array $actions, array $payload, string $event ): void {
        foreach ( $actions as $a ) {
            if ( ! is_array( $a ) ) { continue; }
            $type = sanitize_key( (string) ( $a['type'] ?? 'dispatch' ) );
            [ $id, $obj_type ] = $this->object_from_payload( $payload, $event );
            if ( 'dispatch' === $type ) {
                $this->core->events()->dispatch( (string) ( $a['event'] ?? $event ), $payload );
            } elseif ( 'reindex' === $type ) {
                $this->core->events()->dispatch( 'index.rebuild', array( 'index' => sanitize_key( (string) ( $a['index'] ?? '' ) ), 'object_type' => $obj_type, 'object_id' => $id ) );
            } elseif ( 'purge' === $type ) {
                $this->core->cache()->bump( sanitize_key( (string) ( $a['tag'] ?? 'content' ) ) );
            } elseif ( 'action' === $type ) {
                $key = sanitize_key( (string) ( $a['action'] ?? '' ) );
                if ( isset( $this->actions[ $key ] ) ) { call_user_func( $this->actions[ $key ]['callback'], $payload, $event, $a ); }
            } elseif ( 'set_meta' === $type ) {
                $this->run_set_meta( $a, $id, $obj_type, $payload, $event );
            } elseif ( 'set_term' === $type ) {
                $this->run_set_term( $a, $id, $payload, $event );
            } elseif ( 'set_status' === $type ) {
                $status = sanitize_key( (string) ( $a['status'] ?? '' ) );
                if ( $status && $id && get_post( $id ) ) { wp_update_post( array( 'ID' => $id, 'post_status' => $status ) ); }
            } elseif ( 'add_relationship' === $type ) {
                $this->run_add_relationship( $a, $id, $payload, $event );
            } elseif ( 'notify' === $type ) {
                $this->run_notify( $a, $id, $payload, $event );
            } elseif ( 'webhook' === $type ) {
                $this->run_webhook( $a, $payload, $event );
            } elseif ( 'schedule' === $type ) {
                $delay = max( 1, (int) ( $a['delay'] ?? 5 ) );
                $ev    = sanitize_key( (string) ( $a['event'] ?? $event ) );
                if ( $ev ) { \CGM\Core\Support\Queue::schedule_single( time() + $delay * MINUTE_IN_SECONDS, 'cgm_core/scheduled_rule', array( $ev, $payload ) ); }
            } elseif ( 'add_term' === $type ) {
                $this->run_add_term( $a, $id, $payload, $event );
            }
        }
    }

    private function run_add_term( array $a, int $id, array $payload, string $event ): void {
        $taxonomy = sanitize_key( (string) ( $a['taxonomy'] ?? '' ) );
        $name     = $this->resolve_token( (string) ( $a['term'] ?? '' ), $payload, $event );
        if ( ! $taxonomy || ! $name || ! $id || ! get_post( $id ) ) { return; }
        $term = get_term_by( 'name', $name, $taxonomy );
        if ( $term && ! is_wp_error( $term ) ) { $term_id = (int) $term->term_id; }
        else {
            $created = wp_insert_term( $name, $taxonomy );
            if ( is_wp_error( $created ) ) { return; }
            $term_id = (int) $created['term_id'];
        }
        wp_set_object_terms( $id, array( $term_id ), $taxonomy, true );
    }

    private function run_webhook( array $a, array $payload, string $event ): void {
        $url = esc_url_raw( (string) ( $a['url'] ?? '' ) );
        if ( ! $url ) { return; }
        // SSRF guard: https only, no private/reserved ranges, with a filter
        // escape hatch for controlled internal endpoints.
        $allowed = (bool) apply_filters( 'cgm_core/rule_webhook_url_allowed', false, $url );
        if ( ! $allowed ) {
            if ( 'https' !== (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) { return; }
            $host = (string) wp_parse_url( $url, PHP_URL_HOST );
            $ip = gethostbyname( $host );
            if ( $host === $ip || filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) { return; }
        }
        $body = array(
            'event'   => $event,
            'payload' => $this->resolve_payload( $payload, $event ),
        );
        wp_remote_post( $url, array(
            'timeout'   => 10,
            'blocking'  => false,
            'headers'   => array( 'Content-Type' => 'application/json' ),
            'body'      => wp_json_encode( $body ),
        ) );
    }

    private function resolve_payload( array $payload, string $event ): array {
        $out = array();
        foreach ( $payload as $k => $v ) {
            $out[ $k ] = is_string( $v ) ? $this->resolve_token( $v, $payload, $event ) : $v;
        }
        return $out;
    }

    private function run_set_meta( array $a, int $id, string $obj_type, array $payload, string $event ): void {
        $key = sanitize_key( (string) ( $a['meta_key'] ?? '' ) );
        if ( ! $key || ! $id ) { return; }
        $value = $this->resolve_token( (string) ( $a['meta_value'] ?? '' ), $payload, $event );
        if ( get_post( $id ) ) { update_post_meta( $id, $key, $value ); }
        elseif ( get_userdata( $id ) ) { update_user_meta( $id, $key, $value ); }
        elseif ( str_starts_with( $obj_type, 'term_' ) ) { update_term_meta( $id, $key, $value ); }
    }

    private function run_set_term( array $a, int $id, array $payload, string $event ): void {
        $taxonomy = sanitize_key( (string) ( $a['taxonomy'] ?? '' ) );
        $term     = $this->resolve_token( (string) ( $a['term'] ?? '' ), $payload, $event );
        if ( ! $taxonomy || ! $term || ! $id || ! get_post( $id ) ) { return; }
        $term_obj = is_numeric( $term ) ? get_term( (int) $term, $taxonomy ) : get_term_by( 'slug', $term, $taxonomy );
        if ( $term_obj && ! is_wp_error( $term_obj ) ) { wp_set_object_terms( $id, array( (int) $term_obj->term_id ), $taxonomy, true ); }
    }

    private function run_add_relationship( array $a, int $id, array $payload, string $event ): void {
        $rel    = sanitize_key( (string) ( $a['relationship'] ?? '' ) );
        $target = absint( $this->resolve_token( (string) ( $a['target'] ?? '' ), $payload, $event ) );
        if ( ! $rel || ! $target || ! $id ) { return; }
        $items   = $this->core->relationships()->get( $rel, $id );
        $items[] = array( 'id' => $target );
        $this->core->relationships()->replace( $rel, $id, $items );
    }

    private function run_notify( array $a, int $id, array $payload, string $event ): void {
        $to      = trim( (string) ( $a['to'] ?? '' ) );
        $subject = $this->resolve_token( (string) ( $a['subject'] ?? '' ), $payload, $event );
        $message = $this->resolve_token( (string) ( $a['message'] ?? '' ), $payload, $event );
        if ( ! $to || ! $subject ) { return; }
        if ( 'author' === $to ) {
            $author = $id && get_post( $id ) ? (int) get_post_field( 'post_author', $id ) : 0;
            $to = $author ? get_the_author_meta( 'user_email', $author ) : get_option( 'admin_email' );
        } elseif ( 'admin' === $to ) {
            $to = get_option( 'admin_email' );
        }
        if ( is_email( $to ) ) { wp_mail( $to, $subject, $message ); }
    }

    private function resolve_token( string $value, array $payload, string $event ): string {
        [ $id, $type ] = $this->object_from_payload( $payload, $event );
        $title = $id && get_post( $id ) ? get_the_title( $id ) : '';
        return str_replace( array( '{object_id}', '{object_type}', '{title}' ), array( (string) $id, $type, $title ), $value );
    }

    private function sanitize_conditions( array $conditions ): array {
        $out = array();
        foreach ( $conditions as $c ) {
            if ( ! is_array( $c ) ) { continue; }
            $type = sanitize_key( (string) ( $c['type'] ?? 'field' ) );
            $out[] = array(
                'type'         => $type,
                'field'        => sanitize_text_field( (string) ( $c['field'] ?? '' ) ),
                'relationship' => sanitize_key( (string) ( $c['relationship'] ?? '' ) ),
                'operator'     => strtoupper( (string) ( $c['operator'] ?? '=' ) ),
                'value'        => is_array( $c['value'] ?? null ) ? array_map( 'sanitize_text_field', (array) $c['value'] ) : sanitize_text_field( (string) ( $c['value'] ?? '' ) ),
            );
        }
        return $out;
    }

    private function sanitize_actions( array $actions ): array {
        $out = array();
        foreach ( $actions as $a ) {
            if ( ! is_array( $a ) ) { continue; }
            $out[] = array(
                'type'         => sanitize_key( (string) ( $a['type'] ?? 'dispatch' ) ),
                'event'        => sanitize_key( (string) ( $a['event'] ?? '' ) ),
                'index'        => sanitize_key( (string) ( $a['index'] ?? '' ) ),
                'tag'          => sanitize_key( (string) ( $a['tag'] ?? '' ) ),
                'action'       => sanitize_key( (string) ( $a['action'] ?? '' ) ),
                'meta_key'     => sanitize_key( (string) ( $a['meta_key'] ?? '' ) ),
                'meta_value'   => sanitize_text_field( (string) ( $a['meta_value'] ?? '' ) ),
                'taxonomy'     => sanitize_key( (string) ( $a['taxonomy'] ?? '' ) ),
                'term'         => sanitize_text_field( (string) ( $a['term'] ?? '' ) ),
                'status'       => sanitize_key( (string) ( $a['status'] ?? '' ) ),
                'relationship' => sanitize_key( (string) ( $a['relationship'] ?? '' ) ),
                'target'       => sanitize_text_field( (string) ( $a['target'] ?? '' ) ),
                'to'           => sanitize_text_field( (string) ( $a['to'] ?? '' ) ),
                'subject'      => sanitize_text_field( (string) ( $a['subject'] ?? '' ) ),
                'message'      => sanitize_textarea_field( (string) ( $a['message'] ?? '' ) ),
                'url'          => esc_url_raw( (string) ( $a['url'] ?? '' ) ),
                'delay'        => max( 1, absint( $a['delay'] ?? 5 ) ),
            );
        }
        return $out;
    }
}
