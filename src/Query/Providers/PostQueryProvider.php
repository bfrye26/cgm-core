<?php
namespace CGM\Core\Query\Providers;

use CGM\Core\Contracts\QueryProviderInterface;
use CGM\Core\Plugin;
use CGM\Core\Query\QueryPlan;
use CGM\Core\Query\QueryResult;

final class PostQueryProvider implements QueryProviderInterface {
    private array $steps = array();
    private array $dependencies = array();

    public function __construct( private Plugin $core ) {}
    public function id(): string { return 'wordpress-posts'; }
    public function supports( array $content_type ): bool { return in_array( (string) ( $content_type['kind'] ?? '' ), array( 'post', 'media' ), true ); }

    public function run( array $query, array $context, array $content_type ): QueryResult {
        global $wpdb;
        $started = microtime( true );
        $plan = $this->plan( $query, $context, $content_type );
        $count_sql = $this->prepare( $plan->count_sql, $plan->count_params );
        $total = (int) $wpdb->get_var( $count_sql );
        $sql = $this->prepare( $plan->sql, $plan->params );
        $ids = array_map( 'absint', (array) $wpdb->get_col( $sql ) );
        $items = array();
        if ( $ids ) {
            $items = get_posts( array(
                'post_type'        => 'any',
                'post_status'      => 'any',
                'posts_per_page'   => count( $ids ),
                'post__in'         => $ids,
                'orderby'          => 'post__in',
                'suppress_filters' => false,
            ) );
        }
        $debug = array(
            'provider'        => $this->id(),
            'plan'            => $plan,
            'sql'             => current_user_can( 'manage_cgm_queries' ) ? $sql : '',
            'count_sql'       => current_user_can( 'manage_cgm_queries' ) ? $count_sql : '',
            'execution_ms'    => round( ( microtime( true ) - $started ) * 1000, 2 ),
            'dependencies'    => $plan->dependencies,
            'candidate_count' => count( $ids ),
        );
        return new QueryResult( $items, $total, (int) $query['page'], (int) $query['limit'], $debug );
    }

    public function explain( array $query, array $context, array $content_type ): array {
        $plan = $this->plan( $query, $context, $content_type );
        return array( 'provider' => $this->id(), 'plan' => $plan );
    }

