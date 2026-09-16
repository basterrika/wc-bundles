<?php

defined('ABSPATH') || exit;

/**
 * Get a bundle's public component products in their configured order.
 *
 * @return list<WC_Product>
 */
function wc_bundles_get_items(WC_Product $product): array {
    $saved_ids = $product->get_meta('_wc_bundles_item_ids', true, 'edit');
    $item_ids = is_array($saved_ids) ? wp_parse_id_list($saved_ids) : [];
    $items = [];

    if ($item_ids) {
        _prime_post_caches($item_ids);
    }

    foreach ($item_ids as $item_id) {
        $item = wc_get_product($item_id);

        if (!$item || !$item->is_type(['simple', 'variable']) || $item->get_status() !== 'publish' || !$item->is_visible() || post_password_required($item_id)) {
            continue;
        }

        $items[] = $item;
    }

    return $items;
}

/**
 * Sum one of each component.
 *
 * @param list<WC_Product> $items Component products or selected variations.
 * @param bool $for_display Apply WooCommerce's shop tax-display settings.
 *
 * @return array{min: float, max: float}|null Null for empty or unpriced items.
 */
function wc_bundles_calculate_total(array $items, bool $for_display = false): ?array {
    if (!$items) {
        return null;
    }

    $minimum = 0.0;
    $maximum = 0.0;

    foreach ($items as $item) {
        if ($item instanceof WC_Product_Variable) {
            $prices = $item->get_variation_prices($for_display);

            if (!$prices['price']) {
                return null;
            }

            $minimum += (float)current($prices['price']);
            $maximum += (float)end($prices['price']);
        }
        else {
            if ($item->get_price() === '') {
                return null;
            }

            $price = $for_display ? wc_get_price_to_display($item) : (float)$item->get_price();
            $minimum += $price;
            $maximum += $price;
        }
    }

    return ['min' => $minimum, 'max' => $maximum];
}
