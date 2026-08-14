# CGM Core 3.0.0

CGM Core is a WordPress-native content interoperability platform for CGMagazine. It does not replace WordPress admin, Gutenberg, ACF, or page builders. It gives them one shared language for content objects, fields, relationships, queries, context, dynamic data, configuration, events, services, permissions, and builder integrations.

## Control room

CGM Core ships a React admin console (the "control room") for the whole CGM suite:

- **Overview** — cross-suite health, a live event/activity feed, query performance (executions, avg ms, cache-hit rate), and builder/provider status.
- **Setup / Diagnostics** — discovery, provider compatibility, builder adapters, and search-index definitions.
- **Queries** (Smart Collections) — a visual nested AND/OR query builder with live preview and explain; save once, then reuse in Gutenberg, Bricks, Elementor, shortcodes, or CSV/JSON export.
- **Content / Data** — the content/field registry, a browsable dynamic-data token library with copy-paste snippets (`{cgm:…}`, `[cgm_data key="…"]`), and a live token preview.
- **Relationships** — a model editor and an **integrity** audit (orphaned references, cardinality violations) with dry-run/confirm repair.
- **Reports** — COUNT/SUM/AVG/MIN/MAX aggregation grouped by field, taxonomy, or relationship.
- **Automation** — event → conditions → actions rules (Drupal-Rules style): reindex search, purge caches, set meta/term/status, link relationships, email, call webhooks, or schedule deferred events.
- **Bulk operations** — run an action against every object in a saved query or content type (preview first).
- **Workflow** — registrable editorial states (draft / in-review / published / archived) with transitions, queryable + contextual.
- **Search** — a unified search facade with faceted filtering, backed by the native engine or Typesense.
- **Graph** — a relationship graph (objects ↔ relationships) with a visualizer.
- **Configuration** — versioned export/diff/import/rollback, with a promote (download → import) flow.
- **Inspector** — the normalized Core view of any object.

## Core concepts

- **Provider and capability registry** with required, optional, and suggested dependencies plus API/version compatibility reporting. The full CGM suite (SEO, Tag Manager, Image Renamer, Homepage Manager, Feed Manager, Editorial Intelligence, Scheduled Revisions, Relationship Suite, Typesense search, and others) is auto-detected and surfaced here, alongside non-CGM plugins (WooCommerce, Yoast SEO).
- **Universal content/object model** for WordPress posts, custom post types, media, users, terms, and provider-defined object types.
- **Field registry** for native WordPress fields/meta, ACF, Meta Box, user meta, term meta, and provider-defined fields.
- **Relationship platform** with forward/reverse references, cardinality, ordering, primary records, roles, typed relationship metadata, permissions, visibility, provider-owned storage, and Core-owned storage.
- **Drupal Views-style query platform** with nested AND/OR groups, fields, taxonomies, relationships, typed operators, multiple sorts, contextual values, pagination, query providers, SQL planning for WordPress entities, reusable saved queries, code-defined queries, preview, explain output, and **aggregation**.
- **Views displays and exposed filters** — a saved query can render as a list, block, or REST endpoint, and `[cgm_view id="slug" filters="1"]` renders a user-facing filter form.
- **Context engine** including current post/user/author/term/query item and provider/relationship-derived contexts.
- **Typed dynamic data** with multi-hop traversal through objects, fields, and relationships, plus optional fallbacks and formatters.
- **Automation rules** — event → conditions → actions on the event bus, with built-in actions and a `cgm_register_rule_action()` extension point.
- **Search-index definitions and facets** — plugins register indexes via `cgm_register_index()` and facets via `cgm_register_facet()`; Core fans content/relationship changes out to `index.rebuild` events and provides a unified search facade.
- **Workflow states** — registrable editorial states via `cgm_register_workflow_state()`, queryable and contextual.
- **View modes** — named presentations via `cgm_register_view_mode()`, rendered by `[cgm_object]`.
- **Capability mapping** — granular capabilities (`manage_cgm_*`, `inspect_cgm_*`) gate the control room, REST write routes (nonce + capability), per-object workflow transitions, and query/relationship visibility.
- **Pathauto, locale and notifications** — `cgm_pathauto()`, `cgm_locale()`, and `cgm_notify()` contracts.
- **Bulk operations** — run rule actions against a query's result set.
- **Configuration management** with export, validation, diff, dry run, merge/replace, backups, rollback, interrupted-import recovery, code-managed configuration, and optional multisite network defaults.
- **Event and service contracts** so plugins communicate through Core instead of hard dependencies.
- **Dependency-aware caching** using cache namespaces and dependency tags.
- **REST, WP-CLI, Site Health, diagnostics, and object/relationship/query inspection**.

## Editor and builder integrations

### Gutenberg

Gutenberg is the first-class editor integration. CGM controls use native WordPress components and the native Post Summary extension area. Relationship controls support search, add/remove, ordering, primary selection, roles, and relationship metadata. Core also provides Block Bindings, a Dynamic Value block, and a CGM Query Loop block that renders ordinary nested Gutenberg blocks.

### Bricks

Bricks receives saved CGM Query Loop types, CGM dynamic-data tags, context, and Element Conditions. Core remains the query/data source while Bricks remains responsible for presentation.

### Elementor

Elementor receives a CGM Dynamic Data tag and CGM saved-query Query IDs through its public extension APIs.

### Oxygen, Divi, and Mosaic

Core ships builder adapters and stable builder-neutral query/data/condition contracts, plus shortcodes and REST/PHP access. Where a builder does not publish a stable third-party UI registration contract for a feature, Core deliberately does not depend on undocumented/private hooks. Builder-specific connector plugins can surface the same Core registries without changing content or query definitions.

## Existing CGM plugins

CGM Relationship Suite (the successor to Game Linker) and CGM Authors remain owners of their production data. Core provides bridge contracts so they can expose data without migration. Authors has a compatibility adapter for existing `_cgm_authors` storage. Relationship Suite exposes its storage through the `cgm_game_linker/core_bridge` filter; Core intentionally does not guess private storage. The rest of the CGM suite is auto-detected as providers so the control room shows the whole newsroom stack.

## Public PHP API

Common entry points include:

```php
cgm_register_provider( $definition );
cgm_register_content_type( $definition );
cgm_register_field( $definition );
cgm_register_relationship_type( $definition );
cgm_register_relationship_store( $id, $store );
cgm_register_dynamic_data( $definition );
cgm_register_saved_query( $id, $definition, $args );
cgm_register_relationship_definition( $definition );
cgm_register_context( $id, $label, $resolver );
cgm_register_query_provider( $provider );
cgm_register_index( $definition );
cgm_register_rule_action( $id, $label, $callback );

$result = cgm_query( 'related-game-coverage' );
$value  = cgm_data( 'relationship.game.primary.label' );
$rels   = cgm_relationships()->get( 'game', get_the_ID() );
$report = cgm_core()->queries()->aggregate( array( 'content_type' => 'post', 'aggregate' => array( 'group_by' => 'taxonomy.category' ) ) );
```

## Requirements

- WordPress 6.7+
- PHP 8.1+

## Safety

Core-owned data is preserved on uninstall by default. Permanent deletion requires explicitly defining `CGM_CORE_REMOVE_DATA_ON_UNINSTALL` as `true` before uninstalling.
