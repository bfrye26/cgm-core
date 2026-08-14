<?php
namespace CGM\Core\Integrations\Elementor;

if ( class_exists( '\\Elementor\\Core\\DynamicTags\\Data_Tag' ) ) {
    final class CGMDynamicTag extends \Elementor\Core\DynamicTags\Data_Tag {
        public function get_name(): string { return 'cgm-core-dynamic-data'; }
        public function get_title(): string { return __( 'CGM Dynamic Data', 'cgm-core' ); }
        public function get_group(): string { return 'cgm-core'; }
        public function get_categories(): array {
            if ( class_exists( '\\Elementor\\Modules\\DynamicTags\\Module' ) ) {
                return array(
                    \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
                    \Elementor\Modules\DynamicTags\Module::URL_CATEGORY,
                    \Elementor\Modules\DynamicTags\Module::NUMBER_CATEGORY,
                );
            }
            return array( 'text','url','number' );
        }

        protected function register_controls(): void {
            $options = array();
            foreach ( cgm_core()->dynamic_data()->all() as $id => $definition ) {
                $options[ $id ] = (string) ( $definition['group'] ?? 'CGM' ) . ' — ' . (string) ( $definition['label'] ?? $id );
            }
            $this->add_control( 'cgm_key', array(
                'label'       => __( 'CGM data', 'cgm-core' ),
                'type'        => \Elementor\Controls_Manager::SELECT2,
                'options'     => $options,
                'description' => __( 'Choose a registered value, or enter a relationship traversal path below.', 'cgm-core' ),
            ) );
            $this->add_control( 'cgm_path', array(
                'label'       => __( 'Traversal path', 'cgm-core' ),
                'type'        => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'game.primary.developer.name',
                'description' => __( 'Optional. Overrides the selected value and supports CGM relationship traversal.', 'cgm-core' ),
            ) );
        }

        public function get_value( array $options = array() ): mixed {
            $path = trim( (string) $this->get_settings( 'cgm_path' ) );
            $key  = $path ?: (string) $this->get_settings( 'cgm_key' );
            if ( ! $key ) { return ''; }
            $object = $GLOBALS['cgm_core_query_object'] ?? get_the_ID();
            return cgm_data( $key, $object );
        }
    }
}
