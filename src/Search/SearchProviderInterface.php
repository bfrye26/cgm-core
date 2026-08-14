<?php
namespace CGM\Core\Search;

/** A search backend. Core ships a native WP_Query provider; engines like Typesense register their own. */
interface SearchProviderInterface {
    public function id(): string;
    /** @return array{items:array,total:int,page:int,per_page:int} */
    public function search( string $query, array $args = array() ): array;
    /** @return array<int,array{id:string,label:string,taxonomy:string,options:array}> */
    public function facets( string $query, array $args = array() ): array;
}
