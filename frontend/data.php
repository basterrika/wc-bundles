<?php

defined('ABSPATH') || exit;

/**
 * Sanitize bundle markup while preserving responsive images and price direction.
 */
function wc_bundles_kses_html(string $html): string {
    static $allowed_html = null;

    if ($allowed_html === null) {
        $allowed_html = wp_kses_allowed_html('post');
        $allowed_html['img']['srcset'] = true;
        $allowed_html['img']['sizes'] = true;
        $allowed_html['img']['decoding'] = true;
        $allowed_html['bdi']['dir'] = true;
    }

    return wp_kses($html, $allowed_html);
}

/**
 * Prepare everything the bundle's templates print.
 */
function wc_bundles_get_frontend_data(WC_Product $product): array {
    $products = wc_bundles_get_items($product);
    $free_ids = wc_bundles_get_free_ids($product);
    $items = [];
    $has_options = false;
    $children = [];

    foreach ($products as $item) {
        if ($item instanceof WC_Product_Variable) {
            $children[] = $item->get_visible_children();
        }
    }

    // One query primes every variation's meta for wc_bundles_get_in_stock_variations()
    if ($children) {
        update_meta_cache('post', array_merge(...$children));
    }

    // Two queries prime every item thumbnail instead of two per item
    if ($image_ids = array_filter(array_map(static fn(WC_Product $item) => (int)$item->get_image_id(), $products))) {
        _prime_post_caches($image_ids, false, true);
    }

    foreach ($products as $item) {
        $data = wc_bundles_prepare_item($item, in_array($item->get_id(), $free_ids, true));
        // Without JavaScript the first configurable item starts expanded
        $data['open'] = !$has_options && $data['attributes'] !== [];
        $items[] = $data;
        $has_options = $has_options || $data['attributes'] !== [];
    }

    return [
        'items' => $items,
        'total_html' => wc_bundles_format_total(wc_bundles_calculate_total($products, true, $free_ids)),
        'has_options' => $has_options,
        // Items without options need no selection
        'ready' => count(array_filter($items, static fn(array $item) => !$item['attributes'])),
        'available' => wc_bundles_is_complete($product, $products),
        'form_action' => apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink()),
    ];
}

/**
 * Read in-stock variations from post meta, without loading variation objects.
 * Only a hint for the controls: the selection endpoint and the cart still validate through WooCommerce.
 *
 * @param list<string> $names Variation attribute meta keys, in display order.
 *
 * @return list<list<string>>
 */
function wc_bundles_get_in_stock_variations(WC_Product_Variable $item, array $names): array {
    $variations = [];

    foreach ($item->get_visible_children() as $id) {
        $meta = get_post_meta($id);

        // Prices are left to WooCommerce: filters can price a variation whose meta is empty, and wrongly disabling a buyable option is the worse mistake
        if (($meta['_stock_status'][0] ?? 'instock') !== 'outofstock') {
            $variations[] = array_map(static fn(string $name) => (string)($meta[$name][0] ?? ''), $names);
        }
    }

    return $variations;
}

/**
 * Prepare display values and option controls without loading variation objects.
 */
function wc_bundles_prepare_item(WC_Product $item, bool $free = false): array {
    $attributes = [];
    $variations = [];

    if ($item->is_type('variable')) {
        $defaults = $item->get_default_attributes();

        foreach ($item->get_attributes() as $attribute) {
            if (!$attribute->get_variation()) {
                continue;
            }

            $is_taxonomy = $attribute->is_taxonomy();
            $options = $is_taxonomy
                ? wc_get_product_terms($item->get_id(), $attribute->get_name(), ['fields' => 'all'])
                : $attribute->get_options();

            if (!$options) {
                continue;
            }

            $key = sanitize_title($attribute->get_name());
            $choices = [];

            foreach ($options as $option) {
                $value = (string)($is_taxonomy ? $option->slug : $option);
                $choices[] = [
                    'value' => $value,
                    'label' => (string)($is_taxonomy ? $option->name : $option),
                    // A single option is no choice to make
                    'selected' => count($options) === 1 || ($defaults[$key] ?? '') === $value,
                ];
            }

            $attributes[] = [
                'name' => wc_variation_attribute_name($attribute->get_name()),
                'label' => $is_taxonomy
                    ? ($attribute->get_taxonomy_object()->attribute_label ?? $attribute->get_name())
                    : $attribute->get_name(),
                'input_name' => 'wc_bundles_selections[' . $item->get_id() . '][' . wc_variation_attribute_name($attribute->get_name()) . ']',
                'options' => $choices,
            ];
        }

        $variations = wc_bundles_get_in_stock_variations($item, array_column($attributes, 'name'));
    }

    return [
        'id' => $item->get_id(),
        'name' => $item->get_name(),
        'url' => $item->get_permalink(),
        'price_html' => $free ? wc_bundles_free_price_html($item) : $item->get_price_html(),
        'thumbnail_html' => $item->get_image('woocommerce_gallery_thumbnail', ['class' => 'wc-bundles-thumbnail', 'alt' => '', 'loading' => 'lazy']),
        'free' => $free,
        'attributes' => $attributes,
        'variations' => $variations,
    ];
}
