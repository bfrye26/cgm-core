<?php
namespace CGM\Core\Integrations\Gutenberg;use CGM\Core\Plugin;
final class BlockBindings {
    public function __construct(private Plugin $core){}
    public function register():void{add_action('init',array($this,'source'),40);}
    public function source():void{if(!function_exists('register_block_bindings_source'))return;register_block_bindings_source('cgm-core/dynamic-data',array('label'=>__('CGM Dynamic Data','cgm-core'),'uses_context'=>array('postId','postType'),'get_value_callback'=>function(array $source_args,\WP_Block $block,string $attribute){$key=sanitize_text_field((string)($source_args['key']??''));$object=$GLOBALS['cgm_core_query_object']??null;if(!$object){$post_id=absint($block->context['postId']??get_the_ID());$object=$post_id?:null;}return $key?$this->core->dynamic_data()->resolve($key,$object):null;}));}
}
