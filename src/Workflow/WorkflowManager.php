<?php
namespace CGM\Core\Workflow;

use CGM\Core\Plugin;
use CGM\Core\Objects\ObjectReference;

/**
 * Editorial workflow states, layered on top of native WP post_status.
 *
 * The current state lives in post meta; "published" is derived from
 * post_status when no explicit state is stored. States are registrable, so
 * plugins can add their own with transitions.
 */
final class WorkflowManager {
    public const META = '_cgm_workflow_state';
    public const META_CHANGED = '_cgm_workflow_state_changed_at';
    private const SCHEDULE_OPTION = 'cgm_core_scheduled_transitions';
    private const AUTO_OPTION = 'cgm_core_auto_transitions';

    private array $states = array();

    public function __construct( private Plugin $core ) {}

    public function register(): void {
        // Default states use plain strings: __() cannot run before `init`, and
        // register() runs during plugins_loaded boot.
        $this->register_state( array( 'id'=>'draft', 'label'=>'Draft', 'color'=>'neutral', 'order'=>1, 'transitions'=>array( 'in_review', 'published', 'archived' ) ) );
        $this->register_state( array( 'id'=>'in_review', 'label'=>'In review', 'color'=>'gold', 'order'=>2, 'transitions'=>array( 'draft', 'published', 'archived' ) ) );
        $this->register_state( array( 'id'=>'published', 'label'=>'Published', 'color'=>'pine', 'order'=>3, 'transitions'=>array( 'draft', 'archived' ) ) );
        $this->register_state( array( 'id'=>'archived', 'label'=>'Archived', 'color'=>'rust', 'order'=>4, 'transitions'=>array( 'published' ) ) );
        // Field + context registration needs content types, which populate on `init`.
        add_action( 'init', array( $this, 'register_field' ), 30 );
        add_action( 'save_post', array( $this, 'ensure_state' ), 20 );
        // Deferred workflow transitions (scheduled unpublish / publish / archive).
        add_action( 'cgm_core/apply_workflow_transition', array( $this, 'apply_scheduled_transition' ) );
        // Recurring auto-transition policy (auto-expire / auto-archive).
        add_action( 'cgm_core/apply_auto_transitions', array( $this, 'run_auto_transitions' ) );
        add_action( 'init', array( $this, 'ensure_auto_transition_cron' ), 40 );
    }

    public function ensure_auto_transition_cron(): void {
        if ( ! wp_next_scheduled( 'cgm_core/apply_auto_transitions' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'cgm_core/apply_auto_transitions' );
        }
    }

