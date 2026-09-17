<?php
/**
 * Handle option selections from the bundle's single product page.
 *
 * Validate the bundle, resolve complete variation selections, check availability,
 * and return updated images, prices, messages, and the bundle total.
 * Loaded in a separate read-only AJAX request when selections change, not while
 * rendering the product page. Does not modify products or the cart.
 */

defined('ABSPATH') || exit;

/**
 * Read-only public pricing endpoint; no cart or product data is modified.
 */
function wc_bundles_send_selection(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        wp_send_json_error(null, 405);
    }

    $bundle_id = $_POST['bundle_id'] ?? null;
    $selections = $_POST['selections'] ?? [];
    $displayed = $_POST['items'] ?? [];

    if (!is_scalar($bundle_id) || !is_array($selections) || !is_array($displayed)) {
        wp_send_json_error(null, 400);
    }

    $bundle = wc_get_product(absint($bundle_id));

    if (!$bundle || !$bundle->is_type('bundle') || $bundle->get_status() !== 'publish' || post_password_required($bundle->get_id())) {
        wp_send_json_error(null, 404);
    }

    require_once WC_BUNDLES_PLUGIN_PATH . 'frontend/data.php';
    wp_send_json_success(wc_bundles_get_selection_data($bundle, wp_unslash($selections), wp_parse_id_list($displayed)));
}

/**
 * Price only configured public items using server-resolved variations.
 *
 * @param array<int, array<string, mixed>> $selections Complete item selections keyed by product ID.
 * @param list<int> $displayed Product IDs currently displayed on the page.
 *
 * @return array{items: array<int, array>, purchasable: bool, total_html: string}
 * @throws Exception
 */
function wc_bundles_get_selection_data(WC_Product $bundle, array $selections, array $displayed): array {
    $products = wc_bundles_get_items($bundle);
    $ids = array_map(static fn(WC_Product $product) => $product->get_id(), $products);
    $free_ids = wc_bundles_get_free_ids($bundle);
    $items = [];
    $selected_total = 0.0;
    $available = true;
    $ready = true;

    foreach ($products as $index => $product) {
        if (!$product instanceof WC_Product_Variable) {
            continue;
        }

        $selection = $selections[$product->get_id()] ?? null;

        if ($selection === null) {
            $ready = false;
            continue;
        }

        $variation = is_array($selection) ? wc_bundles_resolve_variation($product, $selection) : null;
        $result = [
            'variation_id' => 0,
            'message' => __('This combination is unavailable. Choose different options.', 'wc-bundles'),
        ];

        // Run WooCommerce's woocommerce_available_variation filter so extensions can adjust stock, purchasability, and price
        $data = $variation ? $product->get_available_variation($variation) : false;

        if ($data && $data['variation_is_visible'] && $data['variation_is_active'] && $data['is_purchasable']) {
            $in_stock = $data['is_in_stock'] && $variation->has_enough_stock(1);
            $free = in_array($product->get_id(), $free_ids, true);
            $result = [
                'variation_id' => $variation->get_id(),
                // WooCommerce omits price_html when all variations share a price; the bundle always shows it
                'price_html' => wc_bundles_kses_html($free ? wc_bundles_free_price_html($variation, (float)$data['display_price']) : ($data['price_html'] ?: $variation->get_price_html())),
                'image_html' => wc_bundles_kses_html($variation->get_image('woocommerce_thumbnail', ['class' => 'wc-bundles-product-image'])),
                'thumbnail_html' => wc_bundles_kses_html($variation->get_image('woocommerce_gallery_thumbnail', ['class' => 'wc-bundles-thumbnail', 'alt' => '', 'loading' => 'lazy'])),
                'message' => $in_stock ? '' : __('This combination is out of stock. Choose different options.', 'wc-bundles'),
            ];
            $selected_total += $free ? 0.0 : (float)$data['display_price'];
            unset($products[$index]);
            $available = $available && $in_stock;
        }
        else {
            $available = false;
        }

        $items[$product->get_id()] = $result;
    }

    if (array_diff($displayed, $ids) || array_diff(array_keys($selections), $ids)) {
        $available = false;
    }

    $total = $products ? wc_bundles_calculate_total($products, true, $free_ids) : ['min' => 0.0, 'max' => 0.0];
    if ($total) {
        $total['min'] += $selected_total;
        $total['max'] += $selected_total;
    }

    return [
        'items' => $items,
        'purchasable' => $available && $ready && $total !== null,
        'total_html' => $available
            ? wc_bundles_kses_html(wc_bundles_format_total($total))
            : esc_html__('Selection unavailable', 'wc-bundles'),
    ];
}
