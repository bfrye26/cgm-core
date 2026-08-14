<?php
namespace CGM\Core\Support;

/**
 * Relative date expansion for query values. Resolves tokens like `now`, `today`,
 * `-30d`, `-3m`, `this month` to concrete `Y-m-d H:i:s` strings so a date/datetime
 * field rule can express "published in the last 30 days".
 *
 * Returns the input unchanged when it is not a relative date.
 */
final class RelativeDate {
    public static function expand( mixed $value ): mixed {
        if ( is_array( $value ) ) { return $value; }
        $s = trim( (string) $value );
        if ( '' === $s ) { return $value; }

        if ( str_contains( $s, ',' ) ) {
            $parts = array_map( 'trim', explode( ',', $s ) );
            $out = array();
            foreach ( $parts as $part ) {
                $e = self::expand_one( $part );
                if ( null === $e ) { return $value; }
                $out[] = $e;
            }
            return implode( ',', $out );
        }

        $e = self::expand_one( $s );
        return null === $e ? $value : $e;
    }

    private static function expand_one( string $s ): ?string {
        $key = strtolower( $s );
        $today = new \DateTimeImmutable( 'now', wp_timezone() );

        $named = array(
            'now'         => $today->format( 'Y-m-d H:i:s' ),
            'today'       => $today->setTime( 0, 0 )->format( 'Y-m-d H:i:s' ),
            'yesterday'   => $today->modify( '-1 day' )->setTime( 0, 0 )->format( 'Y-m-d H:i:s' ),
            'tomorrow'    => $today->modify( '+1 day' )->setTime( 0, 0 )->format( 'Y-m-d H:i:s' ),
            'this week'   => $today->modify( 'monday this week' )->setTime( 0, 0 )->format( 'Y-m-d H:i:s' ),
            'last week'   => $today->modify( 'monday last week' )->setTime( 0, 0 )->format( 'Y-m-d H:i:s' ),
            'this month'  => $today->modify( 'first day of this month' )->setTime( 0, 0 )->format( 'Y-m-d H:i:s' ),
            'last month'  => $today->modify( 'first day of last month' )->setTime( 0, 0 )->format( 'Y-m-d H:i:s' ),
            'this year'   => $today->modify( 'first day of january' )->setTime( 0, 0 )->format( 'Y-m-d H:i:s' ),
            'last year'   => $today->modify( 'first day of january last year' )->setTime( 0, 0 )->format( 'Y-m-d H:i:s' ),
        );
        if ( array_key_exists( $key, $named ) ) { return $named[ $key ]; }

        if ( preg_match( '/^([+-])(\d+)(d|w|m|y)$/', $key, $m ) ) {
            $unit = array( 'd' => 'days', 'w' => 'weeks', 'm' => 'months', 'y' => 'years' )[ $m[3] ];
            $modifier = ( '-' === $m[1] ? '-' : '+' ) . absint( $m[2] ) . ' ' . $unit;
            return $today->modify( $modifier )->format( 'Y-m-d H:i:s' );
        }

        return null;
    }
}
