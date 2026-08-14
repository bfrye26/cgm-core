<?php
namespace CGM\Core\Contracts;
use CGM\Core\Plugin;
interface ContentProviderInterface extends ProviderInterface { public function register_content( Plugin $core ): void; }
