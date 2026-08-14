<?php
namespace CGM\Core\DynamicData;

final class DynamicValue implements \JsonSerializable {
    public function __construct( public mixed $value, public string $type = 'string', public array $definition = array() ) {}
    public function jsonSerialize(): array { return array( 'value' => $this->value, 'type' => $this->type ); }
}