    /**
     * Aggregate the query's result set. COUNT works for any group_by; SUM/AVG/MIN/MAX
     * aggregate a numeric `aggregate.field` (post column or meta) and group by a post
     * column, taxonomy (`taxonomy.<name>`), meta field, or core relationship
     * (`relationship.<id>`).
     */
    public function run_aggregate( array $query, array $context, array $content_type ): array {
        global $wpdb;
        $agg      = is_array( $query['aggregate'] ?? null ) ? $query['aggregate'] : array();
        $group_by = sanitize_text_field( (string) ( $agg['group_by'] ?? '' ) );
        if ( ! $group_by ) { return array( 'error' => 'Aggregate requires a group_by field.' ); }
        $fn = strtoupper( (string) ( $agg['function'] ?? 'COUNT' ) );
        if ( ! in_array( $fn, array( 'COUNT', 'SUM', 'AVG', 'MIN', 'MAX' ), true ) ) { $fn = 'COUNT'; }
        $limit     = max( 1, min( 500, (int) ( $agg['limit'] ?? 50 ) ) );
        $agg_field = sanitize_text_field( (string) ( $agg['field'] ?? '' ) );

        $post_type = (string) ( $content_type['subtype'] ?? ( 'media' === ( $content_type['kind'] ?? '' ) ? 'attachment' : $content_type['id'] ) );
        $where  = array( 'p.post_type = %s' );
        $params = array( $post_type );
        $statuses = (array) ( $query['status'] ?? array( 'publish' ) );
        if ( $statuses && ! in_array( 'any', $statuses, true ) ) {
            $ph = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
            $where[] = "p.post_status IN ({$ph})"; $params = array_merge( $params, $statuses );
        }
        $filter = $this->compile_group( (array) ( $query['filters'] ?? array() ), $content_type, 'p.ID' );
        if ( $filter['sql'] ) { $where[] = '(' . $filter['sql'] . ')'; $params = array_merge( $params, $filter['params'] ); }

        $joins = ''; $join_params = array(); $group_expr = ''; $label_expr = '';

        if ( str_starts_with( $group_by, 'taxonomy.' ) ) {
            $tax = sanitize_key( substr( $group_by, 9 ) );
            if ( ! taxonomy_exists( $tax ) ) { return array( 'error' => 'Unknown taxonomy.' ); }
            $joins .= " LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id=p.ID LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id AND tt.taxonomy=%s LEFT JOIN {$wpdb->terms} t ON t.term_id=tt.term_id";
            $join_params[] = $tax;
            $group_expr = 't.name'; $label_expr = 't.name';
        } elseif ( str_starts_with( $group_by, 'relationship.' ) ) {
            $rel = sanitize_key( substr( $group_by, 13 ) );
            $d = $this->core->relationships()->get_type( $rel );
            if ( ! $d ) { return array( 'error' => 'Unknown relationship.' ); }
            if ( 'core' !== (string) ( $d['store'] ?? 'core' ) ) { return array( 'error' => 'Only core relationships can be aggregated.' ); }
            $joins .= " LEFT JOIN {$wpdb->prefix}cgm_core_relationships cr ON cr.relationship_key=%s AND cr.source_id=p.ID";
            $join_params[] = $rel;
            $group_expr = 'cr.target_id';
            $label_expr = "COALESCE((SELECT post_title FROM {$wpdb->posts} WHERE ID=cr.target_id), CONCAT('#', cr.target_id))";
        } else {
            $field = $this->core->fields()->get( $group_by );
            if ( ! $field ) { return array( 'error' => 'Unknown group_by field.' ); }
            $provider = (string) ( $field['provider'] ?? '' ); $source = (string) ( $field['source'] ?? '' );
            if ( 'wordpress' === $provider && in_array( $source, array( 'post_type', 'post_status', 'post_author', 'post_date', 'post_modified' ), true ) ) {
                $group_expr = 'p.`' . $source . '`'; $label_expr = 'p.`' . $source . '`';
            } elseif ( $source ) {
                $joins .= " LEFT JOIN {$wpdb->postmeta} pmg ON pmg.post_id=p.ID AND pmg.meta_key=%s";
                $join_params[] = $source;
                $group_expr = 'pmg.meta_value'; $label_expr = 'pmg.meta_value';
            } else {
                return array( 'error' => 'Unsupported group_by field.' );
            }
        }

        if ( 'COUNT' === $fn ) {
            $select = 'COUNT(p.ID) AS total';
        } else {
            $field = $this->core->fields()->get( $agg_field );
            if ( ! $field ) { return array( 'error' => 'SUM/AVG/MIN/MAX require an aggregate field.' ); }
            $provider = (string) ( $field['provider'] ?? '' ); $source = (string) ( $field['source'] ?? '' );
            if ( 'wordpress' === $provider && in_array( $source, array( 'ID', 'post_parent', 'menu_order' ), true ) ) {
                $expr = 'p.`' . $source . '`';
            } elseif ( $source ) {
                $joins .= " LEFT JOIN {$wpdb->postmeta} pma ON pma.post_id=p.ID AND pma.meta_key=%s";
                $join_params[] = $source;
                $expr = 'pma.meta_value';
            } else {
                return array( 'error' => 'Unsupported aggregate field.' );
            }
            $select = $fn . '(CAST(' . $expr . ' AS DECIMAL(30,10))) AS total';
        }

        $base = " FROM {$wpdb->posts} p{$joins} WHERE " . implode( ' AND ', $where );
        $sql = 'SELECT ' . $select . ', ' . $label_expr . ' AS label' . $base . " GROUP BY {$group_expr} ORDER BY 1 DESC LIMIT %d";
        $all_params = array_merge( $join_params, $params, array( $limit ) );
        $prepared = $wpdb->prepare( $sql, ...$all_params );
        $rows = $wpdb->get_results( $prepared, ARRAY_A ) ?: array();
        return array(
            'group_by' => $group_by, 'function' => $fn, 'rows' => $rows,
            'sql' => current_user_can( 'manage_cgm_queries' ) ? $prepared : '',
        );
    }

