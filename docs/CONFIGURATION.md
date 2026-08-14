# Configuration management

Core configuration is separate from provider-owned content data.

Configuration includes:
- saved database queries;
- Core-owned relationship definitions;
- configuration schema/version metadata.

Provider records, Game Linker relationships, author credits, ACF values and other domain content are not exported as Core configuration.

## Workflow

1. Export source configuration.
2. Validate the incoming schema.
3. Preview the diff or use dry-run.
4. Choose merge or replace.
5. Core writes a backup immediately before mutation.
6. Apply and verify the resulting configuration.
7. Roll back to a retained backup if required.

Replace protects code-managed definitions.

## Configuration as code

Use `cgm_register_saved_query()` and `cgm_register_relationship_definition()` to make definitions code-owned. The UI reports them as managed by code and import/replace does not silently overwrite them.

## Multisite

Configuration is site-local by default. Network defaults are optional fallbacks. A site-local definition wins over a network default. Cross-site relationships require explicit provider support and opt-in policy rather than Core assuming a global relationship table.
