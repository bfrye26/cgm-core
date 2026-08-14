# CGM Query format

CGM Query is a builder-neutral AST. The same definition can be saved in WordPress, registered in code, called through PHP/REST, or consumed by a builder adapter.

```php
[
    'content_type' => 'post',
    'status'       => [ 'publish' ],
    'search'       => '',
    'filters'      => [
        'relation' => 'AND',
        'rules'    => [
            [
                'type'         => 'relationship',
                'relationship' => 'game',
                'operator'     => 'IN',
                'value'        => '@current_game',
            ],
            [
                'relation' => 'OR',
                'rules'    => [
                    [ 'type'=>'taxonomy', 'taxonomy'=>'category', 'operator'=>'IN', 'value'=>[ 12 ] ],
                    [ 'type'=>'field', 'field'=>'review_score', 'operator'=>'>=', 'value'=>80 ],
                ],
            ],
        ],
    ],
    'sort' => [
        [ 'field'=>'review_score', 'direction'=>'DESC' ],
        [ 'field'=>'post.date', 'direction'=>'DESC' ],
    ],
    'limit'     => 12,
    'page'      => 1,
    'offset'    => 0,
    'cache'     => true,
    'cache_ttl' => 120,
]
```

## Query provider model

The `content_type` selects a registered query provider. Core ships bounded providers for posts/media, users and terms. Plugins can register additional providers for non-WordPress objects.

## Context tokens

Values may use tokens such as `@current_post`, `@current_parent`, `@current_author`, `@current_user`, `@current_term` and `@current_query_item`, plus provider-registered context keys. URL/query-var context is resolved only through the explicit context contract rather than executing arbitrary PHP.

## Query explain

`QueryEngine::explain()` resolves the saved query, contexts, query provider, dependencies and provider-specific plan. The admin Query Builder and REST/CLI tools use the same explain path.

## Code-managed query

```php
cgm_register_saved_query( 'related_game_coverage', $definition, [
    'title'  => 'Related Game Coverage',
    'public' => true,
] );
```

Code-managed definitions are visible but not overwritten by configuration replacement.
