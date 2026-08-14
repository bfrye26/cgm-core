<?php
/**
 * Compatibility facade for CGM Suite plugins built against CGM Core 1.x/2.x.
 *
 * Core 3 intentionally does not restore the retired CGMUI application shell. These
 * functions accept historical call shapes, record registrations for diagnostics and
 * fail closed for UI mounting so an older module can show its own fallback rather
 * than crash WordPress with a TypeError or undefined-function fatal.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'cgm_core_legacy_normalize_registration' ) ) {
    function cgm_core_legacy_normalize_registration( array $args ): array {
        $id = '';
        $definition = array();

        if ( isset( $args[0] ) && is_string( $args[0] ) ) {
            $id = sanitize_key( $args[0] );
            if ( isset( $args[1] ) && is_array( $args[1] ) ) {
                $definition = $args[1];
            }
        } elseif ( isset( $args[0] ) && is_array( $args[0] ) ) {
            $definition = $args[0];
            $id = sanitize_key( (string) ( $definition['id'] ?? $definition['module_id'] ?? $definition['slug'] ?? '' ) );
        }

        return array( $id, $definition );
    }
}

if ( ! function_exists( 'cgm_core_register_module' ) ) {
    function cgm_core_register_module( ...$args ): bool {
        [ $id, $definition ] = cgm_core_legacy_normalize_registration( $args );
        if ( '' === $id ) {
            do_action( 'cgm_core/legacy_registration_invalid', 'module', $args );
            return false;
        }

        try {
            cgm_register_provider( array(
                'id'           => $id,
                'label'        => sanitize_text_field( (string) ( $definition['label'] ?? $definition['name'] ?? $id ) ),
                'version'      => sanitize_text_field( (string) ( $definition['version'] ?? '' ) ),
                'capabilities' => array( 'legacy.module' ),
                'status'       => 'legacy-registration',
                'notes'        => 'Registered through the Core 3 legacy Suite compatibility facade.',
            ) );
        } catch ( Throwable $e ) {
            // A duplicate/provider timing problem must never make a compatibility call fatal.
            do_action( 'cgm_core/legacy_registration_error', 'module', $id, $e );
        }

        do_action( 'cgm_core/legacy_module_registered', $id, $definition );
        return true;
    }
}

if ( ! function_exists( 'cgm_core_register_module_contract' ) ) {
    function cgm_core_register_module_contract( ...$args ): bool {
        [ $id, $definition ] = cgm_core_legacy_normalize_registration( $args );
        do_action( 'cgm_core/legacy_module_contract_registered', $id, $definition, $args );
        return true;
    }
}

if ( ! function_exists( 'cgm_core_register_admin_app' ) ) {
    function cgm_core_register_admin_app( ...$args ): bool {
        [ $id, $definition ] = cgm_core_legacy_normalize_registration( $args );
        do_action( 'cgm_core/legacy_admin_app_registered', $id, $definition, $args );
        // Core 3 does not pretend the retired CGMUI runtime exists. Returning false
        // lets well-behaved Suite modules use their dependency/fallback path.
        return false;
    }
}

if ( ! function_exists( 'cgm_core_register_settings_section' ) ) {
    function cgm_core_register_settings_section( ...$args ): bool {
        do_action( 'cgm_core/legacy_settings_section', $args );
        return true;
    }
}

if ( ! function_exists( 'cgm_core_register_setting' ) ) {
    function cgm_core_register_setting( ...$args ): bool {
        do_action( 'cgm_core/legacy_setting', $args );
        return true;
    }
}

if ( ! function_exists( 'cgm_core_ui_contract_version' ) ) {
    function cgm_core_ui_contract_version(): string {
        return (string) CGM_CORE_UI_CONTRACT_VERSION;
    }
}

if ( ! function_exists( 'cgm_core_ui_runtime_data' ) ) {
    function cgm_core_ui_runtime_data(): array {
        return array(
            'available'       => false,
            'compatibility'   => true,
            'coreVersion'     => (string) CGM_CORE_VERSION,
            'contractVersion' => (string) CGM_CORE_UI_CONTRACT_VERSION,
            'message'         => 'The retired CGMUI 1.x runtime is not provided by CGM Core 3. Migrate this module to the Core 3 provider/builder APIs.',
        );
    }
}

if ( ! function_exists( 'cgm_core_ui_available' ) ) {
    function cgm_core_ui_available( $min_version = '' ): bool {
        return false;
    }
}

if ( ! function_exists( 'cgm_core_register_ui_assets' ) ) {
    function cgm_core_register_ui_assets(): bool {
        if ( function_exists( 'wp_register_style' ) ) {
            if ( ! wp_style_is( 'cgm-core-ui-tokens', 'registered' ) ) {
                wp_register_style( 'cgm-core-ui-tokens', false, array(), CGM_CORE_VERSION );
            }
            if ( ! wp_style_is( 'cgm-core-ui', 'registered' ) ) {
                wp_register_style( 'cgm-core-ui', false, array( 'cgm-core-ui-tokens' ), CGM_CORE_VERSION );
            }
            if ( ! wp_style_is( 'cgm-core-ui-contract', 'registered' ) ) {
                wp_register_style( 'cgm-core-ui-contract', false, array( 'cgm-core-ui' ), CGM_CORE_VERSION );
            }
        }
        if ( function_exists( 'wp_register_script' ) ) {
            if ( ! wp_script_is( 'cgm-core-icons', 'registered' ) ) {
                wp_register_script( 'cgm-core-icons', false, array(), CGM_CORE_VERSION, true );
            }
            if ( ! wp_script_is( 'cgm-core-ui', 'registered' ) ) {
                wp_register_script( 'cgm-core-ui', false, array( 'wp-element' ), CGM_CORE_VERSION, true );
                if ( function_exists( 'wp_add_inline_script' ) ) {
                    wp_add_inline_script(
                        'cgm-core-ui',
                        'window.CGMUI=window.CGMUI||{};window.cgmCoreIcons=window.cgmCoreIcons||{};window.cgmCoreUIData=' . wp_json_encode( cgm_core_ui_runtime_data() ) . ';',
                        'before'
                    );
                }
            }
            if ( ! wp_script_is( 'cgm-core-ui-qa', 'registered' ) ) {
                wp_register_script( 'cgm-core-ui-qa', false, array( 'cgm-core-ui' ), CGM_CORE_VERSION, true );
            }
        }
        return true;
    }
}

if ( ! function_exists( 'cgm_core_enqueue_ui' ) ) {
    /**
     * Historical signature: cgm_core_enqueue_ui( $module_id, array $args = [] ).
     * Some earlier builds also passed only the options array, so both are accepted.
     */
    function cgm_core_enqueue_ui( $module_id = '', $args = array() ): bool {
        if ( is_array( $module_id ) && ( empty( $args ) || ! is_array( $args ) ) ) {
            $args = $module_id;
            $module_id = (string) ( $args['module_id'] ?? $args['id'] ?? '' );
        } elseif ( is_array( $module_id ) && is_array( $args ) && empty( $args ) ) {
            $args = $module_id;
            $module_id = (string) ( $args['module_id'] ?? $args['id'] ?? '' );
        }

        $module_id = sanitize_key( is_scalar( $module_id ) ? (string) $module_id : '' );
        $args = is_array( $args ) ? $args : array();
        cgm_core_register_ui_assets();
        do_action( 'cgm_core/legacy_ui_requested', $module_id, $args );
        return false;
    }
}

