<?php
namespace CGM\Core\Registry;

final class FieldRegistry extends AbstractRegistry {
    public function register( array $definition ): void {
        $definition = wp_parse_args( $definition, array(
            'label'         => '',
            'type'          => 'string',
            'provider'      => 'core',
            'source'        => '',
            'queryable'     => false,
            'sortable'      => false,
            'filterable'    => true,
            'editable'      => false,
            'public'        => true,
            'rest'          => false,
            'dynamic'       => false,
            'content_types' => array( '*' ),
            'operators'     => array( '=', '!=' ),
        ) );
        $definition['content_types'] = array_values( array_unique( array_filter( array_map( 'sanitize_key', (array) $definition['content_types'] ) ) ) );
        $definition['operators'] = array_values( array_unique( array_map( 'strtoupper', (array) $definition['operators'] ) ) );
        parent::register( $definition );
    }

    public function for_content_type( string $content_type, bool $queryable_only = false ): array {
        $content_type = sanitize_key( $content_type );
        return array_filter( $this->items, static function( array $field ) use ( $content_type, $queryable_only ): bool {
            if ( $queryable_only && empty( $field['queryable'] ) ) { return false; }
            $types = (array) ( $field['content_types'] ?? array( '*' ) );
            return in_array( '*', $types, true ) || in_array( $content_type, $types, true );
        } );
    }

    public function operators_for( string $id ): array {
        $field = $this->get( $id );
        return $field ? (array) ( $field['operators'] ?? array( '=', '!=' ) ) : array();
    }
}
