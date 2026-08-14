<?php
namespace CGM\Core\UI;

/**
 * Minimal compatibility facade for PHP-only consumers of the retired CGMUI contract.
 * It deliberately does not claim that the old JavaScript component runtime is present.
 */
final class UIContract {
    public static function components(): array {
        return array(
            'module_root' => array( 'class' => 'cgm-ui-module-root' ),
            'page'        => array( 'class' => 'cgm-ui-page' ),
            'main_column' => array( 'class' => 'cgm-ui-main-column' ),
            'main_rail'   => array( 'class' => 'cgm-ui-main-rail cgm-ui-right-rail' ),
        );
    }

    public static function module_root_attrs( $module_id, $extra_attrs = array() ): string {
        $module_id = function_exists( 'sanitize_key' ) ? sanitize_key( (string) $module_id ) : preg_replace( '/[^a-z0-9_-]/i', '', (string) $module_id );
        $extra_attrs = is_array( $extra_attrs ) ? $extra_attrs : array();
        $class = trim( 'cgm-ui-module-root cgm-ui-page cgm-module--' . $module_id . ' ' . (string) ( $extra_attrs['class'] ?? '' ) );
        unset( $extra_attrs['class'] );

        $attrs = array(
            'class'                => $class,
            'data-cgm-module-id'   => $module_id,
            'data-cgm-module'      => $module_id,
            'data-cgm-ui-contract' => defined( 'CGM_CORE_UI_CONTRACT_VERSION' ) ? CGM_CORE_UI_CONTRACT_VERSION : '0.0.0',
            'data-cgm-ui-compat'   => 'fallback',
        );
        $attrs = array_merge( $attrs, $extra_attrs );

        $escape = static function ( $value ): string {
            return function_exists( 'esc_attr' ) ? esc_attr( (string) $value ) : htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
        };
        $out = array();
        foreach ( $attrs as $key => $value ) {
            if ( null === $value || false === $value ) { continue; }
            $key = preg_replace( '/[^a-zA-Z0-9:_-]/', '', (string) $key );
            if ( true === $value ) { $out[] = $key; continue; }
            $out[] = $key . '="' . $escape( $value ) . '"';
        }
        return implode( ' ', $out );
    }

    public static function module_report_row( $module_id, $definition = array() ): array {
        return array(
            'id'      => (string) $module_id,
            'score'   => 0,
            'status'  => 'compatibility-fallback',
            'message' => 'CGMUI 1.x is not supplied by CGM Core 3.',
        );
    }

    public static function rest_data(): array {
        return array(
            'available'       => false,
            'compatibility'   => true,
            'contractVersion' => defined( 'CGM_CORE_UI_CONTRACT_VERSION' ) ? CGM_CORE_UI_CONTRACT_VERSION : '0.0.0',
            'components'      => self::components(),
        );
    }

    public static function legacy_component_aliases(): array { return array(); }
    public static function legacy_bridge_selectors(): array { return array(); }
}