// Register compatibility handles early enough for legacy front-end styles that still
// declare cgm-core-ui-tokens as a dependency. This does not enqueue the retired UI.
add_action( 'init', 'cgm_core_register_ui_assets', 1 );

/* ---------------------------------------------------------------------
 * Core 3 capability/log/cache/queue/service facades.
 *
 * Older suite plugins call these under function_exists() guards. Core 3 dropped
 * the CGMUI helpers, so these shims map the historical call shapes onto the new
 * provider/query/relationship contracts so the plugins keep working instead of
 * silently no-oping.
 * ------------------------------------------------------------------- */

if ( ! function_exists( 'cgm_core_current_user_can' ) ) {
    function cgm_core_current_user_can( $cap ) {
        // Granular capability only; the 1.x manage_options blanket bypass is retired.
        return current_user_can( $cap );
    }
}

if ( ! function_exists( 'cgm_core_log' ) ) {
    function cgm_core_log( array $entry ) {
        if ( ! function_exists( 'cgm_core' ) ) { return; }
        $core = cgm_core();
        if ( is_object( $core ) && method_exists( $core, 'events' ) ) {
            $core->events()->dispatch( 'activity.logged', $entry );
        }
    }
}

if ( ! function_exists( 'cgm_core_get_service' ) ) {
    function cgm_core_get_service( $id ) {
        if ( ! function_exists( 'cgm_core' ) ) { return null; }
        return cgm_core()->services()->require( sanitize_key( (string) $id ), '*' );
    }
}

if ( ! function_exists( 'cgm_core_get_setting' ) ) {
    function cgm_core_get_setting( $key ) {
        $key = sanitize_key( (string) $key );
        return apply_filters( 'cgm_core/setting', get_option( 'cgm_core_' . $key ), $key );
    }
}

if ( ! function_exists( 'cgm_core_cache' ) ) {
    function cgm_core_cache() {
        if ( ! function_exists( 'cgm_core' ) ) { return null; }
        return new class( cgm_core()->cache() ) {
            private $cache;
            public function __construct( $cache ) { $this->cache = $cache; }
            public function get( $key, $group = 'default' ) { return $this->cache->get( (string) $key, sanitize_key( (string) $group ) ); }
            public function set( $key, $value, $group = 'default', $expire = 300 ) { return $this->cache->set( (string) $key, $value, max( 1, (int) $expire ), sanitize_key( (string) $group ) ); }
            public function delete( $key, $group = 'default' ) { return $this->cache->delete( (string) $key, sanitize_key( (string) $group ) ); }
        };
    }
}

if ( ! function_exists( 'cgm_core_queue' ) ) {
    function cgm_core_queue() {
        return new class {
            public function cancel( $hook, $args = array() ) {
                if ( function_exists( 'as_unschedule_all_actions' ) ) { as_unschedule_all_actions( sanitize_key( (string) $hook ), (array) $args ); }
                return true;
            }
        };
    }
}

// Fire the legacy load signal so plugins that gate on did_action() see Core as ready.
// Note: `cgm_core_register_modules` is intentionally NOT fired — old modules (e.g. the
// Relationship Suite importer) hook it expecting a module registry object, which Core 3
// no longer provides; firing it empty causes `Call to a member function register() on string`.
add_action( 'plugins_loaded', static function () {
    do_action( 'cgm_core_loaded' );
}, 100 );
