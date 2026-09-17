<?php

defined('ABSPATH') || exit;

/**
 * Get a bundle's configured paid or free product IDs.
 *
 * @return list<int>
 */
function wc_bundles_get_item_ids(WC_Product $product, bool $free = false): array {
    $saved_ids = $product->get_meta($free ? '_wc_bundles_free_item_ids' : '_wc_bundles_item_ids', true, 'edit');

    return is_array($saved_ids) ? wp_parse_id_list($saved_ids) : [];
}

/**
 * Get a bundle's free product IDs. Nothing is free without paid products, and a product in both lists is paid.
 *
 * @return list<int>
 */
function wc_bundles_get_free_ids(WC_Product $product): array {
    $paid_ids = wc_bundles_get_item_ids($product);

    return $paid_ids ? array_values(array_diff(wc_bundles_get_item_ids($product, true), $paid_ids)) : [];
}

/**
 * Get a bundle's public component products in their configured order, paid before free.
 *
 * @return list<WC_Product>
 */
function wc_bundles_get_items(WC_Product $product): array {
    $item_ids = array_merge(wc_bundles_get_item_ids($product), wc_bundles_get_free_ids($product));
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
 * @param list<int> $free_ids Product IDs that add nothing to the total.
 *
 * @return array{min: float, max: float}|null Null for empty or unpriced items.
 */
function wc_bundles_calculate_total(array $items, bool $for_display = false, array $free_ids = []): ?array {
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

            $low = (float)current($prices['price']);
            $high = (float)end($prices['price']);
        }
        else {
            if ($item->get_price() === '') {
                return null;
            }

            $low = $high = $for_display ? wc_get_price_to_display($item) : (float)$item->get_price();
        }

        // Free items must still be priced to be purchasable, but add nothing
        if (!in_array($item->get_id(), $free_ids, true)) {
            $minimum += $low;
            $maximum += $high;
        }
    }

    return ['min' => $minimum, 'max' => $maximum];
}

/**
 * Show a free item's usual price crossed out next to a zero price.
 *
 * @param float|null $price Display price of the selected variation, when known.
 */
function wc_bundles_free_price_html(WC_Product $product, ?float $price = null): string {
    if ($price === null && $product instanceof WC_Product_Variable) {
        $minimum = $product->get_variation_price('min', true);
        $maximum = $product->get_variation_price('max', true);

        return wc_format_sale_price($minimum === $maximum ? $minimum : wc_format_price_range($minimum, $maximum), 0);
    }

    return wc_format_sale_price($price ?? wc_get_price_to_display($product), 0);
}
