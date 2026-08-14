<?php
namespace CGM\Core\Support;

/** Dismissable notification inbox. Plugins post notices; the control room lists them. */
final class Notifications {
    private const OPTION = 'cgm_core_notifications';
    private const LIMIT = 50;

    public function notify( string $id, string $title, string $message, string $type = 'info' ): void {
        $list = $this->all();
        $list[ sanitize_key( $id ) ] = array(
            'id' => sanitize_key( $id ), 'title' => sanitize_text_field( $title ), 'message' => sanitize_textarea_field( $message ),
            'type' => sanitize_key( $type ), 'created' => gmdate( DATE_ATOM ),
        );
        update_option( self::OPTION, array_slice( $list, -self::LIMIT, null, true ), false );
    }

    public function all(): array { $l = get_option( self::OPTION, array() ); return is_array( $l ) ? array_values( array_reverse( $l ) ) : array(); }

    public function dismiss( string $id ): void {
        $l = get_option( self::OPTION, array() ); unset( $l[ sanitize_key( $id ) ] );
        update_option( self::OPTION, $l, false );
    }
}
