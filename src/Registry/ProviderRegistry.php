<?php
namespace CGM\Core\Registry;

final class ProviderRegistry extends AbstractRegistry {
    private array $compatibility = array();

    public function register( array $definition ): void {
        $definition = wp_parse_args( $definition, array(
            'label'        => '',
            'version'      => '',
            'api'          => '',
            'apis'         => array(),
            'capabilities' => array(),
            'requires'     => array(),
            'optional'     => array(),
            'suggests'     => array(),
            'status'       => 'ready',
            'notes'        => '',
        ) );
        $definition['capabilities'] = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) $definition['capabilities'] ) ) ) );
        $definition['apis'] = is_array( $definition['apis'] ) ? $definition['apis'] : array();
        if ( $definition['api'] && empty( $definition['apis']['core'] ) ) { $definition['apis']['core'] = (string) $definition['api']; }
        foreach ( array( 'requires', 'optional', 'suggests' ) as $key ) {
            $definition[ $key ] = is_array( $definition[ $key ] ) ? $definition[ $key ] : array();
        }
        parent::register( $definition );
    }

    public function supports( string $capability ): bool { return ! empty( $this->providers_for( $capability, true ) ); }

    public function providers_for( string $capability, bool $compatible_only = false ): array {
        $matches = array();
        foreach ( $this->items as $id => $provider ) {
            if ( ! in_array( $capability, (array) ( $provider['capabilities'] ?? array() ), true ) ) { continue; }
            if ( $compatible_only && isset( $this->compatibility[ $id ] ) && empty( $this->compatibility[ $id ]['compatible'] ) ) { continue; }
            $matches[ $id ] = $provider;
        }
        return $matches;
    }

    public function finalize(): void {
        $this->compatibility = array();
        foreach ( $this->items as $id => &$provider ) {
            $required = $this->dependency_set( (array) ( $provider['requires'] ?? array() ) );
            $optional = $this->dependency_set( (array) ( $provider['optional'] ?? array() ) );
            $suggested = $this->dependency_set( (array) ( $provider['suggests'] ?? array() ) );
            $missing = array(); $incompatible = array(); $optional_missing = array(); $suggested_missing = array(); $api_incompatible = array();
            foreach ( (array) ( $provider['apis'] ?? array() ) as $api => $constraint ) {
                $current = $this->api_version( sanitize_key( (string) $api ) );
                if ( '0' === $current || ! $this->matches( $current, (string) $constraint ) ) { $api_incompatible[] = sanitize_key((string)$api) . ' ' . (string)$constraint . ' (current ' . $current . ')'; }
            }
            foreach ( $required as $dependency => $constraint ) {
                $check = $this->check_dependency( $dependency, $constraint );
                if ( 'missing' === $check['status'] ) { $missing[] = $dependency; }
                elseif ( 'incompatible' === $check['status'] ) { $incompatible[] = $dependency . ' ' . $constraint; }
            }
            foreach ( $optional as $dependency => $constraint ) {
                $check = $this->check_dependency( $dependency, $constraint );
                if ( 'ready' !== $check['status'] ) { $optional_missing[] = $dependency; }
            }
            foreach ( $suggested as $dependency => $constraint ) {
                $check = $this->check_dependency( $dependency, $constraint );
                if ( 'ready' !== $check['status'] ) { $suggested_missing[] = $dependency; }
            }
            $api_report = $this->api_report( (array) ( $provider['apis'] ?? array() ) );
            foreach ( $api_report as $api => $check ) {
                if ( empty( $check['compatible'] ) ) { $incompatible[] = $api . '-api ' . (string) $check['required']; }
            }
            $compatible = ! $missing && ! $incompatible && ! $api_incompatible;
            $report = array(
                'compatible'       => $compatible,
                'missing'          => $missing,
                'incompatible'     => $incompatible,
                'optional_missing' => $optional_missing,
                'suggested_missing'=> $suggested_missing,
                'api_incompatible'  => $api_incompatible,
                'apis'             => $api_report,
            );
            $this->compatibility[ $id ] = $report;
            if ( ! $compatible ) { $provider['status'] = 'incompatible'; }
            elseif ( $optional_missing && 'ready' === ( $provider['status'] ?? 'ready' ) ) { $provider['status'] = 'ready-with-optional-missing'; }
            $provider['compatibility'] = $report;
        }
        unset( $provider );
        do_action( 'cgm_core/providers_finalized', $this->compatibility );
    }

    public function compatibility( string $id ): array {
        return $this->compatibility[ sanitize_key( $id ) ] ?? array( 'compatible' => true, 'missing' => array(), 'incompatible' => array(), 'optional_missing' => array(), 'suggested_missing'=>array(), 'api_incompatible'=>array() );
    }

    public function dependency_report(): array {
        if ( ! $this->compatibility ) { $this->finalize(); }
        return $this->compatibility;
    }

    private function api_report( array $requirements ): array {
        $versions = array(
            'core'          => defined( 'CGM_CORE_API_VERSION' ) ? CGM_CORE_API_VERSION : '0',
            'query'         => defined( 'CGM_CORE_QUERY_API_VERSION' ) ? CGM_CORE_QUERY_API_VERSION : '0',
            'relationships' => defined( 'CGM_CORE_RELATIONSHIP_API_VERSION' ) ? CGM_CORE_RELATIONSHIP_API_VERSION : '0',
            'dynamic_data'  => defined( 'CGM_CORE_DYNAMIC_DATA_API_VERSION' ) ? CGM_CORE_DYNAMIC_DATA_API_VERSION : '0',
        );
        $out = array();
        foreach ( $requirements as $api => $constraint ) {
            $api = sanitize_key( (string) $api ); $constraint = trim( (string) $constraint ) ?: '*';
            $current = (string) ( $versions[ $api ] ?? '0' );
            $out[ $api ] = array( 'current'=>$current, 'required'=>$constraint, 'compatible'=>$this->matches( $current, $constraint ) );
        }
        return $out;
    }

    private function api_version( string $api ): string {
        return match ( $api ) {
            'core' => defined('CGM_CORE_API_VERSION') ? (string)CGM_CORE_API_VERSION : '0',
            'query' => defined('CGM_CORE_QUERY_API_VERSION') ? (string)CGM_CORE_QUERY_API_VERSION : '0',
            'relationships', 'relationship' => defined('CGM_CORE_RELATIONSHIP_API_VERSION') ? (string)CGM_CORE_RELATIONSHIP_API_VERSION : '0',
            'dynamic_data', 'dynamic-data' => defined('CGM_CORE_DYNAMIC_DATA_API_VERSION') ? (string)CGM_CORE_DYNAMIC_DATA_API_VERSION : '0',
            default => '0',
        };
    }

    private function dependency_set( array $raw ): array {
        $out = array();
        foreach ( $raw as $key => $value ) {
            if ( is_int( $key ) ) { $out[ sanitize_key( (string) $value ) ] = '*'; }
            else { $out[ sanitize_key( (string) $key ) ] = trim( (string) $value ) ?: '*'; }
        }
        return array_filter( $out, static fn( $v, $k ) => '' !== $k, ARRAY_FILTER_USE_BOTH );
    }

    private function check_dependency( string $id, string $constraint ): array {
        if ( 'cgm-core' === $id || 'core' === $id ) {
            $version = defined( 'CGM_CORE_VERSION' ) ? CGM_CORE_VERSION : '0';
            return array( 'status' => $this->matches( $version, $constraint ) ? 'ready' : 'incompatible', 'version' => $version );
        }
        $provider = $this->items[ $id ] ?? null;
        if ( ! $provider ) { return array( 'status' => 'missing', 'version' => '' ); }
        $version = (string) ( $provider['version'] ?? '' );
        if ( '*' === $constraint || '' === $constraint || '' === $version ) { return array( 'status' => 'ready', 'version' => $version ); }
        return array( 'status' => $this->matches( $version, $constraint ) ? 'ready' : 'incompatible', 'version' => $version );
    }

    private function matches( string $version, string $constraint ): bool {
        $version = preg_replace( '/[^0-9.].*$/', '', $version ) ?: '0';
        $constraint = trim( $constraint );
        if ( '' === $constraint || '*' === $constraint ) { return true; }
        if ( str_starts_with( $constraint, '^' ) ) {
            $min = substr( $constraint, 1 );
            $parts = array_map( 'intval', explode( '.', $min ) );
            $max = ( ( $parts[0] ?? 0 ) + 1 ) . '.0.0';
            return version_compare( $version, $min, '>=' ) && version_compare( $version, $max, '<' );
        }
        if ( str_starts_with( $constraint, '~' ) ) {
            $min = substr( $constraint, 1 ); $parts = array_map( 'intval', explode( '.', $min ) );
            $max = ( $parts[0] ?? 0 ) . '.' . ( ( $parts[1] ?? 0 ) + 1 ) . '.0';
            return version_compare( $version, $min, '>=' ) && version_compare( $version, $max, '<' );
        }
        if ( preg_match( '/^(>=|<=|>|<|=)\s*(.+)$/', $constraint, $m ) ) { return version_compare( $version, $m[2], $m[1] ); }
        return version_compare( $version, $constraint, '>=' );
    }
}
