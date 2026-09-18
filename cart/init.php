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
 * Keep free items only while the paid products added with their bundle are in the cart, one free unit per complete set.
 * Only lines tagged by the bundle's own add-to-cart count; the same product added elsewhere does not.
 */
add_action('woocommerce_before_calculate_totals', 'wc_bundles_sync_free_items');
function wc_bundles_sync_free_items(WC_Cart $cart): void {
    static $syncing = false;

    if ($syncing) {
        return;
    }

    $syncing = true;

    try {
        $free = [];
        $paid = [];

        foreach ($cart->get_cart() as $key => $item) {
            if (isset($item['wc_bundles_free'])) {
                $free[$item['wc_bundles_free']][$key] = $item;
            }
            elseif (isset($item['wc_bundles_paid'])) {
                $paid[$item['wc_bundles_paid']][$item['product_id']] = ($paid[$item['wc_bundles_paid']][$item['product_id']] ?? 0) + $item['quantity'];
            }
        }

        foreach ($free as $bundle_id => $lines) {
            $bundle = wc_get_product($bundle_id);
            $paid_ids = $bundle && $bundle->is_type('bundle') ? wc_bundles_get_item_ids($bundle) : [];
            $free_ids = $paid_ids ? wc_bundles_get_free_ids($bundle) : [];
            $sets = $free_ids ? min(array_map(static fn(int $id) => $paid[$bundle_id][$id] ?? 0, $paid_ids)) : 0;
            $remaining = [];

            foreach ($lines as $key => $item) {
                $product_id = $item['product_id'];
                $remaining[$product_id] ??= in_array($product_id, $free_ids, true) ? $sets : 0;
                $allowed = min($item['quantity'], $remaining[$product_id]);
                $remaining[$product_id] -= $allowed;

                if ($item['quantity'] <= $allowed) {
                    continue;
                }

                $cart->set_quantity($key, $allowed, false);

                if (!$allowed) {
                    wc_add_notice(sprintf(__('%s was removed because its bundle is no longer complete.', 'wc-bundles'), $item['data']->get_name()), 'notice');
                }
            }
        }
    }
    finally {
        $syncing = false;
    }
}
