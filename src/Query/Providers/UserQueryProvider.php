<?php
namespace CGM\Core\Query\Providers;

use CGM\Core\Contracts\QueryProviderInterface;
use CGM\Core\Plugin;
use CGM\Core\Query\QueryPlan;
use CGM\Core\Query\QueryResult;

final class UserQueryProvider implements QueryProviderInterface {
    private array $steps = array();
    private array $dependencies = array();
    public function __construct( private Plugin $core ) {}
    public function id(): string { return 'wordpress-users'; }
    public function supports( array $content_type ): bool { return 'user' === ( $content_type['kind'] ?? '' ); }

    public function run( array $query, array $context, array $content_type ): QueryResult {
        global $wpdb;
        $started = microtime( true ); $plan = $this->plan( $query, $context, $content_type );
        $count_sql = $this->prepare( $plan->count_sql, $plan->count_params );
        $total = (int) $wpdb->get_var( $count_sql );
        $sql = $this->prepare( $plan->sql, $plan->params );
        $ids = array_map( 'absint', (array) $wpdb->get_col( $sql ) );
        $items = array();
        if ( $ids ) {
            $map = array(); foreach ( get_users( array( 'include' => $ids, 'number' => count( $ids ) ) ) as $user ) { $map[ $user->ID ] = $user; }
            foreach ( $ids as $id ) { if ( isset( $map[ $id ] ) ) { $items[] = $map[ $id ]; } }
        }
        return new QueryResult( $items, $total, (int) $query['page'], (int) $query['limit'], array(
            'provider' => $this->id(), 'plan' => $plan,
            'sql' => current_user_can( 'manage_cgm_queries' ) ? $sql : '',
            'count_sql' => current_user_can( 'manage_cgm_queries' ) ? $count_sql : '',
            'execution_ms' => round( ( microtime( true ) - $started ) * 1000, 2 ),
            'dependencies' => $plan->dependencies,
        ) );
    }
    public function explain( array $query, array $context, array $content_type ): array { return array( 'provider' => $this->id(), 'plan' => $this->plan( $query, $context, $content_type ) ); }

    private function plan( array $query, array $context, array $content_type ): QueryPlan {
        global $wpdb;
        $this->steps = array(); $this->dependencies = array( 'content:user' ); $where = array( '1=1' ); $params = array();
        if ( '' !== (string) ( $query['search'] ?? '' ) ) {
            $like = '%' . $wpdb->esc_like( (string) $query['search'] ) . '%';
            $where[] = '(u.display_name LIKE %s OR u.user_login LIKE %s OR u.user_email LIKE %s)'; array_push( $params, $like, $like, $like );
        }
        $filter = $this->compile_group( (array) ( $query['filters'] ?? array() ), 'u.ID' );
        if ( $filter['sql'] ) { $where[] = '(' . $filter['sql'] . ')'; $params = array_merge( $params, $filter['params'] ); }
        $order = $this->compile_sort( (array) ( $query['sort'] ?? array() ) );
        $limit = max( 1, (int) $query['limit'] ); $offset = ( max( 1, (int) $query['page'] ) - 1 ) * $limit + max( 0, (int) $query['offset'] );
        $base = " FROM {$wpdb->users} u WHERE " . implode( ' AND ', $where );
        $count_sql = 'SELECT COUNT(DISTINCT u.ID)' . $base;
        $sql = 'SELECT DISTINCT u.ID' . $base . ' ORDER BY ' . $order['sql'] . ' LIMIT %d OFFSET %d';
        return new QueryPlan( $this->id(), (string) $content_type['id'], $sql, array_merge( $params, $order['params'], array( $limit, $offset ) ), $count_sql, $params, $this->steps, array_values( array_unique( $this->dependencies ) ) );
    }