    /** Persist the derived state so meta-based filters and queries are complete. */
    public function ensure_state( int $post_id ): void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) { return; }
        if ( get_post_meta( $post_id, self::META, true ) ) { return; }
        update_post_meta( $post_id, self::META, 'publish' === get_post_status( $post_id ) ? 'published' : 'draft' );
        if ( ! get_post_meta( $post_id, self::META_CHANGED, true ) ) { update_post_meta( $post_id, self::META_CHANGED, time() ); }
    }

    public function register_field(): void {
        $post_types = array();
        foreach ( $this->core->content_types()->all() as $ct ) {
            if ( in_array( (string) ( $ct['kind'] ?? '' ), array( 'post', 'media' ), true ) ) { $post_types[] = (string) $ct['id']; }
        }
        $this->core->fields()->register( array(
            'id'=>'workflow.state', 'label'=>__( 'Workflow state', 'cgm-core' ), 'source'=>self::META, 'type'=>'string',
            'operators'=>array( '=', '!=', 'IN', 'NOT IN', 'EXISTS', 'NOT EXISTS' ), 'provider'=>'wordpress-meta',
            'queryable'=>true, 'sortable'=>true, 'content_types'=>$post_types ?: array( '*' ), 'dynamic'=>true, 'public'=>true,
        ) );
        $this->core->context()->register( 'current_state', __( 'Current workflow state', 'cgm-core' ), function ( array $ctx ): string {
            $id = absint( $ctx['current_query_item'] ?? $ctx['post_id'] ?? 0 );
            return $id ? $this->get_state( $id ) : '';
        } );
    }

    public function register_state( array $s ): void {
        $id = sanitize_key( (string) ( $s['id'] ?? '' ) );
        if ( ! $id ) { return; }
        $this->states[ $id ] = array(
            'id'=>$id, 'label'=>sanitize_text_field( (string) ( $s['label'] ?? $id ) ),
            'color'=>sanitize_key( (string) ( $s['color'] ?? 'neutral' ) ), 'order'=>(int) ( $s['order'] ?? 0 ),
            'transitions'=>array_values( array_filter( array_map( 'sanitize_key', (array) ( $s['transitions'] ?? array() ) ) ) ),
        );
    }

    public function states(): array { $s = $this->states; usort( $s, static fn( $a, $b ) => $a['order'] <=> $b['order'] ); return $s; }

    public function get_state( int $id ): string {
        $state = get_post_meta( $id, self::META, true );
        if ( $state && is_string( $state ) && '' !== $state ) { return sanitize_key( $state ); }
        return 'publish' === get_post_status( $id ) ? 'published' : 'draft';
    }

    public function transition( int $id, string $state ): bool {
        if ( ! $id || ! get_post( $id ) ) { return false; }
        $state = sanitize_key( $state );
        if ( ! isset( $this->states[ $state ] ) ) { return false; }
        update_post_meta( $id, self::META, $state );
        update_post_meta( $id, self::META_CHANGED, time() );
        if ( 'published' === $state ) { wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ) ); }
        elseif ( 'draft' === $state && 'publish' === get_post_status( $id ) ) { wp_update_post( array( 'ID' => $id, 'post_status' => 'draft' ) ); }
        do_action( 'cgm_core/workflow_transition', $id, $state );
        $this->core->events()->dispatch( 'workflow.transition', array( 'object_id' => $id, 'state' => $state ) );
        return true;
    }

    /**
     * Auto-transition policy rules: "after N days in a state, move to another".
     * Covers Drupal Scheduler-style auto-expire and auto-archive.
     *
     * @return array<int,array{id:string,content_types:string[],from_state:string,to_state:string,after_days:int,enabled:bool}>
     */
    public function auto_transitions(): array {
        $rules = get_option( self::AUTO_OPTION, false );
        if ( false === $rules ) {
            $rules = array( array(
                'id'=>'default-archive', 'content_types'=>array( '*' ), 'from_state'=>'published', 'to_state'=>'archived', 'after_days'=>30, 'enabled'=>false,
            ) );
            update_option( self::AUTO_OPTION, $rules, false );
        }
        return is_array( $rules ) ? $rules : array();
    }

    public function save_auto_transitions( array $rules ): bool {
        $clean = array();
        foreach ( $rules as $rule ) {
            if ( ! is_array( $rule ) || empty( $rule['id'] ) ) { continue; }
            $clean[] = array(
                'id' => sanitize_key( (string) $rule['id'] ),
                'content_types' => array_values( array_filter( array_map( 'sanitize_key', (array) ( $rule['content_types'] ?? array( '*' ) ) ) ) ),
                'from_state' => sanitize_key( (string) ( $rule['from_state'] ?? 'published' ) ),
                'to_state' => sanitize_key( (string) ( $rule['to_state'] ?? 'archived' ) ),
                'after_days' => max( 1, min( 3650, (int) ( $rule['after_days'] ?? 30 ) ) ),
                'enabled' => ! empty( $rule['enabled'] ),
            );
        }
        return update_option( self::AUTO_OPTION, $clean, false );
    }

    /** Run the auto-transition sweep. Returns the number of objects transitioned. */
    public function run_auto_transitions(): int {
        $count = 0;
        foreach ( $this->auto_transitions() as $rule ) {
            if ( empty( $rule['enabled'] ) ) { continue; }
            $from = sanitize_key( (string) ( $rule['from_state'] ?? 'published' ) );
            $to = sanitize_key( (string) ( $rule['to_state'] ?? 'archived' ) );
            $after = max( 1, (int) ( $rule['after_days'] ?? 30 ) );
            $types = array_values( array_filter( array_map( 'sanitize_key', (array) ( $rule['content_types'] ?? array( '*' ) ) ) ) );
            foreach ( $this->find_due_posts( $from, $after, $types ) as $post_id ) {
                if ( $this->transition( $post_id, $to ) ) { $count++; }
            }
        }
        return $count;
    }

    private function find_due_posts( string $from, int $after_days, array $types ): array {
        $post_types = ( empty( $types ) || in_array( '*', $types, true ) ) ? get_post_types( array( 'public' => true ) ) : $types;
        $query = new \WP_Query( array(
            'post_type' => $post_types, 'post_status' => array( 'publish', 'draft', 'pending', 'private' ),
            'posts_per_page' => 200, 'fields' => 'ids', 'no_found_rows' => true,
        ) );
        $threshold = time() - $after_days * DAY_IN_SECONDS;
        $due = array();
        foreach ( (array) $query->posts as $id ) {
            $id = absint( $id );
            if ( $this->get_state( $id ) !== $from ) { continue; }
            $changed = (int) get_post_meta( $id, self::META_CHANGED, true );
            if ( ! $changed ) {
                $gmt = (string) get_post_field( 'post_date_gmt', $id );
                $changed = $gmt ? (int) strtotime( $gmt . ' GMT' ) : 0;
            }
            if ( $changed && $changed <= $threshold ) { $due[] = $id; }
        }
        return $due;
    }

    /**
     * Schedule a workflow transition for the future (Drupal Scheduler parity:
     * scheduled publish, unpublish, archive, in-review). Uses Action Scheduler
     * when present, otherwise a WP-Cron single event via the Queue helper.
     */
    public function schedule_transition( int $post_id, string $state, int $timestamp ): bool|\WP_Error {
        if ( ! $post_id || ! get_post( $post_id ) ) { return new \WP_Error( 'cgm_workflow_missing_post', __( 'Post not found.', 'cgm-core' ) ); }
        $state = sanitize_key( $state );
        if ( ! isset( $this->states[ $state ] ) ) { return new \WP_Error( 'cgm_workflow_bad_state', __( 'Unknown workflow state.', 'cgm-core' ) ); }
        if ( $timestamp < time() + MINUTE_IN_SECONDS ) { return new \WP_Error( 'cgm_workflow_past_date', __( 'Scheduled time must be in the future.', 'cgm-core' ) ); }

        $id = $post_id . '_' . $timestamp . '_' . $state;
        $scheduled = $this->scheduled_raw();
        $scheduled[ $id ] = array( 'id'=>$id, 'post_id'=>$post_id, 'state'=>$state, 'at'=>$timestamp, 'created'=>time() );
        $this->save_scheduled( $scheduled );

        \CGM\Core\Support\Queue::schedule_single( $timestamp, 'cgm_core/apply_workflow_transition', array( $id ) );
        $this->core->events()->dispatch( 'workflow.scheduled', array( 'post_id'=>$post_id, 'state'=>$state, 'at'=>$timestamp ) );
        return true;
    }

    /** @return array<int,array{id:string,post_id:int,post_title:string,state:string,state_label:string,at:int}> */
    public function scheduled(): array {
        $out = array();
        foreach ( $this->scheduled_raw() as $entry ) {
            $post_id = (int) ( $entry['post_id'] ?? 0 );
            if ( ! $post_id || ! get_post( $post_id ) ) { continue; }
            $state = (string) ( $entry['state'] ?? '' );
            $label = $state;
            foreach ( $this->states as $s ) { if ( $s['id'] === $state ) { $label = $s['label']; break; } }
            $out[] = array(
                'id'=>$entry['id'], 'post_id'=>$post_id, 'post_title'=>get_the_title( $post_id ),
                'state'=>$state, 'state_label'=>$label, 'at'=>(int) ( $entry['at'] ?? 0 ),
            );
        }
        usort( $out, static fn( $a, $b ) => $a['at'] <=> $b['at'] );
        return $out;
    }

    public function cancel_scheduled( string $id ): bool {
        $scheduled = $this->scheduled_raw();
        if ( ! isset( $scheduled[ sanitize_key( $id ) ] ) ) { return false; }
        unset( $scheduled[ sanitize_key( $id ) ] );
        $this->save_scheduled( $scheduled );
        return true;
    }

    public function apply_scheduled_transition( string $id ): void {
        $scheduled = $this->scheduled_raw();
        if ( ! isset( $scheduled[ sanitize_key( $id ) ] ) ) { return; }
        $entry = $scheduled[ sanitize_key( $id ) ];
        unset( $scheduled[ sanitize_key( $id ) ] );
        $this->save_scheduled( $scheduled );
        $post_id = (int) ( $entry['post_id'] ?? 0 );
        if ( ! $post_id || ! get_post( $post_id ) ) { return; }
        $this->transition( $post_id, (string) ( $entry['state'] ?? '' ) );
    }

    private function scheduled_raw(): array {
        $s = get_option( self::SCHEDULE_OPTION, array() );
        return is_array( $s ) ? $s : array();
    }

    private function save_scheduled( array $scheduled ): void {
        update_option( self::SCHEDULE_OPTION, array_slice( $scheduled, -100, null, true ), false );
    }
}
