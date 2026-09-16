<?php

defined('ABSPATH') || exit;

add_action('current_screen', 'wc_bundles_init_product_editor');
function wc_bundles_init_product_editor(WP_Screen $screen): void {
    if ($screen->base !== 'post' || $screen->post_type !== 'product') {
        return;
    }

    require_once WC_BUNDLES_PLUGIN_PATH . 'admin/product-data/register.php';
}