    private function plan( array $query, array $context, array $content_type ): QueryPlan {
        global $wpdb;
        $this->steps = array(); $this->dependencies = array( 'content:' . (string) $content_type['id'] );
        $post_type = (string) ( $content_type['subtype'] ?? ( 'media' === ( $content_type['kind'] ?? '' ) ? 'attachment' : $content_type['id'] ) );
        $where = array( 'p.post_type = %s' ); $params = array( $post_type );
        $statuses = (array) ( $query['status'] ?? array( 'publish' ) );
        if ( $statuses && ! in_array( 'any', $statuses, true ) ) {
            $ph = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
            $where[] = "p.post_status IN ({$ph})"; $params = array_merge( $params, $statuses );
        }
        if ( '' !== (string) ( $query['search'] ?? '' ) ) {
            $like = '%' . $wpdb->esc_like( (string) $query['search'] ) . '%';
            $where[] = '(p.post_title LIKE %s OR p.post_excerpt LIKE %s OR p.post_content LIKE %s)';
            array_push( $params, $like, $like, $like );
            $this->dependencies[] = 'post.search';
        }
        $filter = $this->compile_group( (array) ( $query['filters'] ?? array() ), $content_type, 'p.ID' );
        if ( $filter['sql'] ) { $where[] = '(' . $filter['sql'] . ')'; $params = array_merge( $params, $filter['params'] ); }
        $order = $this->compile_sort( (array) ( $query['sort'] ?? array() ), $content_type );
        $limit = max( 1, (int) $query['limit'] );
        $page_offset = ( max( 1, (int) $query['page'] ) - 1 ) * $limit;
        $offset = $page_offset + max( 0, (int) $query['offset'] );
        $base = " FROM {$wpdb->posts} p WHERE " . implode( ' AND ', $where );
        $count_sql = 'SELECT COUNT(DISTINCT p.ID)' . $base;
        $sql = 'SELECT DISTINCT p.ID' . $base . ' ORDER BY ' . $order['sql'] . ' LIMIT %d OFFSET %d';
        $sql_params = array_merge( $params, $order['params'], array( $limit, $offset ) );
        $this->steps[] = array( 'type' => 'hydrate', 'description' => 'Hydrate only the paged IDs through WordPress so filters and post objects remain compatible.' );
        return new QueryPlan( $this->id(), (string) $content_type['id'], $sql, $sql_params, $count_sql, $params, $this->steps, array_values( array_unique( $this->dependencies ) ) );
    }

    private function compile_group( array $group, array $content_type, string $source_expression ): array {
        $rules = (array) ( $group['rules'] ?? array() );
        if ( ! $rules ) { return array( 'sql' => '', 'params' => array() ); }
        $parts = array(); $params = array();
        foreach ( $rules as $rule ) {
            if ( ! is_array( $rule ) ) { continue; }
            $compiled = isset( $rule['rules'] ) || isset( $rule['relation'] )
                ? $this->compile_group( $rule, $content_type, $source_expression )
                : $this->compile_rule( $rule, $content_type, $source_expression );
            if ( $compiled['sql'] ) { $parts[] = '(' . $compiled['sql'] . ')'; $params = array_merge( $params, $compiled['params'] ); }
        }
        if ( ! $parts ) { return array( 'sql' => '', 'params' => array() ); }
        $relation = 'OR' === strtoupper( (string) ( $group['relation'] ?? 'AND' ) ) ? ' OR ' : ' AND ';
        return array( 'sql' => implode( $relation, $parts ), 'params' => $params );
    }

