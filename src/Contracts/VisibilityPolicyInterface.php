<?php
namespace CGM\Core\Contracts;
interface VisibilityPolicyInterface { public function can_read( string $object_type, int $object_id, bool $public_only = false ): bool; }
