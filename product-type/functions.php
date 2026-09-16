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
 * Find the matching variation only when all required options are selected.
 *
 * @param array $selection Posted attribute names and values.
 *
 * @throws Exception
 */
function wc_bundles_resolve_variation(WC_Product_Variable $product, array $selection): ?WC_Product_Variation {
    $attributes = [];

    foreach ($product->get_variation_attributes() as $name => $options) {
        $key = wc_variation_attribute_name($name);
        $value = $selection[$key] ?? null;

        if (!is_string($value) || $value === '' || !in_array($value, $options, true)) {
            return null;
        }

        $attributes[$key] = $value;
    }

    if (!$attributes) {
        return null;
    }

    $id = WC_Data_Store::load('product')->find_matching_product_variation($product, $attributes);
    $variation = $id ? wc_get_product($id) : false;

    return $variation instanceof WC_Product_Variation && $variation->get_parent_id() === $product->get_id()
        ? $variation
        : null;
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
