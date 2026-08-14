<?php
namespace CGM\Core\Events;

final class EventBus {
    private array $schemas = array();

    public function register( string $event, string $version = '1.0', array $required = array(), array $definition = array() ): void {
        $event = $this->normalize_event( $event ); if ( ! $event ) { return; }
        $this->schemas[ $event ] = wp_parse_args( $definition, array( 'event'=>$event, 'version'=>$version, 'required'=>array_values($required), 'description'=>'' ) );
    }
    public function schema( string $event ): ?array { return $this->schemas[ $this->normalize_event( $event ) ] ?? null; }
    public function schemas(): array { return $this->schemas; }

    public function dispatch( string $event, array $payload = array() ): void {
        $event = $this->normalize_event( $event ); if ( ! $event ) { return; }
        $schema = $this->schema( $event );
        $envelope = array(
            'event'=>$event, 'version'=>(string)($schema['version']??'1.0'), 'occurred_at'=>gmdate(DATE_ATOM),
            'payload'=>$payload, 'schema_valid'=>$this->validate_payload($payload,$schema),
        );
        do_action( 'cgm_core/event/' . sanitize_key( str_replace( '.', '_', $event ) ), $payload, $event, $envelope );
        do_action( 'cgm_core/event', $event, $payload, $envelope );
    }
    public function listen( string $event, callable $callback, int $priority = 10 ): void {
        add_action( 'cgm_core/event/' . sanitize_key( str_replace( '.', '_', $this->normalize_event($event) ) ), $callback, $priority, 3 );
    }
    private function validate_payload( array $payload, ?array $schema ): bool {
        if ( ! $schema ) { return true; }
        foreach ( (array) ( $schema['required'] ?? array() ) as $key ) { if ( ! array_key_exists( $key, $payload ) ) { return false; } }
        return true;
    }
    private function normalize_event( string $event ): string { return trim( strtolower( preg_replace( '/[^a-zA-Z0-9_.-]+/', '_', $event ) ), '._-' ); }
}