    private function compile_group( array $group, string $source_expression ): array {
        $rules = (array) ( $group['rules'] ?? array() ); if ( ! $rules ) { return array( 'sql' => '', 'params' => array() ); }
        $parts = array(); $params = array();
        foreach ( $rules as $rule ) {
            if ( ! is_array( $rule ) ) { continue; }
            $c = isset( $rule['rules'] ) || isset( $rule['relation'] ) ? $this->compile_group( $rule, $source_expression ) : $this->compile_rule( $rule, $source_expression );
            if ( $c['sql'] ) { $parts[] = '(' . $c['sql'] . ')'; $params = array_merge( $params, $c['params'] ); }
        }
        $join = 'OR' === strtoupper( (string) ( $group['relation'] ?? 'AND' ) ) ? ' OR ' : ' AND ';
        return array( 'sql' => implode( $join, $parts ), 'params' => $params );
    }

    private function compile_rule( array $rule, string $source_expression ): array {
        global $wpdb;
        $type = sanitize_key( (string) ( $rule['type'] ?? 'field' ) ); $op = strtoupper( (string) ( $rule['operator'] ?? '=' ) ); $value = $rule['value'] ?? '';
        if ( 'path' === $type ) { $path=sanitize_text_field((string)($rule['path']??''));$this->dependencies[]='path:'.$path;return (new \CGM\Core\Query\PathQueryCompiler($this->core))->condition($path,$op,$value,'user',$source_expression); }
        if ( 'relationship_property' === $type ) { $relationship=sanitize_key((string)($rule['relationship']??''));$property=sanitize_text_field((string)($rule['property']??''));$this->dependencies[]='relationship:'.$relationship;return $this->core->relationships()->sql_property_condition($relationship,$property,$op,$value,$source_expression,'user'); }
        if ( 'relationship' === $type ) {
            $rel = sanitize_key( (string) ( $rule['relationship'] ?? '' ) ); $this->dependencies[] = 'relationship:' . $rel;
            return $this->core->relationships()->sql_condition( $rel, $op, $value, $source_expression, 'user' );
        }
        if ( 'taxonomy' === $type ) { return array( 'sql' => '1=0', 'params' => array() ); }
        $field_id = (string) ( $rule['field'] ?? '' ); $field = $this->core->fields()->get( $field_id ); if ( ! $field || ! $this->field_applies($field,'user') ) { return array( 'sql' => '1=0', 'params' => array() ); }
        $this->dependencies[] = 'field:' . $field_id; $source = (string) ( $field['source'] ?? '' ); $provider = (string) ( $field['provider'] ?? '' );
        $direct = array( 'ID', 'user_login', 'user_email', 'display_name', 'user_registered', 'user_url' );
        if ( 'wordpress-user' === $provider && in_array( $source, $direct, true ) ) { return $this->scalar( 'u.`' . $source . '`', $op, $value ); }
        if ( 'role' === $source ) {
            $key = $wpdb->get_blog_prefix() . 'capabilities';
            if ( in_array( $op, array( 'EXISTS', 'NOT EXISTS' ), true ) ) {
                return array( 'sql' => ( 'NOT EXISTS' === $op ? 'NOT ' : '' ) . "EXISTS (SELECT 1 FROM {$wpdb->usermeta} um WHERE um.user_id=u.ID AND um.meta_key=%s)", 'params' => array( $key ) );
            }
            $like = '%"' . $wpdb->esc_like( sanitize_key( (string) $value ) ) . '"%';
            $sql = "EXISTS (SELECT 1 FROM {$wpdb->usermeta} um WHERE um.user_id=u.ID AND um.meta_key=%s AND um.meta_value LIKE %s)";
            if ( in_array( $op, array( '!=', 'NOT LIKE', 'NOT IN' ), true ) ) { $sql = 'NOT ' . $sql; }
            return array( 'sql' => $sql, 'params' => array( $key, $like ) );
        }
        if ( ! $source ) { return array( 'sql' => '1=0', 'params' => array() ); }
        if ( 'NOT EXISTS' === $op || 'EXISTS' === $op ) {
            return array( 'sql' => ( 'NOT EXISTS' === $op ? 'NOT ' : '' ) . "EXISTS (SELECT 1 FROM {$wpdb->usermeta} um WHERE um.user_id=u.ID AND um.meta_key=%s)", 'params' => array( $source ) );
        }
        $condition = $this->scalar( 'um.meta_value', in_array( $op, array( '!=', 'NOT IN', 'NOT LIKE' ), true ) ? array( '!=' => '=', 'NOT IN' => 'IN', 'NOT LIKE' => 'LIKE' )[ $op ] : $op, $value );
        $sql = "EXISTS (SELECT 1 FROM {$wpdb->usermeta} um WHERE um.user_id=u.ID AND um.meta_key=%s AND {$condition['sql']})";
        if ( in_array( $op, array( '!=', 'NOT IN', 'NOT LIKE' ), true ) ) { $sql = 'NOT ' . $sql; }
        return array( 'sql' => $sql, 'params' => array_merge( array( $source ), $condition['params'] ) );
    }

