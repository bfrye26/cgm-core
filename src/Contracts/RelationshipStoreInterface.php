<?php
namespace CGM\Core\Contracts;
interface RelationshipStoreInterface {
    public function get( string $relationship, string $source_type, int $source_id, array $args = array() ): array;
    public function get_reverse( string $relationship, string $target_type, int $target_id, array $args = array() ): array;
    public function replace( string $relationship, string $source_type, int $source_id, string $target_type, array $items ): bool;
}