    private function compile_rule( array $rule, array $content_type, string $source_expression ): array {
        $type = sanitize_key( (string) ( $rule['type'] ?? 'field' ) );
        $operator = strtoupper( (string) ( $rule['operator'] ?? '=' ) );
        $value = $rule['value'] ?? '';
        if ( 'path' === $type ) { $path=sanitize_text_field((string)($rule['path']??''));$this->dependencies[]='path:'.$path;return (new \CGM\Core\Query\PathQueryCompiler($this->core))->condition($path,$operator,$value,(string)$content_type['id'],$source_expression); }
        if ( 'relationship_property' === $type ) { $relationship=sanitize_key((string)($rule['relationship']??''));$property=sanitize_text_field((string)($rule['property']??''));$this->dependencies[]='relationship:'.$relationship;return $this->core->relationships()->sql_property_condition($relationship,$property,$operator,$value,$source_expression,(string)$content_type['id']); }
        if ( 'relationship' === $type ) {
            $relationship = sanitize_key( (string) ( $rule['relationship'] ?? '' ) );
            $this->dependencies[] = 'relationship:' . $relationship;
            $this->steps[] = array( 'type' => 'relationship', 'relationship' => $relationship, 'operator' => $operator );
            return $this->core->relationships()->sql_condition( $relationship, $operator, $value, $source_expression, (string) $content_type['id'] );
        }
        if ( 'taxonomy' === $type ) {
            $taxonomy = sanitize_key( (string) ( $rule['taxonomy'] ?? '' ) );
            $this->dependencies[] = 'taxonomy:' . $taxonomy;
            $this->steps[] = array( 'type' => 'taxonomy', 'taxonomy' => $taxonomy, 'operator' => $operator );
            return $this->taxonomy_condition( $taxonomy, $operator, $value, $source_expression );
        }
        if ( 'relationship_reverse' === $type ) {
            $relationship = sanitize_key( (string) ( $rule['relationship'] ?? '' ) );
            $this->dependencies[] = 'relationship:' . $relationship;
            $this->steps[] = array( 'type' => 'relationship_reverse', 'relationship' => $relationship, 'operator' => $operator );
            return $this->core->relationships()->sql_reverse_condition( $relationship, $operator, $value, $source_expression, (string) $content_type['id'] );
        }
        if ( 'relationship_count' === $type ) {
            $relationship = sanitize_key( (string) ( $rule['relationship'] ?? '' ) );
            $this->dependencies[] = 'relationship:' . $relationship;
            $this->steps[] = array( 'type' => 'relationship_count', 'relationship' => $relationship, 'operator' => $operator, 'reverse' => ! empty( $rule['reverse'] ) );
            return $this->core->relationships()->sql_count_condition( $relationship, $operator, $value, $source_expression, ! empty( $rule['reverse'] ) );
        }
        $field_id = (string) ( $rule['field'] ?? '' );
        $field = $this->core->fields()->get( $field_id );
        if ( ! $field || ! $this->field_applies( $field, (string) $content_type['id'] ) ) { return array( 'sql' => '1=0', 'params' => array() ); }
        $this->dependencies[] = 'field:' . $field_id;
        $this->steps[] = array( 'type' => 'field', 'field' => $field_id, 'operator' => $operator );
        if ( in_array( (string) ( $field['type'] ?? '' ), array( 'date', 'datetime' ), true ) ) { $value = \CGM\Core\Support\RelativeDate::expand( $value ); }
        $provider = (string) ( $field['provider'] ?? '' ); $source = (string) ( $field['source'] ?? '' );
        if ( 'wordpress' === $provider && in_array( $source, array( 'ID', 'post_title', 'post_name', 'post_author', 'post_status', 'post_date', 'post_modified', 'post_parent', 'menu_order', 'post_mime_type' ), true ) ) {
            return $this->scalar_condition( 'p.`' . $source . '`', $operator, $value, (string) ( $field['type'] ?? 'string' ) );
        }
        return $this->meta_condition( $source, $operator, $value, (string) ( $field['type'] ?? 'string' ), $source_expression );
    }

