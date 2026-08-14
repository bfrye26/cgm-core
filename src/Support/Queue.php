<?php
namespace CGM\Core\Support;

/**
 * Deferred-work queue. Soft-dependency on Action Scheduler: use it when present
 * (durable, resumable), otherwise fall back to WP-Cron single events.
 */
final class Queue {
    public static function schedule_single( int $timestamp, string $hook, array $args = array() ): bool {
        if ( function_exists( 'as_schedule_single_action' ) ) {
            return false !== as_schedule_single_action( $timestamp, $hook, $args );
        }
        if ( ! wp_next_scheduled( $hook, $args ) ) {
            wp_schedule_single_event( $timestamp, $hook, $args );
        }
        return true;
    }
}
