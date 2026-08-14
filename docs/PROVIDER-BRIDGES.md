# Provider bridge contracts

Provider bridges let an existing CGM plugin retain its own storage and domain logic while exposing capabilities through CGM Core.

## CGM Authors

Preferred filter:

```php
add_filter( 'cgm_authors/core_bridge', function( array $bridge, $core ): array {
    return [
        'post_types'  => [ 'post', 'review' ],
        'get'         => $get_relationships,
        'get_reverse' => $get_reverse_relationships,
        'replace'     => $replace_relationships,
        'search'      => $search_users,

        // Optional guest-author object/query support.
        'get_guests'          => $get_guests,
        'guest_exists'        => $guest_exists,
        'guest_label'         => $guest_label,
        'guest_url'           => $guest_url,
        'guest_edit_url'      => $guest_edit_url,
        'guest_public'        => $guest_public,
        'guest_property'      => $guest_property,
        'guest_search'        => $guest_search,
        'guest_query'         => $guest_query,
        'guest_query_explain' => $guest_query_explain,
    ];
}, 10, 2 );
```

If no bridge is supplied, Core's compatibility adapter reads/writes the existing `_cgm_authors` real-user credit shape while preserving guest records it does not own.

## CGM Game Linker

Core deliberately does not inspect Game Linker's private storage. Game Linker should expose:

```php
add_filter( 'cgm_game_linker/core_bridge', function( array $bridge, $core ): array {
    return [
        'game_post_type' => 'game',
        'post_types'     => [ 'post', 'review' ],
        'roles'          => [ 'related', 'reviewed', 'previewed', 'mentioned', 'interviewed' ],

        // Required provider-storage callbacks.
        'get'         => $get,
        'get_reverse' => $get_reverse,
        'replace'     => $replace,

        // Strongly recommended optimized query callback(s).
        'matching_source_ids' => $matching_source_ids,
        'sql_condition'       => $sql_condition,

        // Optional editor search and permissions.
        'search'            => $search,
        'assign_capability' => 'edit_posts',
    ];
}, 10, 2 );
```

Rows returned by the bridge may contain:

```php
[
    'target_id' => 123,
    'role'      => 'reviewed',
    'primary'   => true,
    'order'     => 0,
    'meta'      => [
        'display' => true,
        'status'  => 'active',
        'notes'   => '',
    ],
]
```

Core passes the complete normalized relationship state back to `replace`; Game Linker remains responsible for mapping it to its canonical production store and any ACF/index synchronization it owns.

## Provider metadata

Providers should register version, capabilities, required dependencies, optional dependencies, and status. Consumers query capabilities through Core rather than checking plugin filenames or classes.
