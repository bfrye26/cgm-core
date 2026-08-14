<?php
namespace CGM\Core\Support;

use CGM\Core\Plugin;

/**
 * Pathauto-style alias builder: expand a token pattern into a URL-safe slug.
 * Tokens use the dynamic-data engine (e.g. "[game.primary.title]-[post.date]").
 */
final class Pathauto {
    public function __construct( private Plugin $core ) {}

    public function build( string $pattern, mixed $object = null ): string {
        $slug = preg_replace_callback(
            '/\[([a-zA-Z0-9._:>-]+)\]/',
            function ( $m ) use ( $object ) {
                $value = $this->core->dynamic_data()->resolve( $m[1], $object );
                return is_scalar( $value ) || null === $value ? (string) $value : '';
            },
            $pattern
        ) ?? $pattern;
        $slug = sanitize_title( $slug );
        // Callers are responsible for uniqueness; this hook lets them resolve
        // collisions (suffix with a counter, etc.) without Core guessing intent.
        return apply_filters( 'cgm_core/pathauto_uniquify', $slug, $pattern, $object );
    }
}
