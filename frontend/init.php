<?php

defined('ABSPATH') || exit;

add_action('wc_ajax_wc_bundles_selection', 'wc_bundles_load_selection');
function wc_bundles_load_selection(): void {
    require_once WC_BUNDLES_PLUGIN_PATH . 'frontend/selection.php';
    wc_bundles_send_selection();
}

add_action('wp_enqueue_scripts', 'wc_bundles_enqueue_cart_style');
function wc_bundles_enqueue_cart_style(): void {
    // Carts can render on any page; two inlined rules avoid a stylesheet request.
    wp_register_style('wc-bundles-cart', false, [], WC_BUNDLES_VERSION);
    wp_enqueue_style('wc-bundles-cart');
    wp_add_inline_style('wc-bundles-cart', '.wc-bundles-free-price del{color:#c00}.wc-bundles-free-quantity{color:#9a9a9a;font-size:12px}');
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

    // The theme keeps its own product page; the bundle only fills the add-to-cart slot
    add_action('wp_enqueue_scripts', 'wc_bundles_enqueue_frontend_assets');
    add_action('woocommerce_bundle_add_to_cart', 'wc_bundles_render_summary');
}

/**
 * Render the bundle at WooCommerce's bundle add-to-cart hook.
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
        echo '<p class="wc-bundles-hint">' . esc_html__('No products are available in this bundle yet.', 'wc-bundles') . '</p>';

        return;
    }

    if ($data['has_options']) {
        wp_enqueue_script('wc-bundles-frontend');
        wp_localize_script('wc-bundles-frontend', 'wcBundles', [
            'url' => WC_AJAX::get_endpoint('wc_bundles_selection'),
            'bundleId' => $product->get_id(),
            'error' => __('Could not check availability. Please try again.', 'wc-bundles'),
            'unavailable' => __('This product is no longer available.', 'wc-bundles'),
            'bundleUnavailable' => __('This bundle is currently unavailable.', 'wc-bundles'),
            'checking' => __('Checking availability…', 'wc-bundles'),
            /* translators: %s: attribute name, e.g. size */
            'select' => __('Select %s', 'wc-bundles'),
            /* translators: %s: product name */
            'choose' => __('Choose the options for %s.', 'wc-bundles'),
        ]);
    }

    require WC_BUNDLES_PLUGIN_PATH . 'frontend/templates/summary.php';
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
