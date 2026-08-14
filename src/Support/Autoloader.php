<?php
namespace CGM\Core\Support;

final class Autoloader {
    public static function register(): void {
        spl_autoload_register( array( self::class, 'autoload' ) );
    }
    public static function autoload( string $class ): void {
        $prefix = 'CGM\\Core\\';
        if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) { return; }
        $relative = substr( $class, strlen( $prefix ) );
        $path = CGM_CORE_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
        if ( is_readable( $path ) ) { require_once $path; }
    }
}