    private function scalar( string $expr, string $op, mixed $value ): array {
        global $wpdb; $op = strtoupper( $op );
        if ( in_array( $op, array( 'IN', 'NOT IN' ), true ) ) { $vals = is_array( $value ) ? $value : array_filter( array_map( 'trim', explode( ',', (string) $value ) ) ); if ( ! $vals ) { return array( 'sql' => 'NOT IN' === $op ? '1=1' : '1=0', 'params' => array() ); } return array( 'sql' => $expr . ' ' . $op . ' (' . implode( ',', array_fill( 0, count( $vals ), '%s' ) ) . ')', 'params' => array_values( $vals ) ); }
        if ( in_array( $op, array( 'LIKE', 'NOT LIKE' ), true ) ) { return array( 'sql' => $expr . ' ' . $op . ' %s', 'params' => array( '%' . $wpdb->esc_like( (string) $value ) . '%' ) ); }
        if ( ! in_array( $op, array( '=', '!=', '>', '>=', '<', '<=' ), true ) ) { $op = '='; }
        return array( 'sql' => $expr . ' ' . $op . ' %s', 'params' => array( $value ) );
    }

    private function compile_sort( array $sorts ): array {
        global $wpdb; $parts = array(); $params = array();
        foreach ( $sorts as $sort ) { $path=sanitize_text_field((string)($sort['path']??''));$order='ASC'===strtoupper((string)($sort['order']??'DESC'))?'ASC':'DESC';if($path){$compiled=(new \CGM\Core\Query\PathQueryCompiler($this->core))->sort($path,'user','u.ID',!empty($sort['numeric']));if($compiled){$parts[]=$compiled['sql'].' '.$order;$params=array_merge($params,$compiled['params']);}continue;} $field = $this->core->fields()->get( (string) ( $sort['field'] ?? '' ) ); if ( ! $field || empty( $field['sortable'] ) || ! $this->field_applies($field,'user') ) { continue; } $order = 'ASC' === strtoupper( (string) ( $sort['order'] ?? 'DESC' ) ) ? 'ASC' : 'DESC'; $source = (string) ( $field['source'] ?? '' ); if ( 'wordpress-user' === ( $field['provider'] ?? '' ) && in_array( $source, array( 'ID','user_login','user_email','display_name','user_registered','user_url' ), true ) ) { $parts[] = 'u.`' . $source . '` ' . $order; } elseif ( $source && 'role' !== $source ) { $parts[] = "(SELECT um_sort.meta_value FROM {$wpdb->usermeta} um_sort WHERE um_sort.user_id=u.ID AND um_sort.meta_key=%s ORDER BY um_sort.umeta_id DESC LIMIT 1) {$order}"; $params[] = $source; } }
        if ( ! $parts ) { $parts[] = 'u.display_name ASC'; } $parts[] = 'u.ID ASC'; return array( 'sql' => implode( ', ', $parts ), 'params' => $params );
    }
    private function field_applies(array $field,string $content_type):bool{$types=(array)($field['content_types']??array('*'));return in_array('*',$types,true)||in_array($content_type,$types,true);}
    private function prepare( string $sql, array $params ): string { global $wpdb; return $params ? $wpdb->prepare( $sql, ...$params ) : $sql; }
}
