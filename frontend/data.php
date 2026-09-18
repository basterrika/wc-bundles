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

    // One query primes every variation's meta for wc_bundles_get_variation_map()
    if ($children) {
        update_meta_cache('post', array_merge(...$children));
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
        'available' => wc_bundles_is_complete($product),
        'form_action' => apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink()),
    ];
}

/**
 * Read in-stock variations and option images from post meta, without loading variation objects.
 * Only a hint for the controls: the selection endpoint and the cart still validate through WooCommerce.
 *
 * @param list<string> $names Variation attribute meta keys, in display order.
 *
 * @return array{variations: list<list<string>>, images: array<string, array<string, int>>}
 */
function wc_bundles_get_variation_map(WC_Product_Variable $item, array $names): array {
    $variations = [];
    $found = [];

    foreach ($item->get_visible_children() as $id) {
        $meta = get_post_meta($id);
        $values = [];

        foreach ($names as $name) {
            $values[] = (string)($meta[$name][0] ?? '');
        }

        // Prices are left to WooCommerce: filters can price a variation whose meta is empty, and wrongly disabling a buyable option is the worse mistake
        if (($meta['_stock_status'][0] ?? 'instock') !== 'outofstock') {
            $variations[] = $values;
        }

        foreach ($names as $index => $name) {
            if ($values[$index] !== '') {
                $found[$name][$values[$index]][(int)($meta['_thumbnail_id'][0] ?? 0)] = true;
            }
        }
    }

    $images = [];

    // An attribute gets image swatches only when each of its options maps to one image and the options differ
    foreach ($found as $name => $options) {
        $ids = [];

        foreach ($options as $value => $option_images) {
            if (count($option_images) !== 1 || !key($option_images)) {
                continue 2;
            }

            $ids[$value] = key($option_images);
        }

        if (count(array_unique($ids)) > 1) {
            $images[$name] = $ids;
        }
    }

    return ['variations' => $variations, 'images' => $images];
}

/**
 * Prepare display values and option controls without loading variation objects.
 */
function wc_bundles_prepare_item(WC_Product $item, bool $free = false): array {
    $attributes = [];
    $variations = [];

    if ($item->is_type('variable')) {
        $defaults = $item->get_default_attributes();
        $rows = [];

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
                    'image_html' => '',
                ];
            }

            $rows[] = [
                'name' => wc_variation_attribute_name($attribute->get_name()),
                'label' => $is_taxonomy
                    ? ($attribute->get_taxonomy_object()->attribute_label ?? $attribute->get_name())
                    : $attribute->get_name(),
                'input_name' => 'wc_bundles_selections[' . $item->get_id() . '][' . wc_variation_attribute_name($attribute->get_name()) . ']',
                'options' => $choices,
            ];
        }

        $map = wc_bundles_get_variation_map($item, array_column($rows, 'name'));
        $variations = $map['variations'];

        foreach ($rows as $row) {
            foreach ($row['options'] as $index => $choice) {
                $image_id = $map['images'][$row['name']][$choice['value']] ?? 0;

                if ($image_id) {
                    $row['options'][$index]['image_html'] = wp_get_attachment_image($image_id, 'woocommerce_gallery_thumbnail', false, ['alt' => '', 'loading' => 'lazy']);
                }
            }

            $attributes[] = $row;
        }
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
