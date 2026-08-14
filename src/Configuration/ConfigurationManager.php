<?php
namespace CGM\Core\Configuration;
use CGM\Core\Query\SavedQueryRepository;use CGM\Core\Relationships\ConfiguredRelationshipRepository;
final class ConfigurationManager {
    private const BACKUPS='cgm_core_config_backups';
    private const IMPORT_STATE='cgm_core_config_import_state';
    public function __construct(private SavedQueryRepository $queries,private ConfiguredRelationshipRepository $relationships){}
    public function register_recovery():void{add_action('admin_init',array($this,'recover_interrupted_import'),1);}
    public function pending_import():array{$state=get_option(self::IMPORT_STATE,array());return is_array($state)?$state:array();}
    public function recover_interrupted_import():void{$state=$this->pending_import();if(empty($state['backup'])||empty($state['started']))return;if(time()-absint($state['started'])<30)return;$this->restore_snapshot((string)$state['backup']);delete_option(self::IMPORT_STATE);do_action('cgm_core/configuration_recovered',$state);}
    public function register_code_configuration():void{do_action('cgm_core/configuration/register',$this);do_action('cgm_core/register_configuration',$this);}
    public function register_query(string $id,array $definition,array $args=array()):void{$this->queries->register_code($id,$definition,$args);}
    public function register_relationship(array $definition):void{$this->relationships->register_code($definition);}
    public function code_queries():array{return $this->queries->code_definitions();}
    public function export():array{$queries=array();foreach($this->queries->list() as $q){if('database'!==($q['managed_by']??''))continue;$queries[]=array('slug'=>$q['slug'],'title'=>$q['title'],'public'=>$q['public'],'definition'=>$q['definition']);}$rels=array_values($this->relationships->stored());return array('schema'=>CGM_CORE_CONFIG_SCHEMA,'generated'=>gmdate(DATE_ATOM),'core_version'=>CGM_CORE_VERSION,'api_version'=>CGM_CORE_API_VERSION,'relationships'=>$rels,'queries'=>$queries,'code_managed'=>array('queries'=>array_values(array_map(static fn($q)=>array('slug'=>$q['slug'],'title'=>$q['title'],'source'=>$q['source']??'plugin'),$this->queries->code_definitions())),'relationships'=>array_values(array_map(static fn($r)=>array('id'=>$r['id'],'label'=>$r['label'],'provider'=>$r['provider']??'code'),$this->relationships->code_definitions()))));}
    public function validate(array $data):array{$errors=array();$warnings=array();if((string)($data['schema']??'')!==(string)CGM_CORE_CONFIG_SCHEMA)$errors[]='Unsupported configuration schema.';if(!isset($data['queries'])||!is_array($data['queries']))$errors[]='Queries array is missing.';if(!isset($data['relationships'])||!is_array($data['relationships']))$errors[]='Relationships array is missing.';foreach((array)($data['queries']??array()) as $i=>$q){if(!is_array($q)||empty($q['slug'])||!isset($q['definition'])||!is_array($q['definition'])){$errors[]='Invalid query at index '.$i.'.';continue;}$slug=sanitize_title((string)$q['slug']);if(isset($this->queries->code_definitions()[$slug]))$warnings[]='Query '.$slug.' is code-managed and will not be overwritten.';}foreach((array)($data['relationships']??array()) as $i=>$r){if(!is_array($r)||empty($r['id'])||empty($r['source_type'])||empty($r['target_type'])){$errors[]='Invalid relationship at index '.$i.'.';continue;}$id=sanitize_key((string)$r['id']);if(isset($this->relationships->code_definitions()[$id]))$warnings[]='Relationship '.$id.' is code-managed and will not be overwritten.';}return array('valid'=>!$errors,'errors'=>$errors,'warnings'=>$warnings,'counts'=>array('queries'=>count((array)($data['queries']??array())),'relationships'=>count((array)($data['relationships']??array()))));}
    public function diff(array $data,string $mode='merge'):array{return $this->preview($data,$mode);}
    public function preview(array $data,string $mode='merge'):array{$mode='replace'===$mode?'replace':'merge';$check=$this->validate($data);if(!$check['valid'])return array('valid'=>false,'errors'=>$check['errors'],'warnings'=>$check['warnings'],'diff'=>array());$current=$this->export();$diff=array('queries'=>array('add'=>array(),'update'=>array(),'remove'=>array(),'unchanged'=>array(),'protected'=>array()),'relationships'=>array('add'=>array(),'update'=>array(),'remove'=>array(),'unchanged'=>array(),'protected'=>array()));$curQ=array();foreach($current['queries'] as $q)$curQ[$q['slug']]=$q;$newQ=array();foreach($data['queries'] as $q){$slug=sanitize_title((string)$q['slug']);if(isset($this->queries->code_definitions()[$slug])){$diff['queries']['protected'][]=$slug;continue;}$newQ[$slug]=$q;if(!isset($curQ[$slug]))$diff['queries']['add'][]=$slug;elseif($this->hash($curQ[$slug])!==$this->hash($q))$diff['queries']['update'][]=$slug;else$diff['queries']['unchanged'][]=$slug;}if('replace'===$mode)foreach(array_diff(array_keys($curQ),array_keys($newQ)) as $slug)$diff['queries']['remove'][]=$slug;$curR=array();foreach($current['relationships'] as $r)$curR[$r['id']]=$r;$newR=array();foreach($data['relationships'] as $r){$id=sanitize_key((string)$r['id']);if(isset($this->relationships->code_definitions()[$id])){$diff['relationships']['protected'][]=$id;continue;}$newR[$id]=$r;if(!isset($curR[$id]))$diff['relationships']['add'][]=$id;elseif($this->hash($curR[$id])!==$this->hash($r))$diff['relationships']['update'][]=$id;else$diff['relationships']['unchanged'][]=$id;}if('replace'===$mode)foreach(array_diff(array_keys($curR),array_keys($newR)) as $id)$diff['relationships']['remove'][]=$id;return array('valid'=>true,'errors'=>array(),'warnings'=>$check['warnings'],'mode'=>$mode,'counts'=>$check['counts'],'diff'=>$diff,'destructive'=>!empty($diff['queries']['remove'])||!empty($diff['relationships']['remove']));}
    public function import(array $data,string $mode='merge',bool $dry_run=false):array{
        $preview=$this->preview($data,$mode);
        if(empty($preview['valid']))return array('success'=>false,'errors'=>$preview['errors']??array('Validation failed.'),'preview'=>$preview);
        if($dry_run)return array('success'=>true,'dry_run'=>true,'preview'=>$preview);
        $backup=$this->snapshot();
        update_option(self::IMPORT_STATE,array('backup'=>$backup,'started'=>time(),'mode'=>$mode,'status'=>'applying'),false);
        try{
            $existingR=$this->relationships->stored();
            $nextR='replace'===$mode?array():$existingR;
            foreach((array)$data['relationships'] as $r){if(!is_array($r)||empty($r['id']))continue;$id=sanitize_key((string)$r['id']);if(isset($this->relationships->code_definitions()[$id]))continue;$nextR[$id]=$r;}
            if(!$this->relationships->save($nextR))throw new \RuntimeException('Relationship configuration could not be saved.');
            if('replace'===$mode){$incoming=array_map(static fn($q)=>sanitize_title((string)($q['slug']??'')),(array)$data['queries']);foreach($this->queries->all() as $p)if(!in_array($p->post_name,$incoming,true))wp_delete_post($p->ID,true);}
            $count=0;
            foreach((array)$data['queries'] as $q){
                if(!is_array($q))continue;$slug=sanitize_title((string)($q['slug']??''));if(isset($this->queries->code_definitions()[$slug]))continue;
                $existing=$this->queries->find($slug);
                $id=$this->queries->save(array('id'=>is_array($existing)&&'database'===($existing['managed_by']??'')?absint($existing['id']):0,'title'=>sanitize_text_field((string)($q['title']??'Imported Query')),'slug'=>$slug,'public'=>!empty($q['public']),'definition'=>(array)($q['definition']??array())));
                if(is_wp_error($id))throw new \RuntimeException($id->get_error_message());
                $count++;
            }
            $verify=$this->preview($data,$mode);
            $verified=empty($verify['diff']['queries']['add'])&&empty($verify['diff']['queries']['update'])&&empty($verify['diff']['queries']['remove'])&&empty($verify['diff']['relationships']['add'])&&empty($verify['diff']['relationships']['update'])&&empty($verify['diff']['relationships']['remove']);
            if(!$verified)throw new \RuntimeException('Configuration verification did not match the requested state.');
            delete_option(self::IMPORT_STATE);
            do_action('cgm_core/configuration_imported',$mode,$preview,true);
            return array('success'=>true,'queries'=>$count,'relationships'=>count($nextR),'backup'=>$backup,'preview'=>$preview,'verified'=>true);
        }catch(\Throwable $e){
            $this->restore_snapshot($backup);
            delete_option(self::IMPORT_STATE);
            do_action('cgm_core/configuration_import_failed',$mode,$preview,$e);
            return array('success'=>false,'errors'=>array($e->getMessage()),'backup'=>$backup,'rolled_back'=>true,'preview'=>$preview);
        }
    }
    public function snapshot():string{$id=gmdate('Ymd-His').'-'.wp_generate_password(6,false,false);$b=$this->backups();$b[$id]=array('created'=>gmdate(DATE_ATOM),'config'=>$this->export());if(count($b)>5)$b=array_slice($b,-5,null,true);update_option(self::BACKUPS,$b,false);return $id;}
    public function backups():array{$b=get_option(self::BACKUPS,array());return is_array($b)?$b:array();}
    public function rollback(string $id):array{$b=$this->backups();if(empty($b[$id]['config']))return array('success'=>false,'errors'=>array('Backup not found.'));$result=$this->import((array)$b[$id]['config'],'replace',false);$result['rollback_from']=$id;return $result;}
    private function restore_snapshot(string $id):void{$b=$this->backups();if(empty($b[$id]['config']))return;$cfg=(array)$b[$id]['config'];$this->relationships->save((array)$cfg['relationships']);foreach($this->queries->all() as $p)wp_delete_post($p->ID,true);foreach((array)$cfg['queries'] as $q)$this->queries->save(array('title'=>$q['title'],'slug'=>$q['slug'],'public'=>$q['public'],'definition'=>$q['definition']));}
    private function hash(array $v):string{unset($v['generated'],$v['core_version'],$v['api_version'],$v['managed_by']);ksort($v);return md5(wp_json_encode($v));}
}
