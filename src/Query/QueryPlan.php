<?php
namespace CGM\Core\Query;

final class QueryPlan implements \JsonSerializable {
    public function __construct(
        public string $provider,
        public string $content_type,
        public string $sql,
        public array $params = array(),
        public string $count_sql = '',
        public array $count_params = array(),
        public array $steps = array(),
        public array $dependencies = array()
    ) {}

    public function jsonSerialize(): array {
        return array(
            'provider'     => $this->provider,
            'content_type' => $this->content_type,
            'sql'          => $this->sql,
            'params'       => $this->params,
            'count_sql'    => $this->count_sql,
            'steps'        => $this->steps,
            'dependencies' => $this->dependencies,
        );
    }
}
