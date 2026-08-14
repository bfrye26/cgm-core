# Provider SDK

A provider teaches CGM Core about data or behavior it does not own. Providers should register capabilities once and expose data through Core contracts instead of making consuming plugins call provider-specific functions.

## Minimal provider

```php
add_action( 'cgm_core/register', function( $core ) {
    cgm_register_provider( [
        'id'           => 'my-provider',
        'label'        => 'My Provider',
        'version'      => '1.4.0',
        'apis'         => [
            'core'          => '^2.0',
            'query'         => '^2.0',
            'relationships' => '^2.0',
            'dynamic_data'  => '^2.0',
        ],
        'capabilities' => [ 'content.product', 'relationships.product' ],
        'requires'     => [ 'cgm-core' => '>=3.0.0' ],
        'optional'     => [ 'acf' => '*' ],
        'suggests'     => [ 'bricks' => '*' ],
    ] );
} );
```

Providers can also implement `CGM\Core\Contracts\ProviderInterface` and register through `cgm_register_provider_object()`.

## Content and objects

Register a content type with `cgm_register_content_type()`. WordPress post types, users, media and terms already have native adapters. Non-WordPress objects should register an `ObjectAdapterInterface` implementation so Core can resolve existence, labels, URLs, visibility, properties and search.

## Fields

Register typed field definitions with `cgm_register_field()`. Important definition flags include `queryable`, `filterable`, `sortable`, `dynamic`, `editable`, `public`, `rest`, `operators` and `content_types`.

## Relationships

Use `cgm_register_relationship_type()` for the schema. A provider may use Core storage or register its own `RelationshipStoreInterface` with `cgm_register_relationship_store()`. Queryable provider storage should implement `QueryableRelationshipStoreInterface` so the query planner can keep filtering in the database/provider instead of collecting unbounded ID lists in PHP.

## Query providers

Use `cgm_register_query_provider()` for non-standard content. A query provider receives the normalized query AST and context and returns a `QueryResult`. Providers should implement bounded pagination and provide `explain()` output.

## Dynamic data

Use `cgm_register_dynamic_data()` for explicit computed values. Core traversal can also follow registered relationships and fields, so providers do not need to register every possible path combination.

## Context

Use `cgm_register_context()` for reusable context tokens such as a provider-defined Current Product or Current Company. Relationship-derived contexts can resolve a registered relationship from the current object.

## Events and services

Use `cgm_register_event_contract()` for versioned events and `cgm_event()` to dispatch them. Use `cgm_listen()` to listen without direct plugin dependencies.

Use `cgm_register_service()` to expose callable/object services with a version. Consumers use `cgm_service( 'service-id', '^1.0' )`, which returns `null` when the required contract is unavailable.

## Data ownership rule

A provider remains the owner of its domain data. Core stores only data explicitly assigned to a Core-owned relationship/configuration store. Existing provider data should be exposed through adapters rather than copied into Core.
