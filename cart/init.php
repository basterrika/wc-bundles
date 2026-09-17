<?php

defined('ABSPATH') || exit;

add_action('woocommerce_add_to_cart_handler_bundle', 'wc_bundles_handle_purchase');
function wc_bundles_handle_purchase(string|false $url): void {
    require_once WC_BUNDLES_PLUGIN_PATH . 'cart/add-to-cart.php';
    wc_bundles_submit_purchase($url);
}
