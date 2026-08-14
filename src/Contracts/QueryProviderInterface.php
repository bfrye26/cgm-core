<?php
namespace CGM\Core\Contracts;

use CGM\Core\Query\QueryResult;

interface QueryProviderInterface {
    public function id(): string;
    public function supports( array $content_type ): bool;
    public function run( array $query, array $context, array $content_type ): QueryResult;
    public function explain( array $query, array $context, array $content_type ): array;
}