    private function meta_condition( string $key, string $operator, mixed $value, string $type, string $source_expression ): array {
        global $wpdb;
        if ( ! $key ) { return array( 'sql' => '1=0', 'params' => array() ); }
        if ( 'NOT EXISTS' === $operator ) {
            return array( 'sql' => "NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id={$source_expression} AND pm.meta_key=%s)", 'params' => array( $key ) );
        }
        if ( 'EXISTS' === $operator ) {
            return array( 'sql' => "EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id={$source_expression} AND pm.meta_key=%s)", 'params' => array( $key ) );
        }
        $condition = $this->scalar_condition( $this->cast( 'pm.meta_value', $type ), $operator, $value, $type );
        $sql = "EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id={$source_expression} AND pm.meta_key=%s AND {$condition['sql']})";
        if ( in_array( $operator, array( '!=', 'NOT IN', 'NOT LIKE', 'NOT BETWEEN' ), true ) ) {
            // Meta negative comparisons should also match objects where the key is missing.
            $positive = array( '!=' => '=', 'NOT IN' => 'IN', 'NOT LIKE' => 'LIKE', 'NOT BETWEEN' => 'BETWEEN' )[ $operator ];
            $condition = $this->scalar_condition( $this->cast( 'pm.meta_value', $type ), $positive, $value, $type );
            $sql = "NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id={$source_expression} AND pm.meta_key=%s AND {$condition['sql']})";
        }
        return array( 'sql' => $sql, 'params' => array_merge( array( $key ), $condition['params'] ) );
    }

    private function taxonomy_condition( string $taxonomy, string $operator, mixed $value, string $source_expression ): array {
        global $wpdb;
        if ( ! taxonomy_exists( $taxonomy ) ) { return array( 'sql' => '1=0', 'params' => array() ); }
        $base = "SELECT 1 FROM {$wpdb->term_relationships} tr JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id JOIN {$wpdb->terms} t ON t.term_id=tt.term_id WHERE tr.object_id={$source_expression} AND tt.taxonomy=%s";
        if ( 'EXISTS' === $operator ) { return array( 'sql' => "EXISTS ({$base})", 'params' => array( $taxonomy ) ); }
        if ( 'NOT EXISTS' === $operator ) { return array( 'sql' => "NOT EXISTS ({$base})", 'params' => array( $taxonomy ) ); }
        $values = is_array( $value ) ? $value : array_values( array_filter( array_map( 'trim', explode( ',', (string) $value ) ), static fn( $v ) => '' !== $v ) );
        if ( ! $values ) { return array( 'sql' => in_array( $operator, array( 'NOT IN', '!=' ), true ) ? '1=1' : '1=0', 'params' => array() ); }
        $all_numeric = count( array_filter( $values, static fn( $v ) => ctype_digit( (string) $v ) ) ) === count( $values );
        $column = $all_numeric ? 't.term_id' : 't.slug';
        $ph = implode( ',', array_fill( 0, count( $values ), $all_numeric ? '%d' : '%s' ) );
        $params = array_merge( array( $taxonomy ), $all_numeric ? array_map( 'absint', $values ) : array_map( 'sanitize_title', $values ) );
        $exists = "EXISTS ({$base} AND {$column} IN ({$ph}))";
        if ( in_array( $operator, array( 'NOT IN', '!=' ), true ) ) { $exists = 'NOT ' . $exists; }
        return array( 'sql' => $exists, 'params' => $params );
    }

