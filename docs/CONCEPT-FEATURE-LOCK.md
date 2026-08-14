# CGM Core 3.0 concept feature lock

`3.0.0-alpha.4.4` is the feature-lock implementation of the new CGM Core concept. The purpose of Core is to make WordPress, Gutenberg, page builders, custom data providers and CGM feature plugins operate against a shared content/query/relationship contract without moving ownership of domain data into Core.

## Feature-lock matrix

| Concept | Core implementation |
|---|---|
| Plugin/provider discovery | `ProviderRegistry`, `ProviderInterface`, capability/dependency/API declarations |
| Required/optional/suggested dependencies | `ProviderRegistry::finalize()` compatibility reports |
| API versioning/deprecation | Core/Query/Relationship/Dynamic Data API constants, `ApiCompatibility`, `VersionConstraint`, `cgm_deprecated()` |
| Content/entity registry | `ContentTypeRegistry`, `ObjectReference`, `ObjectResolver`, object adapters |
| WordPress entities | Posts/custom post types, media, users and taxonomy-specific term object types |
| Provider entities | `CallbackObjectAdapter` plus provider query/content registration |
| Field registry | `FieldRegistry`, WordPress registered meta, ACF, Meta Box and provider fields |
| Relationship definitions | `RelationshipSchema`, configured/code/provider definitions |
| Relationship storage | Core table plus provider-owned `RelationshipStoreInterface` adapters |
| Relationship semantics | Forward/reverse, cardinality, primary, order, roles, typed metadata, max items, permissions, visibility and delete policy |
| Relationship integrity | `RelationshipLifecycle` detachment/restriction/cascade event contract |
| Query platform | `QueryEngine`, normalized AST, query provider registry and query plans |
| Query providers | Post/media, user, term and callback query providers |
| Drupal Views-style builder | Native WordPress visual nested AND/OR query builder with typed operators, sort, context, test and explain |
| Saved queries | Revisioned database definitions, cloning, usage tracking and code-managed definitions |
| Contextual filters | Current post/parent/user/author/term/query item, URL/query vars and provider/relationship contexts |
| Dynamic data | Typed registry plus traversal through objects, fields and relationships |
| Plugin communication | Versioned `EventBus` and versioned `ServiceRegistry` |
| Caching | Dependency/tag-aware cache epochs and invalidation |
| Configuration management | Versioned export, validation, diff, dry run, merge/replace, backup, rollback, recovery and network defaults |
| Permissions | Capability enforcement across workflow transitions, query visibility, dynamic data and relationship reads |
| REST | Versioned registry, query, object, relationship, dynamic data, editor and configuration controllers |
| Gutenberg | Native Post Summary controls, relationship editing, Block Bindings, Query Loop and Dynamic Value blocks |
| Bricks | Custom saved query types, inline query contract, dynamic tags, loop objects and Element Conditions |
| Elementor | Dynamic Tag and saved CGM Query IDs through Elementor public extension hooks |
| Oxygen | Stable Core dynamic/query/condition bridge, PHP helpers and shortcodes; no private Oxygen internals |
| Divi | Stable Core data/query/condition bridge, PHP helpers and shortcodes; no private Divi internals |
| Mosaic | Stable Core dynamic/query/condition source bridge, PHP helpers and shortcodes; no private Mosaic internals |
| Diagnostics | Content/Field explorer, Relationship screen, Content Inspector, provider/API/builder diagnostics |
| Site Health | Registry/provider/query/relationship/builder health checks |
| WP-CLI | Registry, queries, objects, relationships, config and health commands |
| Multisite | Local-first policy, optional network defaults and explicit cross-site provider opt-in |
| Existing CGM data | CGM Authors adapter plus formal Game Linker provider bridge without data duplication |
| Graceful extension | Feature plugins own their storage and can expose it through provider/store/object/query contracts |

## What feature complete means

Feature complete means the **Core-side architecture and runtime contracts are present**. It does not mean every independent CGM plugin has already shipped the companion code needed to consume those contracts, or that Core should hook undocumented private APIs in third-party builders.

The most important example is Game Linker. Core contains the complete Game Linker bridge contract and refuses to guess or duplicate Game Linker's private storage. A matching Game Linker release must expose that bridge before Core can safely mutate production Game Linker data.

Likewise, Bricks and Elementor receive direct native integrations because stable public extension hooks are available. Oxygen, Divi and Mosaic receive stable Core-side adapters and bridge contracts where a complete public registration surface has not been verified. This keeps the architecture feature-complete without coupling CGM Core to private builder internals.

## Beta boundary

After this release, beta work should be limited to:

- real WordPress/staging acceptance;
- provider companion-plugin rollout;
- performance profiling and query-plan tuning;
- builder compatibility fixes;
- accessibility and keyboard/focus work;
- native WordPress visual polish;
- migration/upgrade testing;
- documentation/examples;
- bug fixes and backwards-compatibility shims.

A new major subsystem after feature lock should require evidence that staging exposed a missing architectural capability rather than a UX or implementation defect.
