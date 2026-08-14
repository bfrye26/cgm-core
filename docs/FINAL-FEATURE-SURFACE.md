# CGM Core 3.0.0-alpha.4.4 Final Feature Surface

This alpha intentionally places every Core-side feature intended for the 3.0 final release behind stable, versioned contracts before beta begins.

## Added after alpha.3

- Relationship-property filtering for role, primary state, order and typed JSON metadata.
- Relationship/data-path query rules and bounded relationship traversal SQL.
- Relationship/path sorting where the provider can compile a deterministic scalar expression.
- Async object/term/user selectors in the visual query builder.
- Visual data-path browser and path REST endpoint.
- Visual relationship metadata schema editor; raw JSON is no longer the primary setup workflow.
- Multisite network relationship store with explicit source/target site identity.
- Gutenberg Query Loop saved/inline modes.
- Gutenberg Dynamic Value custom traversal paths.
- Bricks-native CGM query controls in query-capable element control groups.
- Builder-neutral manifest endpoint for Oxygen, Divi, Mosaic and future builders.
- CGM Suite content/relationship event bridge for search, SEO and cache consumers.
- Setup/discovery screen for providers, builders, content, fields and relationship readiness.

## External ownership boundary

CGM Core cannot safely rewrite private storage owned by another plugin. CGM Authors and CGM Game Linker therefore retain formal provider bridge contracts. Core includes backwards-safe compatibility for existing real-user Authors credits. Game Linker must expose its bridge from its own release for writes to remain source-of-truth safe. Builder adapters only use public extension surfaces; no private Oxygen/Divi/Mosaic internals are hard-coded.

Beta is reserved for real WordPress staging validation, browser interaction testing, performance profiling, accessibility, compatibility and UI polish rather than new Core subsystems.
