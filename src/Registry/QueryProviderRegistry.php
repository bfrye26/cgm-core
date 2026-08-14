<?php
namespace CGM\Core\Registry;

use CGM\Core\Contracts\QueryProviderInterface;

final class QueryProviderRegistry {
    /** @var array<string,QueryProviderInterface> */
    private array $providers = array();

    public function register( QueryProviderInterface $provider ): void {
        $this->providers[ sanitize_key( $provider->id() ) ] = $provider;
    }

    public function get( string $id ): ?QueryProviderInterface {
        return $this->providers[ sanitize_key( $id ) ] ?? null;
    }

    public function for_content_type( array $content_type ): ?QueryProviderInterface {
        $preferred = sanitize_key( (string) ( $content_type['query_provider'] ?? '' ) );
        if ( $preferred && isset( $this->providers[ $preferred ] ) ) {
            return $this->providers[ $preferred ];
        }
        foreach ( $this->providers as $provider ) {
            if ( $provider->supports( $content_type ) ) {
                return $provider;
            }
        }
        return null;
    }

    public function all(): array { return $this->providers; }
}
