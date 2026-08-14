<?php
namespace CGM\Core\Providers\Typesense;

use CGM\Core\Contracts\ProviderInterface;
use CGM\Core\Plugin;

/**
 * Registers the Typesense search index with Core's index layer so `index.rebuild`
 * events fan out to it and the control room lists it. The provider itself is
 * registered by CGMSuiteMembersProvider; this only adds the index definition.
 */
final class TypesenseProvider implements ProviderInterface {
    public function id(): string { return 'search-with-typesense'; }

    public function register( Plugin $core ): void {
        if ( ! defined( 'CODEMANAS_TYPESENSE_VERSION' ) ) { return; }
        $core->indexes()->register( array(
            'id' => 'typesense', 'label' => 'Typesense', 'content_types' => array( '*' ), 'provider' => 'search-with-typesense',
        ) );
        if ( class_exists( 'Codemanas\\Typesense\\Services\\SearchProvider' ) ) {
            $core->search()->register_provider( new TypesenseSearchProvider( $core ) );
        }
    }
}
