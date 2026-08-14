<?php
namespace CGM\Core\Contracts;
use CGM\Core\Plugin;
interface DynamicDataProviderInterface extends ProviderInterface { public function register_dynamic_data( Plugin $core ): void; }
