<?php
namespace CGM\Core\Integrations\Gutenberg;use CGM\Core\Plugin;use CGM\Core\Contracts\BuilderAdapterInterface;
final class GutenbergIntegration implements BuilderAdapterInterface {
    public function id(): string { return 'gutenberg'; }
    public function detected(): bool { return true; }
    public function capabilities(): array { return array('editor-summary','query-loop-block','block-bindings','dynamic-data','relationships','context'); }
    public function __construct(private ?Plugin $core=null){}
    public function register():void{add_action('enqueue_block_editor_assets',array($this,'enqueue'));if($this->core){(new BlockBindings($this->core))->register();(new QueryLoopBlock($this->core))->register();(new DynamicValueBlock($this->core))->register();(new BlockLibrary($this->core))->register();}}
    public function enqueue():void{wp_enqueue_script('cgm-core-editor',CGM_CORE_URL.'assets/js/editor.js',array('wp-api-fetch','wp-components','wp-data','wp-edit-post','wp-editor','wp-element','wp-plugins','wp-i18n'),CGM_CORE_VERSION,true);wp_enqueue_style('cgm-core-editor',CGM_CORE_URL.'assets/css/editor.css',array('wp-components'),CGM_CORE_VERSION);wp_add_inline_script('cgm-core-editor','window.CGMCoreEditor='.wp_json_encode(array('rest'=>esc_url_raw(rest_url('cgm-core/v1/')),'nonce'=>wp_create_nonce('wp_rest'))).';','before');}
}
