<?php

defined('ABSPATH') || exit;

add_filter('product_type_selector', static function($types) {
    $types['bundle'] = __('Bundle', 'wc-bundles');

    return $types;
});

add_filter('woocommerce_product_class', static function($classname, $product_type) {
    if ($product_type !== 'bundle') {
        return $classname;
    }

    require_once WC_BUNDLES_PLUGIN_PATH . 'product-type/class-wc-bundles-product.php';

    return WC_Bundles_Product::class;
}, 10, 2);
