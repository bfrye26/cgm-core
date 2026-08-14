<?php
namespace CGM\Core\Contracts;

use CGM\Core\Objects\ObjectReference;

interface ObjectAdapterInterface {
    public function kind(): string;
    public function exists( ObjectReference $object ): bool;
    public function label( ObjectReference $object ): string;
    public function url( ObjectReference $object ): string;
    public function edit_url( ObjectReference $object ): string;
    public function is_public( ObjectReference $object ): bool;
    public function property( ObjectReference $object, string $property ): mixed;
    public function search( string $subtype, string $search, array $args = array() ): array;
}
