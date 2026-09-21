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
 * Free quantities follow the paid sets (see wc_bundles_sync_free_items), so the cart shows them as text instead of an input.
 * Runs late to replace any stepper a theme wraps around the input.
 */
add_filter('woocommerce_cart_item_quantity', 'wc_bundles_free_item_quantity_html', 20, 3);
function wc_bundles_free_item_quantity_html(string $html, string $key, array $item): string {
    if (!isset($item['wc_bundles_free'])) {
        return $html;
    }

    return sprintf('<span class="wc-bundles-free-quantity">%s: %d</span>', esc_html__('Qty', 'wc-bundles'), $item['quantity']);
}

/**
 * Keep free items only while the paid products added with their bundle are in the cart, one free unit per complete set.
 * Quantities follow the sets both ways; a free product the shopper removed has no line and stays out.
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

        foreach ($cart->get_cart() as $key => $item) {
            if (isset($item['wc_bundles_free'])) {
                $free[$item['wc_bundles_free']][$key] = $item;
            }
        }

        foreach ($free as $bundle_id => $lines) {
            $sets = wc_bundles_count_sets($cart, (int)$bundle_id);
            $free_ids = $sets ? wc_bundles_get_free_ids(wc_get_product($bundle_id)) : [];
            $remaining = [];
            $first = [];

            foreach ($lines as $key => $item) {
                $product_id = $item['product_id'];
                $first[$product_id] ??= $key;
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

            foreach ($first as $product_id => $key) {
                $item = $cart->get_cart_item($key);

                if (!$item || $remaining[$product_id] <= 0) {
                    continue;
                }

                // Other lines can share this stock, and WooCommerce checks the total at checkout
                $in_cart = $cart->get_cart_item_quantities()[$item['data']->get_stock_managed_by_id()] ?? 0;

                if (!$item['data']->is_sold_individually() && $item['data']->has_enough_stock($in_cart + $remaining[$product_id])) {
                    $cart->set_quantity($key, $item['quantity'] + $remaining[$product_id], false);
                    continue;
                }

                $message = sprintf(__('Only %1$d × %2$s can be included free with your bundles.', 'wc-bundles'), $item['quantity'], $item['data']->get_name());

                if (!wc_has_notice($message, 'notice')) {
                    wc_add_notice($message, 'notice');
                }
            }
        }
    }
    finally {
        $syncing = false;
    }
}

/**
 * Count the complete sets of a published bundle's paid products among the lines added with it.
 */
function wc_bundles_count_sets(WC_Cart $cart, int $bundle_id): int {
    $bundle = wc_get_product($bundle_id);
    $paid_ids = $bundle && $bundle->is_type('bundle') && $bundle->get_status() === 'publish' ? wc_bundles_get_item_ids($bundle) : [];

    if (!$paid_ids) {
        return 0;
    }

    $quantities = array_fill_keys($paid_ids, 0);

    foreach ($cart->get_cart() as $item) {
        if ((int)($item['wc_bundles_paid'] ?? 0) === $bundle_id && isset($quantities[$item['product_id']])) {
            $quantities[$item['product_id']] += $item['quantity'];
        }
    }

    return min($quantities);
}

// Restored lines get a fresh product object, so free ones need their zero price again
add_action('woocommerce_restore_cart_item', 'wc_bundles_price_restored_item', 10, 2);
function wc_bundles_price_restored_item(string $key, WC_Cart $cart): void {
    // WooCommerce restores the line even when its product was deleted meanwhile
    if ($cart->cart_contents[$key]['data'] instanceof WC_Product) {
        $cart->cart_contents[$key] = wc_bundles_price_free_item($cart->cart_contents[$key]);
    }
}

/**
 * Undoing a paid line's removal brings back the free lines removed with it, once every paid product of the bundle is in the cart again.
 */
add_action('woocommerce_cart_item_restored', 'wc_bundles_restore_free_items', 10, 2);
function wc_bundles_restore_free_items(string $key, WC_Cart $cart): void {
    $bundle_id = (int)($cart->get_cart_item($key)['wc_bundles_paid'] ?? 0);

    if (!$bundle_id || !wc_bundles_count_sets($cart, $bundle_id)) {
        return;
    }

    foreach ($cart->get_removed_cart_contents() as $removed_key => $item) {
        if ((int)($item['wc_bundles_free'] ?? 0) === $bundle_id) {
            $cart->restore_cart_item($removed_key);
        }
    }
}

/**
 * Record the bundle on each order line so orders, emails and refunds show why a line is free or grouped.
 */
add_action('woocommerce_checkout_create_order_line_item', 'wc_bundles_add_order_item_meta', 10, 3);
function wc_bundles_add_order_item_meta(WC_Order_Item_Product $item, string $key, array $values): void {
    if (isset($values['wc_bundles_free'])) {
        $item->add_meta_data(__('Free with', 'wc-bundles'), get_the_title($values['wc_bundles_free']), true);
    }
    elseif (isset($values['wc_bundles_paid'])) {
        $item->add_meta_data(__('Part of', 'wc-bundles'), get_the_title($values['wc_bundles_paid']), true);
    }
}
