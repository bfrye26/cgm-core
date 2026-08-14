<?php
namespace CGM\Core\Search;

use CGM\Core\Plugin;

/** Holds registered search backends and delegates to the active one (or the native fallback). */
final class SearchManager {
    /** @var array<string,SearchProviderInterface> */
    private array $providers = array();
    private ?NativeSearchProvider $native = null;

    public function __construct( private Plugin $core ) {}

    public function register_provider( SearchProviderInterface $provider ): void { $this->providers[ $provider->id() ] = $provider; }

    public function provider(): SearchProviderInterface {
        if ( $this->providers ) { return reset( $this->providers ); }
        return $this->native ??= new NativeSearchProvider( $this->core );
    }

    public function search( string $query, array $args = array() ): array { return $this->provider()->search( $query, $args ); }
    public function facets( string $query, array $args = array() ): array { return $this->provider()->facets( $query, $args ); }
}
