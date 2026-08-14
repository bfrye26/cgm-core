# CGM Core 3.0.0-alpha.4.4 feature-lock roadmap

Alpha 3 closes the major concept-phase architecture. The feature set is now intended to be locked.

## Implemented concept areas

- Provider/capability/dependency registry and API compatibility.
- Universal content/object model for posts, media, users, terms, and provider objects.
- WordPress, ACF, Meta Box, CGM Authors, and CGM Game Linker provider architecture.
- Typed field registry and Field Explorer.
- Full relationship schema, Core/provider stores, reverse lookup, primary/order/roles/metadata/permissions/visibility.
- Post/media, user, term, and callback query providers.
- Nested visual query builder, saved queries, code queries, context, preview/explain, usage tracking.
- Typed dynamic-data registry and relationship traversal.
- Native Gutenberg Summary integration, query loop, dynamic value block, and Block Bindings.
- Bricks saved queries, dynamic tags, and Element Conditions.
- Elementor dynamic tag and saved-query Query IDs.
- Oxygen, Divi, Mosaic builder adapter contracts plus PHP/REST/shortcode interoperability.
- Versioned events, service registry, dependency-aware cache tags, REST, Site Health, WP-CLI.
- Configuration export/diff/dry-run/import/verification/backups/rollback/recovery and code-managed definitions.
- Explicit multisite policy, network defaults, and opt-in cross-site-provider policy.
- Modular WordPress-native Core administration and diagnostics.

## Beta gate

Beta should not add another major subsystem unless runtime testing exposes a design defect. Work moves to:

- staging install/upgrade testing,
- current CGM Authors and Game Linker companion bridge validation,
- Gutenberg and builder browser QA,
- production-scale query profiling,
- database/index tuning,
- accessibility and keyboard QA,
- UI spacing/copy/polish,
- multisite runtime validation,
- compatibility testing across supported WordPress/PHP versions,
- PHPCS/PHPStan/CI cleanup,
- documentation/examples.
