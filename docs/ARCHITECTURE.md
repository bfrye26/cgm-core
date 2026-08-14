# CGM Core architecture

CGM Core is an interoperability kernel, not a second CMS and not a shared UI shell.

```text
Feature plugins / data providers
        Authors · Game Linker · ACF · Meta Box · future plugins
                              │
                              ▼
┌──────────────────────────────────────────────────────────────┐
│                          CGM CORE                            │
│ Provider Registry        Object / Content Registry          │
│ Field Registry           Relationship API + Stores          │
│ Query Providers          Query Planner / Saved Queries      │
│ Context Engine           Typed Dynamic Data / Traversal     │
│ Events + Services        Cache Dependencies                 │
│ Configuration            Permissions / REST / CLI           │
└──────────────────────────────────────────────────────────────┘
                              │
          ┌───────────────────┼──────────────────────┐
          ▼                   ▼                      ▼
      Gutenberg            Bricks              Other builders
```

## Ownership rules

1. WordPress remains authoritative where it already has a native model.
2. Feature plugins remain authoritative for their domain data.
3. Core owns contracts, discovery, interoperability, reusable querying, and shared integration behavior.
4. A builder adapter consumes Core. It never needs to understand Game Linker, Authors, ACF, or another provider directly.
5. Provider storage does not need to migrate into Core to participate.
6. Missing optional providers fail closed and disappear from relevant UI rather than causing fatal errors.

## Object model

Every content object is represented canonically by a content type plus ID, for example:

```text
post:1171329
game:8421
media:51722
user:42
term_category:18
guest_author:2004
```

`ObjectResolver` delegates object behavior to adapters. Built-in adapters cover posts/media/users/terms, while callback adapters support domain objects.

## Query model

Queries use one normalized AST regardless of consumer. The query engine resolves context and delegates execution to a registered query provider. Built-in providers compile posts/media, users, and terms into bounded database queries. Providers can register custom query backends.

A representative filter tree:

```text
AND
├─ relationship game = @current_game
├─ taxonomy category IN reviews, previews
└─ OR
   ├─ field acf.review_score >= 80
   └─ field meta.post.featured = true
```

The post provider translates supported rules into SQL/meta/taxonomy/relationship subqueries so pagination is applied by the database rather than loading an unbounded ID universe into PHP.

## Relationship model

Relationship definitions describe semantics separately from storage:

```text
id / labels
source content types
target content type
cardinality
multiple / ordered
primary / primary limit
roles
metadata schema
read / assign / manage permissions
public visibility
delete behavior
store provider
```

The Core store uses a dedicated relationship table. Existing plugins can implement `RelationshipStoreInterface` or supply a `CallbackRelationshipStore` bridge.

## Dynamic data and context

Registered values can be resolved directly, while `TraversalResolver` follows object properties and relationships for paths such as:

```text
relationship.game.primary.label
relationship.product.primary.company.primary.label
```

Contexts can be native (`current_post`, `current_user`, `current_term`, `current_query_item`) or registered by providers/relationships (`current_game`, `current_product`, etc.).

## Configuration

Database-managed saved queries and relationship definitions can be exported as versioned JSON. Imports support validation, diff, dry run, merge/replace, verification, automatic rollback on failure, and recovery from interrupted operations. Code-managed definitions remain read-only. In multisite, optional network defaults are read-only fallbacks and local definitions take precedence.
