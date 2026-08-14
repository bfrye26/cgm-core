<?php
namespace CGM\Core\Contracts;
use CGM\Core\Plugin;
interface FieldProviderInterface extends ProviderInterface { public function register_fields( Plugin $core ): void; }