    private function scalar_condition( string $expression, string $operator, mixed $value, string $type ): array {
        global $wpdb;
        $allowed = array( '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN' );
        if ( ! in_array( $operator, $allowed, true ) ) { $operator = '='; }
        if ( in_array( $operator, array( 'IN', 'NOT IN' ), true ) ) {
            $values = is_array( $value ) ? $value : array_values( array_filter( array_map( 'trim', explode( ',', (string) $value ) ), static fn( $v ) => '' !== $v ) );
            if ( ! $values ) { return array( 'sql' => 'NOT IN' === $operator ? '1=1' : '1=0', 'params' => array() ); }
            $ph = implode( ',', array_fill( 0, count( $values ), in_array( $type, array( 'integer', 'number', 'boolean' ), true ) ? '%f' : '%s' ) );
            return array( 'sql' => "{$expression} {$operator} ({$ph})", 'params' => $values );
        }
        if ( in_array( $operator, array( 'BETWEEN', 'NOT BETWEEN' ), true ) ) {
            $values = is_array( $value ) ? array_values( $value ) : array_map( 'trim', explode( ',', (string) $value ) );
            $values = array_pad( $values, 2, '' );
            return array( 'sql' => "{$expression} {$operator} %s AND %s", 'params' => array( $values[0], $values[1] ) );
        }
        if ( in_array( $operator, array( 'LIKE', 'NOT LIKE' ), true ) ) {
            return array( 'sql' => "{$expression} {$operator} %s", 'params' => array( '%' . $wpdb->esc_like( (string) $value ) . '%' ) );
        }
        return array( 'sql' => "{$expression} {$operator} %s", 'params' => array( $value ) );
    }

    private function compile_sort( array $sorts, array $content_type ): array {
        global $wpdb; $parts=array();$params=array();
        foreach($sorts as $sort){$order='ASC'===strtoupper((string)($sort['order']??'DESC'))?'ASC':'DESC';$path=sanitize_text_field((string)($sort['path']??''));if($path){$compiled=(new \CGM\Core\Query\PathQueryCompiler($this->core))->sort($path,(string)$content_type['id'],'p.ID',!empty($sort['numeric']));if($compiled){$parts[]=$compiled['sql'].' '.$order;$params=array_merge($params,$compiled['params']);$this->dependencies[]='path:'.$path;}continue;}$field=$this->core->fields()->get((string)($sort['field']??''));if(!$field||empty($field['sortable'])||!$this->field_applies($field,(string)$content_type['id']))continue;$source=(string)($field['source']??'');if('wordpress'===($field['provider']??'')&&in_array($source,array('ID','post_title','post_name','post_status','post_date','post_modified','post_author'),true)){$parts[]='p.`'.$source.'` '.$order;}elseif($source){$cast=!empty($sort['numeric'])?'CAST(pm_sort.meta_value AS DECIMAL(30,10))':'pm_sort.meta_value';$parts[]="(SELECT {$cast} FROM {$wpdb->postmeta} pm_sort WHERE pm_sort.post_id=p.ID AND pm_sort.meta_key=%s ORDER BY pm_sort.meta_id DESC LIMIT 1) {$order}";$params[]=$source;}}
        if(!$parts)$parts[]='p.post_date DESC';$parts[]='p.ID DESC';return array('sql'=>implode(', ',$parts),'params'=>$params);
    }

    private function field_applies( array $field, string $content_type ): bool { $types=(array)($field['content_types']??array('*'));return in_array('*',$types,true)||in_array($content_type,$types,true); }

    private function cast( string $expression, string $type ): string {
        return match ( $type ) {
            'integer', 'boolean' => "CAST({$expression} AS SIGNED)",
            'number' => "CAST({$expression} AS DECIMAL(30,10))",
            'datetime', 'date' => "CAST({$expression} AS DATETIME)",
            default => $expression,
        };
    }

    private function prepare( string $sql, array $params ): string {
        global $wpdb;
        return $params ? $wpdb->prepare( $sql, ...$params ) : $sql;
    }
}
