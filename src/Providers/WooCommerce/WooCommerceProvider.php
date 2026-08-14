<?php
namespace CGM\Core\Providers\WooCommerce;

use CGM\Core\Contracts\ProviderInterface;
use CGM\Core\Plugin;

/** Makes WooCommerce products and orders addressable content with queryable fields. */
final class WooCommerceProvider implements ProviderInterface {
    public function id(): string { return 'woocommerce'; }

    public function register( Plugin $core ): void {
        if ( ! class_exists( 'WooCommerce' ) ) { return; }
        $core->providers()->register( array(
            'id'=>'woocommerce', 'label'=>'WooCommerce', 'version'=>defined( 'WC_VERSION' ) ? WC_VERSION : '',
            'apis'=>array( 'core'=>'*' ), 'capabilities'=>array( 'content.products', 'content.orders' ), 'status'=>'active',
        ) );
        foreach ( array( 'product', 'shop_order' ) as $pt ) {
            if ( ! post_type_exists( $pt ) ) { continue; }
            $obj = get_post_type_object( $pt );
            if ( ! $obj ) { continue; }
            $core->content_types()->register( array( 'id'=>$pt, 'label'=>$obj->labels->singular_name, 'plural_label'=>$obj->labels->name, 'kind'=>'post', 'subtype'=>$pt, 'provider'=>'woocommerce', 'query_provider'=>'wordpress-posts', 'public'=>(bool) $obj->public, 'rest'=>(bool) $obj->show_in_rest ) );
        }
        foreach ( array( 'price'=>'_price', 'sku'=>'_sku', 'stock'=>'_stock' ) as $id => $meta ) {
            $type = 'price' === $id ? 'number' : ( 'stock' === $id ? 'integer' : 'string' );
            $core->fields()->register( array( 'id'=>'product.'.$id, 'label'=>ucwords( $id ), 'source'=>$meta, 'type'=>$type, 'operators'=>array( '=','!=','>','>=','<','<=','IN','NOT IN','EXISTS','NOT EXISTS' ), 'provider'=>'woocommerce', 'queryable'=>true, 'sortable'=>true, 'content_types'=>array( 'product' ), 'dynamic'=>true, 'public'=>true ) );
            $core->dynamic_data()->register( array( 'id'=>'product.'.$id, 'label'=>ucwords( $id ), 'type'=>$type, 'group'=>'WooCommerce', 'provider'=>'woocommerce', 'resolve'=>static function ( $o ) use ( $meta ) { $id = $o instanceof \WP_Post ? $o->ID : absint( $o ); return get_post_meta( $id, $meta, true ); } ) );
        }
    }
}
