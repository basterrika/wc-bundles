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
        $item['wc_bundles_price'] = $item['data']->get_price();
        $item['data']->set_price(0);
    }

    return $item;
}

/**
 * Show a free line's usual price crossed out before its zero price, in the cart, mini-cart and checkout.
 */
add_filter('woocommerce_cart_item_price', 'wc_bundles_free_item_price_html', 10, 2);
add_filter('woocommerce_cart_item_subtotal', 'wc_bundles_free_item_price_html', 10, 2);
function wc_bundles_free_item_price_html(string $html, array $item): string {
    if (!isset($item['wc_bundles_free']) || !is_numeric($item['wc_bundles_price'] ?? null)) {
        return $html;
    }

    $args = [
        'qty' => current_filter() === 'woocommerce_cart_item_subtotal' ? $item['quantity'] : 1,
        'price' => (float)$item['wc_bundles_price'],
    ];
    $price = WC()->cart->display_prices_including_tax() ? wc_get_price_including_tax($item['data'], $args) : wc_get_price_excluding_tax($item['data'], $args);

    return '<span class="wc-bundles-free-price">' . wc_format_sale_price($price, $html) . '</span>';
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
