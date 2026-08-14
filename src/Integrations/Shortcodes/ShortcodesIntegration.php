<?php
namespace CGM\Core\Integrations\Shortcodes;

use CGM\Core\Plugin;
use CGM\Core\Objects\ObjectReference;

/** Builder-neutral fallback surface for builders that support shortcodes/PHP. */
final class ShortcodesIntegration {
    public function __construct( private Plugin $core ) {}
    public function register(): void { add_shortcode('cgm_data',array($this,'data')); add_shortcode('cgm_query',array($this,'query')); add_shortcode('cgm_view',array($this,'view')); add_shortcode('cgm_object',array($this,'object_view')); }

    /** Render an object through a registered view mode: [cgm_object id="123" view="card"]. */
    public function object_view( array $atts ): string {
        $a=shortcode_atts(array('id'=>0,'content_type'=>'','view'=>'card'),$atts,'cgm_object');
        $id=absint($a['id']);if(!$id)return '';
        $ref=$this->core->objects()->reference($id,sanitize_key((string)$a['content_type'])?:null);if(!$ref)return '';
        $view=$this->core->view_modes()->get(sanitize_key((string)$a['view']));
        $fields=$view?(array)($view['fields']??array()):array('post.title');
        $html='<div class="cgm-object cgm-object--'.esc_attr(sanitize_key((string)$a['view'])).'">';
        foreach($fields as $field){
            $label='';$key=$field;
            if(is_array($field)){$key=(string)($field['field']??$field['path']??'');$label=(string)($field['label']??'');}
            if(!$key)continue;
            $value=$this->string_value($this->core->dynamic_data()->resolve((string)$key,$ref));
            if(''===$value)continue;
            $html.='<div class="cgm-object-field">';
            if($label)$html.='<span class="cgm-object-label">'.esc_html($label).'</span> ';
            $html.='<span class="cgm-object-value">'.esc_html($value).'</span></div>';
        }
        return $html.'</div>';
    }

    public function data( array $atts ): string {
        $a=shortcode_atts(array('key'=>'','object_id'=>0,'content_type'=>'','escape'=>'html'),$atts,'cgm_data');
        $object=absint($a['object_id']);if($object&&!empty($a['content_type']))$object=new ObjectReference(sanitize_key((string)$a['content_type']),$object);
        $v=$this->core->dynamic_data()->resolve((string)$a['key'],$object?:null);
        if(is_array($v))$v=implode(', ',array_map(array($this,'string_value'),$v));$v=$this->string_value($v);
        return match((string)$a['escape']){'url'=>esc_url($v),'attr'=>esc_attr($v),'raw'=>current_user_can('unfiltered_html')?$v:wp_kses_post($v),default=>esc_html($v)};
    }

    public function query( array $atts ): string {
        $a=shortcode_atts(array('id'=>'','format'=>'links','limit'=>0,'class'=>'cgm-query-results'),$atts,'cgm_query');
        if(''===(string)$a['id'])return '';$r=$this->core->queries()->run((string)$a['id'],array('consumer'=>'shortcode','post_id'=>get_the_ID()));return $this->render_result($r,$a);
    }

    /**
     * A saved query rendered as a view, optionally with an exposed filter form.
     *
     * [cgm_view id="related-game-coverage" filters="1"]
     *
     * `filters="1"` renders a form (one control per exposed filter in the query
     * definition); submitted values are appended as field rules before the query
     * runs, matching Drupal Views' exposed filters.
     */
    public function view( array $atts ): string {
        $a=shortcode_atts(array('id'=>'','filters'=>0,'limit'=>0,'class'=>'cgm-view-results'),$atts,'cgm_view');
        if(''===(string)$a['id'])return '';
        $saved=$this->core->saved_queries()->find((string)$a['id']);
        if(!$saved)return '';
        $definition=$saved['definition'];
        $exposed=(array)($definition['exposed_filters']??array());
        $html='';
        if($exposed&&!empty($a['filters'])){
            $filters=is_array($definition['filters']??null)?$definition['filters']:array('relation'=>'AND','rules'=>array());
            $filters['rules']=is_array($filters['rules']??null)?$filters['rules']:array();
            $html.='<form method="get" class="cgm-view-filter-form">';
            foreach($exposed as $f){
                $field=sanitize_text_field((string)($f['field']??''));if(!$field)continue;
                $label=$f['label']?:$field;$input=sanitize_key((string)($f['input']??'text'));$param=sanitize_key($field);
                $cur=isset($_GET[$param])?sanitize_text_field(wp_unslash((string)$_GET[$param])):''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $html.='<label>'.esc_html($label).' ';
                if('select'===$input){$html.='<select name="'.esc_attr($param).'"><option value="">'.esc_html__('Any','cgm-core').'</option>';foreach((array)($f['options']??array()) as $v=>$l)$html.='<option value="'.esc_attr($v).'"'.selected($cur,$v,false).'>'.esc_html($l).'</option>';$html.='</select>';}
                else{$html.='<input type="text" name="'.esc_attr($param).'" value="'.esc_attr($cur).'">';}
                $html.='</label> ';
                if(''!==$cur)$filters['rules'][]=array('type'=>'field','field'=>$field,'operator'=>'=','value'=>$cur);
            }
            $html.='<button type="submit">'.esc_html__('Filter','cgm-core').'</button></form>';
            $definition['filters']=$filters;
        }
        $r=$this->core->queries()->run($definition,array('consumer'=>'shortcode','post_id'=>get_the_ID()));
        $a['limit']=$a['limit']?:0;
        return $html.$this->render_result($r,$a);
    }

    private function render_result( \CGM\Core\Query\QueryResult $r, array $a ): string {
        $items=$r->items;
        if(absint($a['limit']??0))$items=array_slice($items,0,absint($a['limit']));$content_type=(string)($r->debug['normalized']['content_type']??'');$rows=array();
        foreach($items as $item){$ref=$this->core->objects()->reference($item,$content_type?:null);if(!$ref)continue;$rows[]=$this->core->objects()->serialize($ref);}
        if('ids'===$a['format'])return esc_html(implode(',',array_column($rows,'id')));
        if('json'===$a['format'])return '<pre class="cgm-query-json">'.esc_html(wp_json_encode($rows,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)).'</pre>';
        if('count'===$a['format'])return esc_html((string)$r->total);
        $class=sanitize_html_class((string)($a['class']??'cgm-query-results'));$html='<ul class="'.esc_attr($class).'">';foreach($rows as $row){$label=(string)($row['label']??('#'.$row['id']));$url=(string)($row['url']??'');$html.='<li>';if($url)$html.='<a href="'.esc_url($url).'">'.esc_html($label).'</a>';else$html.=esc_html($label);$html.='</li>';}$html.='</ul>';return $html;
    }

    private function string_value( mixed $value ): string {
        if(null===$value)return '';if(is_bool($value))return $value?'1':'0';if(is_scalar($value))return (string)$value;
        if($value instanceof ObjectReference)return $this->core->objects()->label($value);
        if($value instanceof \WP_Post)return get_the_title($value);if($value instanceof \WP_User)return $value->display_name;if($value instanceof \WP_Term)return $value->name;
        return (string)wp_json_encode($value,JSON_UNESCAPED_SLASHES);
    }
}
