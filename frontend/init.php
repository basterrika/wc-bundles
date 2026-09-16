<?php

defined('ABSPATH') || exit;

add_action('wc_ajax_wc_bundles_selection', 'wc_bundles_load_selection');
function wc_bundles_load_selection(): void {
    require_once WC_BUNDLES_PLUGIN_PATH . 'frontend/selection.php';
    wc_bundles_send_selection();
}

add_action('wp', 'wc_bundles_init_frontend');
function wc_bundles_init_frontend(): void {
    if (!is_product() || is_feed() || post_password_required()) {
        return;
    }

    $product = wc_get_product(get_queried_object_id());

    if (!$product || !$product->is_type('bundle')) {
        return;
    }

    // The bundle title is rendered above both columns
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_title', 5);

    // The bundle total is rendered inside the summary
    remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);

    add_filter('wc_get_template_part', 'wc_bundles_product_template', 10, 3);
    add_action('wp_enqueue_scripts', 'wc_bundles_enqueue_frontend_assets');
    add_action('woocommerce_bundle_add_to_cart', 'wc_bundles_render_summary');
}

/**
 * Render the summary at WooCommerce's bundle add-to-cart hook.
 */
function wc_bundles_render_summary(): void {
    /** @var WC_Product $product Current product supplied by WooCommerce. */
    global $product;

    if (!$product instanceof WC_Product || !$product->is_type('bundle')) {
        return;
    }

    require_once WC_BUNDLES_PLUGIN_PATH . 'frontend/data.php';
    $data = wc_bundles_get_frontend_data($product);

    if (!$data['items']) {
        return;
    }

    require WC_BUNDLES_PLUGIN_PATH . 'frontend/templates/summary.php';
}

/**
 * Render the bundle product page.
 */
function wc_bundles_render_product(): void {
    /** @var WC_Product $product Current product supplied by WooCommerce. */
    global $product;

    if (!$product instanceof WC_Product || !$product->is_type('bundle')) {
        return;
    }

    do_action('woocommerce_before_single_product');

    require_once WC_BUNDLES_PLUGIN_PATH . 'frontend/data.php';
    $data = wc_bundles_get_frontend_data($product);

    if ($data['has_options']) {
        wp_enqueue_script('wc-bundles-frontend');
        wp_localize_script('wc-bundles-frontend', 'wcBundles', [
            'url' => WC_AJAX::get_endpoint('wc_bundles_selection'),
            'bundleId' => $product->get_id(),
            'error' => __('Could not check availability. Please try again.', 'wc-bundles'),
            'unavailable' => __('This product is no longer available.', 'wc-bundles'),
        ]);
    }

    require WC_BUNDLES_PLUGIN_PATH . 'frontend/templates/single-product.php';
}

function wc_bundles_product_template(string $template, string $slug, string $name): string {
    if ($slug !== 'content' || $name !== 'single-product' || get_the_ID() !== get_queried_object_id()) {
        return $template;
    }

    return WC_BUNDLES_PLUGIN_PATH . 'frontend/render.php';
}

function wc_bundles_enqueue_frontend_assets(): void {
    wp_enqueue_style(
        'wc-bundles-frontend',
        WC_BUNDLES_PLUGIN_URL . 'frontend/assets/style.css',
        [],
        WC_BUNDLES_VERSION
    );

    wp_register_script(
        'wc-bundles-frontend',
        WC_BUNDLES_PLUGIN_URL . 'frontend/assets/script.js',
        [],
        WC_BUNDLES_VERSION,
        ['in_footer' => true, 'strategy' => 'defer']
    );
}
