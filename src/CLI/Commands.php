<?php
namespace CGM\Core\CLI;

use CGM\Core\Plugin;

/** Operational CLI for registry, queries, objects, relationships and configuration sync. */
final class Commands {
    public function __construct( private Plugin $core ) {}

    public function register(): void {
        if ( ! defined('WP_CLI') || ! WP_CLI ) { return; }
        \WP_CLI::add_command('cgm-core registry',fn($args,$assoc)=>$this->registry($assoc));
        \WP_CLI::add_command('cgm-core query',fn($args,$assoc)=>$this->query($args,$assoc));
        \WP_CLI::add_command('cgm-core object',fn($args,$assoc)=>$this->object($args,$assoc));
        \WP_CLI::add_command('cgm-core relationships',fn($args,$assoc)=>$this->relationships($args,$assoc));
        \WP_CLI::add_command('cgm-core config export',fn($args,$assoc)=>$this->config_export($assoc));
        \WP_CLI::add_command('cgm-core config diff',fn($args,$assoc)=>$this->config_diff($args,$assoc));
        \WP_CLI::add_command('cgm-core config import',fn($args,$assoc)=>$this->config_import($args,$assoc));
        \WP_CLI::add_command('cgm-core config backups',fn()=>\WP_CLI::line(wp_json_encode($this->core->configuration()->backups(),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)));
        \WP_CLI::add_command('cgm-core config rollback',fn($args)=>$this->config_rollback($args));
        \WP_CLI::add_command('cgm-core health',fn()=>\WP_CLI::line(wp_json_encode($this->health_payload(),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)));
        \WP_CLI::add_command('cgm-core workflow list',fn()=>\WP_CLI::line(wp_json_encode($this->core->workflow()->states(),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)));
        \WP_CLI::add_command('cgm-core workflow set',fn($args)=>$this->workflow_set($args));
        \WP_CLI::add_command('cgm-core indexes',fn()=>\WP_CLI::line(wp_json_encode(array_values($this->core->indexes()->all()),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)));
        \WP_CLI::add_command('cgm-core indexes rebuild',fn()=>\WP_CLI::success(sprintf('%d index(es) queued.',(new \CGM\Core\Index\IndexManager($this->core))->rebuild())));
        \WP_CLI::add_command('cgm-core rules',fn()=>\WP_CLI::line(wp_json_encode(array_values($this->core->rules()->all()),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)));
        \WP_CLI::add_command('cgm-core search',fn($args,$assoc)=>$this->search($args,$assoc));
        \WP_CLI::add_command('cgm-core bulk',fn($args,$assoc)=>$this->bulk($args,$assoc));
    }

    private function workflow_set(array $args):void{$id=absint($args[0]??0);$state=sanitize_key((string)($args[1]??''));if(!$id||!$state)\WP_CLI::error('Usage: wp cgm-core workflow set <post_id> <state>');$this->core->workflow()->transition($id,$state)?\WP_CLI::success('State set.'):\WP_CLI::error('Transition failed.');}
    private function search(array $args,array $assoc):void{$q=sanitize_text_field((string)($args[0]??''));$type=sanitize_key((string)($assoc['content_type']??'post'));\WP_CLI::line(wp_json_encode($this->core->search()->search($q,array('content_type'=>$type,'page'=>absint($assoc['page']??1),'per_page'=>absint($assoc['per_page']??20))),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));}
    private function bulk(array $args,array $assoc):void{$id=sanitize_text_field((string)($args[0]??''));$json=(string)($assoc['actions']??'');if(!$id||!$json)\WP_CLI::error('Usage: wp cgm-core bulk <query_id> --actions=<json>');$actions=json_decode($json,true);if(!is_array($actions))\WP_CLI::error('--actions must be valid JSON.');$preview=!isset($assoc['apply']);if($preview)$r=(new \CGM\Core\Bulk\BulkManager($this->core))->preview($id);else$r=(new \CGM\Core\Bulk\BulkManager($this->core))->run($id,$actions);\WP_CLI::line(wp_json_encode($r,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));if($preview)\WP_CLI::success('Preview. Re-run with --apply to run.');}

    private function registry(array $assoc):void{$section=sanitize_key((string)($assoc['section']??'all'));$data=array('providers'=>$this->core->providers()->all(),'compatibility'=>$this->core->providers()->dependency_report(),'content_types'=>$this->core->content_types()->all(),'fields'=>$this->core->fields()->all(),'relationships'=>$this->core->relationships()->all(),'builders'=>$this->core->builders()->all(),'query_providers'=>array_keys($this->core->query_providers()->all()),'context'=>$this->core->context()->tokens());\WP_CLI::line(wp_json_encode('all'===$section?$data:($data[$section]??array()),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));}

