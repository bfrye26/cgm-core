<?php
namespace CGM\Core\Query;
final class QueryResult implements \JsonSerializable {
    public function __construct( public array $items = array(), public int $total = 0, public int $page = 1, public int $per_page = 10, public array $debug = array() ) {}
    public function jsonSerialize(): array { return array( 'items'=>$this->items, 'total'=>$this->total, 'page'=>$this->page, 'per_page'=>$this->per_page ); }
}
