<?php
namespace CGM\Core\Contracts;

/**
 * Optional SQL compiler extension for relationship stores.
 *
 * Stores may participate in the universal query planner without returning
 * unbounded PHP ID lists. Property and wrap compilers are used for
 * relationship metadata and relationship traversal respectively.
 */
interface QueryableRelationshipStoreInterface extends RelationshipStoreInterface {
    public function sql_condition(
        string $relationship,
        string $source_type,
        string $target_type,
        string $operator,
        mixed $value,
        string $source_expression
    ): ?array;

    public function sql_property_condition(
        string $relationship,
        string $source_type,
        string $target_type,
        string $property,
        string $operator,
        mixed $value,
        string $source_expression
    ): ?array;

    /**
     * Wrap a child condition so it is evaluated against the target object of
     * the relationship. $child_sql may reference the token {{TARGET_ID}}.
     */
    public function sql_wrap_condition(
        string $relationship,
        string $source_type,
        string $target_type,
        string $selector,
        string $child_sql,
        array $child_params,
        string $source_expression
    ): ?array;

    /** Return a scalar correlated subquery suitable for ORDER BY when supported. */
    public function sql_sort_expression(
        string $relationship,
        string $source_type,
        string $target_type,
        string $property,
        string $selector,
        string $source_expression
    ): ?array;

    /** Reverse reference condition: the object is the relationship TARGET. */
    public function sql_reverse_condition(
        string $relationship,
        string $operator,
        mixed $value,
        string $target_expression
    ): ?array;

    /** Count of relationship rows for the object, forward or reverse. */
    public function sql_count_condition(
        string $relationship,
        string $operator,
        mixed $value,
        string $expression,
        bool $reverse
    ): ?array;
}
