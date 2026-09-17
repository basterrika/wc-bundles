<?php
/**
 * Add every bundle component through WooCommerce's product form handler.
 * Paid components become plain, independent cart lines; free ones remember their bundle (see cart/init.php).
 */

defined('ABSPATH') || exit;

function wc_bundles_submit_purchase(string|false $url): void {
    $errors = wc_notice_count('error');

    try {
        $id = $_POST['add-to-cart'] ?? null;
        $selections = $_POST['wc_bundles_selections'] ?? [];
        $displayed = $_POST['wc_bundles_items'] ?? [];

        if (!is_scalar($id) || !is_array($selections)) {
            throw new Exception(__('Please reload the product page and try again.', 'wc-bundles'));
        }

        wc_bundles_add_to_cart(absint($id), wp_unslash($selections), wp_parse_id_list($displayed));
        wc_add_to_cart_message([absint($id) => 1], true);

        $url = apply_filters('woocommerce_add_to_cart_redirect', $url, wc_get_product(absint($id)));

        if ($url || get_option('woocommerce_cart_redirect_after_add') === 'yes') {
            wp_safe_redirect($url ?: wc_get_cart_url());
            exit;
        }
    }
    catch (Exception $error) {
        if (wc_notice_count('error') === $errors) {
            wc_add_notice(esc_html($error->getMessage()), 'error');
        }
    }
}

/**
 * Add every component or restore the cart if any addition fails.
 *
 * @param array<int, array<string, string>> $selections Selected attributes.
 * @param list<int> $displayed Expected component IDs.
 * @throws Exception|Throwable When validation or addition fails.
 */
function wc_bundles_add_to_cart(int $bundle_id, array $selections, array $displayed): void {
    $bundle = wc_get_product($bundle_id);
    $cart = WC()->cart;

    if (!$bundle) {
        throw new Exception(__('This bundle cannot be added to the cart.', 'wc-bundles'));
    }

    $components = wc_bundles_validate_purchase($bundle, $selections, $displayed);
    $before = $cart->get_cart();
    $removed = $cart->get_removed_cart_contents();

    $priority = has_action('woocommerce_add_to_cart', [$cart, 'calculate_totals']);
    if ($priority !== false) {
        remove_action('woocommerce_add_to_cart', [$cart, 'calculate_totals'], $priority);
    }

    try {
        foreach ($components as $component) {
            if (!apply_filters('woocommerce_add_to_cart_validation', true, $component['product_id'], 1, $component['variation_id'], $component['variation'])) {
                throw new Exception(sprintf(__('%s could not be added. The bundle was not added.', 'wc-bundles'), $component['name']));
            }

            if (!$cart->add_to_cart($component['product_id'], 1, $component['variation_id'], $component['variation'], $component['free'] ? ['wc_bundles_free' => $bundle_id] : [])) {
                throw new Exception(__('The complete bundle could not be added. Your previous cart has been kept.', 'wc-bundles'));
            }
        }
    }
    catch (Throwable $error) {
        $cart->set_cart_contents($before);
        $cart->set_removed_cart_contents($removed);
        throw $error;
    }
    finally {
        if ($priority !== false) {
            add_action('woocommerce_add_to_cart', [$cart, 'calculate_totals'], $priority, 0);
        }
        $cart->calculate_totals();
    }
}

/**
 * Resolve every configured component; never accept prices or variation IDs from the browser.
 *
 * @param array<int, array<string, string>> $selections Selected attributes keyed by product ID.
 * @param list<int> $expected Component IDs from the page.
 * @return list<array{name: string, product_id: int, variation_id: int, variation: array, free: bool}>
 * @throws Exception When the bundle or a selection is unavailable.
 */
function wc_bundles_validate_purchase(WC_Product $bundle, array $selections, array $expected): array {
    if (!$bundle->is_type('bundle') || $bundle->get_status() !== 'publish' || post_password_required($bundle->get_id()) || !$bundle->is_in_stock()) {
        throw new Exception(__('This bundle is no longer available.', 'wc-bundles'));
    }

    $free_ids = wc_bundles_get_free_ids($bundle);
    $ids = array_merge(wc_bundles_get_item_ids($bundle), $free_ids);
    $products = wc_bundles_get_items($bundle);
    $public_ids = array_map(static fn(WC_Product $product) => $product->get_id(), $products);

    if (!$ids || array_diff($ids, $public_ids) || array_diff($ids, $expected) || array_diff($expected, $ids)) {
        throw new Exception(__('This bundle has changed or contains unavailable products. Please reload its product page.', 'wc-bundles'));
    }

    $resolved = [];

    foreach ($products as $product) {
        $parent_id = $product->get_id();
        $attributes = [];
        $variation_id = 0;

        if ($product instanceof WC_Product_Variable) {
            $attributes = $selections[$parent_id] ?? [];
            $variation = is_array($attributes) ? wc_bundles_resolve_variation($product, $attributes) : null;

            if (!$variation?->variation_is_active()) {
                throw new Exception(sprintf(__('Choose an available combination for %s.', 'wc-bundles'), $product->get_name()));
            }

            $variation_id = $variation->get_id();
        }

        $resolved[] = ['name' => $product->get_name(), 'product_id' => $parent_id, 'variation_id' => $variation_id, 'variation' => $attributes, 'free' => in_array($parent_id, $free_ids, true)];
    }

    return $resolved;
}
