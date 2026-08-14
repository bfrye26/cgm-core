<?php
namespace CGM\Core\Relationships;

use CGM\Core\Contracts\QueryableRelationshipStoreInterface;

class CoreRelationshipStore implements QueryableRelationshipStoreInterface {
    private function table(): string { global $wpdb; return $wpdb->prefix . 'cgm_core_relationships'; }
    public function get( string $relationship, string $source_type, int $source_id, array $args = array() ): array {
        global $wpdb; $where = '';
        if ( isset( $args['primary'] ) ) { $where .= $wpdb->prepare( ' AND is_primary=%d', ! empty( $args['primary'] ) ? 1 : 0 ); }
        if ( ! empty( $args['role'] ) ) { $where .= $wpdb->prepare( ' AND role=%s', sanitize_key( (string) $args['role'] ) ); }
        $sql = $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE relationship_key=%s AND source_type=%s AND source_id=%d{$where} ORDER BY is_primary DESC, sort_order ASC, id ASC", $relationship, $source_type, $source_id );
        return array_map( array( $this, 'hydrate' ), $wpdb->get_results( $sql, ARRAY_A ) ?: array() );
    }
    public function get_reverse( string $relationship, string $target_type, int $target_id, array $args = array() ): array {
        global $wpdb; $where = '';
        if ( isset( $args['primary'] ) ) { $where .= $wpdb->prepare( ' AND is_primary=%d', ! empty( $args['primary'] ) ? 1 : 0 ); }
        if ( ! empty( $args['role'] ) ) { $where .= $wpdb->prepare( ' AND role=%s', sanitize_key( (string) $args['role'] ) ); }
        $sql = $wpdb->prepare( "SELECT * FROM {$this->table()} WHERE relationship_key=%s AND target_type=%s AND target_id=%d{$where} ORDER BY is_primary DESC, sort_order ASC, id ASC", $relationship, $target_type, $target_id );
        return array_map( array( $this, 'hydrate' ), $wpdb->get_results( $sql, ARRAY_A ) ?: array() );
    }
    public function replace( string $relationship, string $source_type, int $source_id, string $target_type, array $items ): bool {
        global $wpdb; $table = $this->table(); $wpdb->query( 'START TRANSACTION' );
        try {
            $deleted = $wpdb->delete( $table, array( 'relationship_key'=>$relationship, 'source_type'=>$source_type, 'source_id'=>$source_id ), array( '%s','%s','%d' ) );
            if ( false === $deleted ) { throw new \RuntimeException( 'Relationship delete failed.' ); }
            $now = current_time( 'mysql', true );
            foreach ( array_values( $items ) as $index => $item ) {
                $target_id = absint( is_array( $item ) ? ( $item['id'] ?? $item['target_id'] ?? 0 ) : $item ); if ( ! $target_id ) { continue; }
                $meta = is_array( $item ) ? (array) ( $item['meta'] ?? array() ) : array();
                foreach ( array( 'display', 'status', 'notes' ) as $key ) { if ( is_array( $item ) && array_key_exists( $key, $item ) ) { $meta[ $key ] = $item[ $key ]; } }
                $ok = $wpdb->insert( $table, array(
                    'relationship_key'=>$relationship,'source_type'=>$source_type,'source_id'=>$source_id,'target_type'=>$target_type,'target_id'=>$target_id,
                    'role'=>sanitize_key( is_array( $item ) ? (string) ( $item['role'] ?? '' ) : '' ), 'sort_order'=>is_array( $item ) ? intval( $item['order'] ?? $item['sort_order'] ?? $index ) : $index,
                    'is_primary'=>is_array( $item ) && ( ! empty( $item['primary'] ) || ! empty( $item['is_primary'] ) ) ? 1 : 0, 'meta'=>$meta ? wp_json_encode( $meta ) : null, 'created_at'=>$now, 'updated_at'=>$now,
                ), array( '%s','%s','%d','%s','%d','%s','%d','%d','%s','%s','%s' ) );
                if ( false === $ok ) { throw new \RuntimeException( 'Relationship insert failed.' ); }
            }
            $wpdb->query( 'COMMIT' ); return true;
        } catch ( \Throwable $e ) { $wpdb->query( 'ROLLBACK' ); return false; }
    }
    public function count_for_object( string $relationship, string $content_type, int $object_id ): int {
        global $wpdb; return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table()} WHERE relationship_key=%s AND ((source_type=%s AND source_id=%d) OR (target_type=%s AND target_id=%d))",
            $relationship, $content_type, $object_id, $content_type, $object_id
        ) );
    }
    public function delete_for_object( string $relationship, string $content_type, int $object_id ): int|false {
        global $wpdb; $result = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$this->table()} WHERE relationship_key=%s AND ((source_type=%s AND source_id=%d) OR (target_type=%s AND target_id=%d))",
            $relationship, $content_type, $object_id, $content_type, $object_id
        ) ); return false === $result ? false : (int) $result;
    }

    /* ── Integrity scan helpers (admin-only audit surface) ───────────── */
    public function count_links( string $relationship ): int {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$this->table()} WHERE relationship_key=%s", $relationship ) );
    }
    public function distinct_targets( string $relationship, int $limit = 5000 ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT target_type, target_id FROM {$this->table()} WHERE relationship_key=%s GROUP BY target_type, target_id LIMIT %d", $relationship, $limit ), ARRAY_A ) ?: array();
    }
    public function distinct_sources( string $relationship, int $limit = 5000 ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT source_type, source_id FROM {$this->table()} WHERE relationship_key=%s GROUP BY source_type, source_id LIMIT %d", $relationship, $limit ), ARRAY_A ) ?: array();
    }
    public function source_target_counts( string $relationship, int $limit = 5000 ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( "SELECT source_type, source_id, COUNT(*) cnt FROM {$this->table()} WHERE relationship_key=%s GROUP BY source_type, source_id HAVING cnt > 1 ORDER BY cnt DESC LIMIT %d", $relationship, $limit ), ARRAY_A ) ?: array();
    }
    public function purge_missing_targets( string $relationship, string $target_type, array $target_ids ): int|false {
        if ( ! $target_ids ) { return 0; }
        global $wpdb; $placeholders = implode( ',', array_fill( 0, count( $target_ids ), '%d' ) );
        $result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->table()} WHERE relationship_key=%s AND target_type=%s AND target_id IN ({$placeholders})", array_merge( array( $relationship, $target_type ), array_map( 'absint', $target_ids ) ) ) );
        return false === $result ? false : (int) $result;
    }
    public function matching_source_ids( string $relationship, string $source_type, string $target_type, string $operator, mixed $value ): array {
        global $wpdb; $table=$this->table(); $operator=strtoupper($operator); $ids=$this->ids($value);
        if ( in_array( $operator, array('EXISTS','NOT EXISTS'), true ) ) {
            $matched = array_map( 'absint', (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT source_id FROM {$table} WHERE relationship_key=%s AND source_type=%s", $relationship, $source_type ) ) );
            return 'EXISTS' === $operator ? $matched : array();
        }
        if ( ! $ids ) { return array(); }
        $placeholders=implode(',',array_fill(0,count($ids),'%d')); $params=array_merge(array($relationship,$source_type,$target_type),$ids);
        return array_map('absint',$wpdb->get_col($wpdb->prepare("SELECT DISTINCT source_id FROM {$table} WHERE relationship_key=%s AND source_type=%s AND target_type=%s AND target_id IN ({$placeholders})",...$params)));
    }
    public function sql_condition( string $relationship, string $source_type, string $target_type, string $operator, mixed $value, string $source_expression ): ?array {
        return $this->sql_property_condition( $relationship, $source_type, $target_type, 'target_id', $operator, $value, $source_expression );
    }
    public function sql_property_condition( string $relationship, string $source_type, string $target_type, string $property, string $operator, mixed $value, string $source_expression ): ?array {
        global $wpdb; $operator=strtoupper($operator); $property=sanitize_text_field($property); $base="SELECT 1 FROM {$this->table()} cr WHERE cr.relationship_key=%s AND cr.source_type=%s AND cr.source_id={$source_expression}"; $params=array($relationship,$source_type);
        if('EXISTS'===$operator||'NOT EXISTS'===$operator){$expr=$this->property_expression($property,'cr');if('target_id'===$property||'target'===$property){$sql="EXISTS ({$base})";}else{$sql="EXISTS ({$base} AND {$expr} IS NOT NULL AND {$expr} <> '')";}return array('sql'=>'NOT EXISTS'===$operator?'NOT '.$sql:$sql,'params'=>$params);}
        if(in_array($property,array('target','target_id'),true)){$expr='cr.target_id';$params[]=$target_type;$base.=' AND cr.target_type=%s';$value=$this->ids($value);if(!$value)return array('sql'=>in_array($operator,array('!=','NOT IN'),true)?'1=1':'1=0','params'=>array());}
        else{$expr=$this->property_expression($property,'cr');}
        $positive=in_array($operator,array('!=','NOT IN','NOT LIKE','NOT BETWEEN'),true);$testop=array('!='=>'=','NOT IN'=>'IN','NOT LIKE'=>'LIKE','NOT BETWEEN'=>'BETWEEN')[$operator]??$operator;$cond=$this->scalar($expr,$testop,$value);$sql="EXISTS ({$base} AND {$cond['sql']})";if($positive)$sql='NOT '.$sql;return array('sql'=>$sql,'params'=>array_merge($params,$cond['params']));
    }
    public function sql_wrap_condition( string $relationship, string $source_type, string $target_type, string $selector, string $child_sql, array $child_params, string $source_expression ): ?array {
        $selector=sanitize_key($selector);$base="SELECT 1 FROM {$this->table()} cr WHERE cr.relationship_key=%s AND cr.source_type=%s AND cr.source_id={$source_expression} AND cr.target_type=%s";$params=array($relationship,$source_type,$target_type);if('primary'===$selector)$base.=' AND cr.is_primary=1';$child=str_replace('{{TARGET_ID}}','cr.target_id',$child_sql);return array('sql'=>"EXISTS ({$base} AND ({$child}))",'params'=>array_merge($params,$child_params));
    }
    public function sql_sort_expression( string $relationship, string $source_type, string $target_type, string $property, string $selector, string $source_expression ): ?array {
        $selector=sanitize_key($selector);$expr=$this->property_expression($property,'cr');$where="cr.relationship_key=%s AND cr.source_type=%s AND cr.source_id={$source_expression} AND cr.target_type=%s";$params=array($relationship,$source_type,$target_type);if('primary'===$selector)$where.=' AND cr.is_primary=1';return array('sql'=>"(SELECT {$expr} FROM {$this->table()} cr WHERE {$where} ORDER BY cr.is_primary DESC, cr.sort_order ASC, cr.id ASC LIMIT 1)",'params'=>$params);
    }

    public function sql_reverse_condition( string $relationship, string $operator, mixed $value, string $target_expression ): ?array {
        global $wpdb;
        $operator = strtoupper( $operator );
        $base = "SELECT 1 FROM {$this->table()} cr WHERE cr.relationship_key=%s AND cr.target_id={$target_expression}";
        $params = array( $relationship );
        if ( in_array( $operator, array( 'EXISTS', 'NOT EXISTS' ), true ) ) {
            return array( 'sql' => ( 'NOT EXISTS' === $operator ? 'NOT ' : '' ) . "EXISTS ({$base})", 'params' => $params );
        }
        $ids = $this->ids( $value );
        if ( ! $ids ) { return array( 'sql' => in_array( $operator, array( '!=', 'NOT IN' ), true ) ? '1=1' : '1=0', 'params' => array() ); }
        $ph = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
        $sql = "EXISTS ({$base} AND cr.source_id IN ({$ph}))";
        if ( in_array( $operator, array( '!=', 'NOT IN' ), true ) ) { $sql = 'NOT ' . $sql; }
        return array( 'sql' => $sql, 'params' => array_merge( $params, $ids ) );
    }

    public function sql_count_condition( string $relationship, string $operator, mixed $value, string $expression, bool $reverse ): ?array {
        global $wpdb;
        $operator = strtoupper( $operator );
        if ( ! in_array( $operator, array( '=', '!=', '>', '>=', '<', '<=' ), true ) ) { $operator = '='; }
        $col = $reverse ? 'target_id' : 'source_id';
        return array(
            'sql' => " (SELECT COUNT(*) FROM {$this->table()} cr WHERE cr.relationship_key=%s AND cr.{$col}={$expression}) {$operator} %d ",
            'params' => array( $relationship, max( 0, (int) $value ) ),
        );
    }
    private function property_expression(string $property,string $alias):string{$p=sanitize_key(str_replace(array('meta.','meta:'),'',$property));return match($property){'role'=>"{$alias}.role",'primary','is_primary'=>"{$alias}.is_primary",'order','sort_order'=>"{$alias}.sort_order",'target','target_id'=>"{$alias}.target_id",'source','source_id'=>"{$alias}.source_id",default=>"JSON_UNQUOTE(JSON_EXTRACT({$alias}.meta, '$.{$p}'))"};}
    private function scalar(string $expr,string $op,mixed $value):array{global $wpdb;$op=strtoupper($op);if(in_array($op,array('IN','NOT IN'),true)){$vals=is_array($value)?$value:array_filter(array_map('trim',explode(',',(string)$value)));if(!$vals)return array('sql'=>'NOT IN'===$op?'1=1':'1=0','params'=>array());return array('sql'=>$expr.' '.$op.' ('.implode(',',array_fill(0,count($vals),'%s')).')','params'=>array_values($vals));}if(in_array($op,array('BETWEEN','NOT BETWEEN'),true)){$vals=is_array($value)?array_values($value):array_map('trim',explode(',',(string)$value));if(count($vals)<2)return array('sql'=>'NOT BETWEEN'===$op?'1=1':'1=0','params'=>array());return array('sql'=>$expr.' '.$op.' %s AND %s','params'=>array($vals[0],$vals[1]));}if(in_array($op,array('LIKE','NOT LIKE'),true))return array('sql'=>$expr.' '.$op.' %s','params'=>array('%'.$wpdb->esc_like((string)$value).'%'));if(!in_array($op,array('=','!=','>','>=','<','<='),true))$op='=';return array('sql'=>$expr.' '.$op.' %s','params'=>array(is_bool($value)?($value?'1':'0'):$value));}
    private function ids(mixed $value):array{return array_values(array_filter(array_map('absint',is_array($value)?$value:array_filter(array_map('trim',explode(',',(string)$value))))));}
    private function hydrate( array $row ): array { $row['source_id']=(int)$row['source_id'];$row['target_id']=(int)$row['target_id'];$row['sort_order']=(int)$row['sort_order'];$row['is_primary']=(bool)$row['is_primary'];$row['primary']=$row['is_primary'];$row['order']=$row['sort_order'];$row['meta']=$row['meta']?(json_decode($row['meta'],true)?:array()):array();foreach(array('display','status','notes') as $k){if(array_key_exists($k,$row['meta']))$row[$k]=$row['meta'][$k];}return $row; }
}
