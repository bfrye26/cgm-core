<?php
namespace CGM\Core\Contracts;
use CGM\Core\Plugin;
interface RelationshipProviderInterface extends ProviderInterface { public function register_relationships( Plugin $core ): void; }
