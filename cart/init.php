<?php

defined('ABSPATH') || exit;

add_action('woocommerce_add_to_cart_handler_bundle', 'wc_bundles_handle_purchase');
function wc_bundles_handle_purchase(string|false $url): void {
    require_once WC_BUNDLES_PLUGIN_PATH . 'cart/add-to-cart.php';
    wc_bundles_submit_purchase($url);
}

// New lines and lines restored from the session both get the zero price; product objects are rebuilt on every request
add_filter('woocommerce_add_cart_item', 'wc_bundles_price_free_item');
add_filter('woocommerce_get_cart_item_from_session', 'wc_bundles_price_free_item');
function wc_bundles_price_free_item(array $item): array {
    if (isset($item['wc_bundles_free'])) {
        $item['data']->set_price(0);
    }

    return $item;
}

/**
 * Keep free items only while their bundle's paid products are in the cart, one free unit per complete set.
 */
add_action('woocommerce_before_calculate_totals', 'wc_bundles_sync_free_items');
function wc_bundles_sync_free_items(WC_Cart $cart): void {
    $free = [];
    $paid = [];

    foreach ($cart->get_cart() as $key => $item) {
        if (isset($item['wc_bundles_free'])) {
            $free[$key] = $item;
        }
        else {
            $paid[$item['product_id']] = ($paid[$item['product_id']] ?? 0) + $item['quantity'];
        }
    }

    foreach ($free as $key => $item) {
        $bundle = wc_get_product($item['wc_bundles_free']);
        $allowed = 0;

        if ($bundle && $bundle->is_type('bundle') && in_array($item['product_id'], wc_bundles_get_free_ids($bundle), true)) {
            $allowed = min(array_map(static fn(int $id) => $paid[$id] ?? 0, wc_bundles_get_item_ids($bundle)));
        }

        if ($item['quantity'] <= $allowed) {
            continue;
        }

        $cart->set_quantity($key, $allowed, false);

        if (!$allowed) {
            wc_add_notice(sprintf(__('%s was removed because its bundle is no longer complete.', 'wc-bundles'), $item['data']->get_name()), 'notice');
        }
    }
}
