<?php
namespace CGM\Core\Integrations\Gutenberg;use CGM\Core\Plugin;
final class DynamicValueBlock {
    public function __construct(private Plugin $core){}
    public function register():void{add_action('init',array($this,'block'),45);}
    public function block():void{register_block_type(CGM_CORE_PATH.'blocks/dynamic-value',array('render_callback'=>array($this,'render')));}
    public function render(array $attributes):string{$key=sanitize_text_field((string)($attributes['key']??''));if(!$key)$key=sanitize_text_field((string)($attributes['path']??''));if(!$key)return '';$object=$GLOBALS['cgm_core_query_object']??get_the_ID();$value=$this->core->dynamic_data()->resolve($key,$object,array('current_query_item'=>$this->id($object),'post_id'=>get_the_ID()));if(is_array($value))$value=implode(', ',array_map(function($v){if(is_scalar($v))return (string)$v;if(is_array($v))return (string)($v['label']??$v['title']??$v['id']??'');return '';},$value));$tag=in_array((string)($attributes['tagName']??'span'),array('span','div','p','strong','h2','h3','h4'),true)?$attributes['tagName']:'span';return '<'.$tag.' class="wp-block-cgm-core-dynamic-value">'.esc_html((string)$value).'</'.$tag.'>';}
    private function id(mixed $o):int{return $o instanceof \WP_Post||$o instanceof \WP_User?(int)$o->ID:($o instanceof \WP_Term?(int)$o->term_id:absint($o));}
}
