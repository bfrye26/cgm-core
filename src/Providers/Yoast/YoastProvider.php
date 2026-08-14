<?php
namespace CGM\Core\Providers\Yoast;

use CGM\Core\Contracts\ProviderInterface;
use CGM\Core\Plugin;

/** Exposes Yoast SEO meta as queryable fields and dynamic data. */
final class YoastProvider implements ProviderInterface {
    public function id(): string { return 'yoast-seo'; }

    public function register( Plugin $core ): void {
        if ( ! defined( 'WPSEO_VERSION' ) ) { return; }
        $core->providers()->register( array(
            'id'=>'yoast-seo', 'label'=>'Yoast SEO', 'version'=>WPSEO_VERSION,
            'apis'=>array( 'core'=>'*' ), 'capabilities'=>array( 'seo.fields' ), 'status'=>'active',
        ) );
        foreach ( array( 'focus_keyword'=>'_yoast_wpseo_focuskw', 'meta_description'=>'_yoast_wpseo_metadesc', 'seo_title'=>'_yoast_wpseo_title', 'readability_score'=>'_yoast_wpseo_linkdex' ) as $id => $meta ) {
            $core->fields()->register( array( 'id'=>'seo.'.$id, 'label'=>ucwords( str_replace( '_', ' ', $id ) ), 'source'=>$meta, 'type'=>'string', 'operators'=>array( '=','!=','LIKE','NOT LIKE','EXISTS','NOT EXISTS' ), 'provider'=>'yoast-seo', 'queryable'=>true, 'sortable'=>false, 'content_types'=>array( 'post' ), 'dynamic'=>true, 'public'=>true ) );
            $core->dynamic_data()->register( array( 'id'=>'seo.'.$id, 'label'=>ucwords( str_replace( '_', ' ', $id ) ), 'type'=>'string', 'group'=>'Yoast SEO', 'provider'=>'yoast-seo', 'resolve'=>static function ( $o ) use ( $meta ) { $id = $o instanceof \WP_Post ? $o->ID : absint( $o ); return get_post_meta( $id, $meta, true ); } ) );
        }
    }
}
