<?php
namespace CGM\Core\Health;

use CGM\Core\Plugin;

final class SiteHealth {
    public function __construct( private Plugin $core ) {}

    public function register(): void { add_filter( 'site_status_tests', array( $this, 'tests' ) ); }

    public function tests( array $tests ): array {
        $tests['direct']['cgm_core_registry'] = array( 'label'=>__( 'CGM Core registry', 'cgm-core' ), 'test'=>array($this,'registry') );
        $tests['direct']['cgm_core_providers'] = array( 'label'=>__( 'CGM Core provider compatibility', 'cgm-core' ), 'test'=>array($this,'providers') );
        $tests['direct']['cgm_core_schema'] = array( 'label'=>__( 'CGM Core storage schema', 'cgm-core' ), 'test'=>array($this,'schema') );
        $tests['direct']['cgm_core_configuration'] = array( 'label'=>__( 'CGM Core configuration recovery', 'cgm-core' ), 'test'=>array($this,'configuration') );
        $tests['direct']['cgm_core_suite'] = array( 'label'=>__( 'CGM suite health', 'cgm-core' ), 'test'=>array($this,'suite') );
        return $tests;
    }

    /** Aggregate the legacy `cgm_core_health_checks` filter used by suite plugins. */
    public function suite(): array {
        $checks = apply_filters( 'cgm_core_health_checks', array() );
        $checks = is_array( $checks ) ? $checks : array();
        $bad = array_filter( $checks, static fn( $c ) => is_array( $c ) && ! empty( $c['status'] ) && ! in_array( $c['status'], array( 'healthy', 'good', 'complete' ), true ) );
        $lines = array();
        foreach ( $checks as $c ) {
            if ( is_array( $c ) && ! empty( $c['id'] ) ) { $lines[] = (string) ( $c['label'] ?? $c['id'] ) . ': ' . (string) ( $c['status'] ?? '' ); }
        }
        return $this->result(
            $bad ? __( 'CGM suite plugins report issues', 'cgm-core' ) : __( 'CGM suite plugins are healthy', 'cgm-core' ),
            $bad ? 'recommended' : 'good',
            $lines ? implode( '; ', $lines ) : __( 'No suite health checks were registered.', 'cgm-core' ),
            'cgm_core_suite'
        );
    }

    public function registry(): array {
        $providers=count($this->core->providers()->all());
        $queryable=0;foreach($this->core->content_types()->all() as $ct){if($this->core->query_providers()->for_content_type($ct))$queryable++;}
        $good=$providers>0&&$queryable>0;
        return $this->result(
            $good?__( 'CGM Core registries are ready', 'cgm-core' ):__( 'CGM Core registries are incomplete', 'cgm-core' ),
            $good?'good':'critical',
            sprintf( __( '%1$d providers, %2$d content types, %3$d fields, %4$d relationships, and %5$d queryable content types are registered.', 'cgm-core' ), $providers,count($this->core->content_types()->all()),count($this->core->fields()->all()),count($this->core->relationships()->all()),$queryable ),
            'cgm_core_registry'
        );
    }

    public function providers(): array {
        $report=$this->core->providers()->dependency_report();$bad=array();$optional=array();
        foreach($report as $id=>$row){if(empty($row['compatible']))$bad[]=$id;if(!empty($row['optional_missing']))$optional[]=$id;}
        $status=$bad?'critical':($optional?'recommended':'good');
        $message=$bad?sprintf(__('Incompatible providers: %s.','cgm-core'),implode(', ',$bad)):($optional?sprintf(__('Providers with optional integrations missing: %s.','cgm-core'),implode(', ',$optional)):__('All registered providers satisfy their required dependencies.','cgm-core'));
        return $this->result( $bad?__( 'CGM Core has incompatible providers','cgm-core' ):__( 'CGM Core provider contracts are healthy','cgm-core' ), $status, $message, 'cgm_core_providers' );
    }

    public function schema(): array {
        global $wpdb;$table=$wpdb->prefix.'cgm_core_relationships';
        $exists=(string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s',$wpdb->esc_like($table)))===$table;
        $version=(string)get_option('cgm_core_schema_version','');$current=(string)CGM_CORE_SCHEMA_VERSION;
        $good=$exists&&$version===$current;
        return $this->result($good?__('CGM Core storage schema is current','cgm-core'):__('CGM Core storage schema needs attention','cgm-core'),$good?'good':'critical',sprintf(__('Relationship table: %1$s. Stored schema: %2$s; expected: %3$s.','cgm-core'),$exists?__('present','cgm-core'):__('missing','cgm-core'),$version?:__('none','cgm-core'),$current),'cgm_core_schema');
    }

    public function configuration(): array {
        $pending=$this->core->configuration()->pending_import();$good=empty($pending);
        return $this->result($good?__('CGM Core configuration state is clean','cgm-core'):__('CGM Core has an interrupted configuration operation','cgm-core'),$good?'good':'recommended',$good?__('No interrupted configuration import is waiting for recovery.','cgm-core'):__('An import journal remains. CGM Core will restore its recorded backup when the recovery grace period is reached.','cgm-core'),'cgm_core_configuration');
    }

    private function result(string $label,string $status,string $description,string $test):array{return array('label'=>$label,'status'=>$status,'badge'=>array('label'=>'CGM Core','color'=>'blue'),'description'=>'<p>'.esc_html($description).'</p>','actions'=>'','test'=>$test);}
}