    private function query(array $args,array $assoc):void{$id=$args[0]??'';if(''===(string)$id)\WP_CLI::error('Provide a saved query slug/ID.');$context=array('consumer'=>'wp-cli');if(isset($assoc['post_id']))$context['post_id']=absint($assoc['post_id']);if(isset($assoc['public']))$context['public_request']=true;if(isset($assoc['explain'])){\WP_CLI::line(wp_json_encode($this->core->queries()->explain($id,$context),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));return;}$r=$this->core->queries()->run($id,$context);$rows=$this->serialize_items($r->items,(string)($r->debug['normalized']['content_type']??''));if(isset($assoc['format'])&&'json'===$assoc['format']){\WP_CLI::line(wp_json_encode(array('total'=>$r->total,'items'=>$rows,'debug'=>$r->debug),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));return;}\WP_CLI::success(sprintf('%d result(s).',$r->total));foreach($rows as $row)\WP_CLI::line($row['content_type'].':'.$row['id']."\t".$row['label']);}

    private function object(array $args,array $assoc):void{$type=sanitize_key((string)($args[0]??''));$id=absint($args[1]??0);if(!$type||!$id)\WP_CLI::error('Usage: wp cgm-core object <content_type> <id>');$ref=$this->core->objects()->reference($id,$type);if(!$ref||!$this->core->objects()->exists($ref))\WP_CLI::error('Object not found.');$payload=$this->core->objects()->serialize($ref);$payload['fields']=array();foreach($this->core->fields()->for_content_type($type) as $field)$payload['fields'][$field['id']]=$this->core->dynamic_data()->resolve((string)$field['id'],$ref);\WP_CLI::line(wp_json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));}

    private function relationships(array $args,array $assoc):void{$rel=sanitize_key((string)($args[0]??''));$id=absint($args[1]??0);if(!$rel||!$id)\WP_CLI::error('Usage: wp cgm-core relationships <relationship> <source_id> [--source_type=post] [--reverse]');if(isset($assoc['reverse']))$rows=$this->core->relationships()->get_reverse($rel,$id,array('public_only'=>isset($assoc['public'])));else$rows=$this->core->relationships()->get($rel,$id,array('source_type'=>sanitize_key((string)($assoc['source_type']??'')),'public_only'=>isset($assoc['public'])));\WP_CLI::line(wp_json_encode($rows,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));}

    private function config_export(array $assoc):void{$json=wp_json_encode($this->core->configuration()->export(),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);if(!empty($assoc['file'])){if(false===file_put_contents((string)$assoc['file'],$json))\WP_CLI::error('Could not write configuration file.');\WP_CLI::success('Configuration exported.');return;}\WP_CLI::line($json);}
    private function config_diff(array $args,array $assoc):void{$data=$this->read_config((string)($args[0]??''));$mode='replace'===($assoc['mode']??'')?'replace':'merge';\WP_CLI::line(wp_json_encode($this->core->configuration()->preview($data,$mode),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));}
    private function config_import(array $args,array $assoc):void{$data=$this->read_config((string)($args[0]??''));$mode='replace'===($assoc['mode']??'')?'replace':'merge';$dry=!isset($assoc['apply']);$result=$this->core->configuration()->import($data,$mode,$dry);\WP_CLI::line(wp_json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));if(empty($result['success']))\WP_CLI::error('Configuration import failed.',false);elseif($dry)\WP_CLI::success('Dry run completed. Re-run with --apply to write changes.');else\WP_CLI::success('Configuration applied.');}
    private function config_rollback(array $args):void{$id=sanitize_text_field((string)($args[0]??''));if(!$id)\WP_CLI::error('Provide a backup ID.');$result=$this->core->configuration()->rollback($id);\WP_CLI::line(wp_json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));if(empty($result['success']))\WP_CLI::error('Rollback failed.',false);else\WP_CLI::success('Configuration rolled back.');}

    private function read_config(string $path):array{if(!$path||!is_readable($path))\WP_CLI::error('Configuration file is not readable.');$data=json_decode((string)file_get_contents($path),true);if(!is_array($data))\WP_CLI::error('Configuration file is not valid JSON.');return $data;}
    private function serialize_items(array $items,string $type):array{$out=array();foreach($items as $item){$ref=$this->core->objects()->reference($item,$type?:null);if($ref)$out[]=$this->core->objects()->serialize($ref);}return $out;}
    private function health_payload():array{$missing_queries=array();foreach($this->core->content_types()->all() as $ct)if(!$this->core->query_providers()->for_content_type($ct))$missing_queries[]=$ct['id'];$missing_stores=array();foreach($this->core->relationships()->all() as $r)if(!$this->core->relationships()->store((string)($r['store']??'core')))$missing_stores[]=$r['id'];return array('version'=>CGM_CORE_VERSION,'provider_compatibility'=>$this->core->providers()->dependency_report(),'missing_query_providers'=>$missing_queries,'missing_relationship_stores'=>$missing_stores,'builders'=>$this->core->builders()->all(),'multisite'=>$this->core->multisite()->describe());}
}
