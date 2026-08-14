# Builder adapter contract

A page builder is a presentation consumer. It should not know how Authors, Game Linker, ACF, Meta Box, or any other provider stores its data.

## Common contract

Every adapter can consume:

```php
cgm_query( $saved_or_inline_query, $context );
cgm_query_ids( $saved_or_inline_query, $context );
cgm_data( $dynamic_path, $object, $context );
cgm_builder_condition( $dynamic_path, $operator, $value, $object, $context );
```

Core also exposes saved-query, dynamic-data, relationship, content-type, and context registries over PHP and authorized REST endpoints.

## Gutenberg

Native Summary controls, Block Bindings, CGM Query Loop, and Dynamic Value blocks.

## Bricks

Native custom query types for saved queries, `{cgm:...}` dynamic tags, and Element Conditions. Bricks remains responsible for element rendering and layout.

## Elementor

CGM Dynamic Data tag plus `cgm_<saved-query-slug>` custom Query IDs for post/media queries.

## Oxygen

Core exposes `cgm_oxygen_data()`, `cgm_oxygen_query()`, and `cgm_oxygen_condition()` plus descriptor/resolver filters. These work with Oxygen's PHP/custom-query/dynamic workflows and form a stable contract for a richer Oxygen-side UI connector without private hooks.

## Divi

Core exposes `cgm_divi_data()`, `cgm_divi_query()`, shortcodes, and adapter descriptors. A future/native Divi connector can register the same sources against a documented third-party Dynamic Content/Loop extension API without changing Core queries.

## Mosaic

Core exposes dynamic-source/query-source descriptor filters plus `cgm_mosaic_data()` and `cgm_mosaic_query()`. This deliberately isolates Mosaic API changes to the adapter layer.

## Adapter rule

If a builder changes its public API, only its adapter changes. Provider plugins and saved CGM queries do not.
