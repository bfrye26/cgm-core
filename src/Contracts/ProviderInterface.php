<?php
namespace CGM\Core\Contracts;
use CGM\Core\Plugin;
interface ProviderInterface { public function id(): string; public function register( Plugin $core ): void; }
