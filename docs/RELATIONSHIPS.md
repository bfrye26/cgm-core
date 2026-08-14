# Relationship platform

Relationships are first-class references between registered CGM content/object types. The schema is separate from storage, so existing plugins may keep their data while still participating in Core querying, Gutenberg and builders.

## Example definition

```php
cgm_register_relationship_definition( [
    'id'            => 'game',
    'label'         => 'Games',
    'reverse_label' => 'Coverage',
    'source_type'   => 'post',
    'target_type'   => 'game',
    'cardinality'   => 'many_to_many',
    'multiple'      => true,
    'ordered'       => true,
    'primary'       => true,
    'primary_max'   => 1,
    'roles'         => [ 'reviewed', 'previewed', 'mentioned' ],
    'metadata_schema' => [
        'display' => [ 'type'=>'boolean', 'label'=>'Display on article', 'public'=>true ],
        'status'  => [ 'type'=>'select', 'options'=>[ 'confirmed'=>'Confirmed', 'suggested'=>'Suggested' ] ],
        'notes'   => [ 'type'=>'textarea', 'public'=>false ],
    ],
    'permissions' => [
        'read'   => 'read',
        'assign' => 'edit_posts',
        'manage' => 'manage_cgm_relationships',
    ],
    'delete_behavior' => 'detach',
] );
```

## Stores

`store => core` uses the Core relationship table. Existing plugins should instead register a store adapter. Store adapters own read/write semantics and can implement query-safe SQL/provider conditions through `QueryableRelationshipStoreInterface`.

## Delete policies

- `detach`: remove references involving the deleted object.
- `restrict`: prevent supported object deletions where WordPress supplies a short-circuitable capability/filter; otherwise surface the restriction event to the owning integration.
- `cascade`: dispatch the cascade contract to the provider before detaching Core references.

## Public projection

Public REST/dynamic output respects object visibility and relationship visibility. Relationship metadata marked `public => false` is removed from anonymous/public projections.
