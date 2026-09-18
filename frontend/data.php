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
        $allowed_html['img']['fetchpriority'] = true;
        $allowed_html['bdi']['dir'] = true;
    }

    return wp_kses($html, $allowed_html);
}

/**
 * Prepare one render snapshot shared by the page and its summary callback.
 */
function wc_bundles_get_frontend_data(WC_Product $product): array {
    static $views = [];

    $product_id = $product->get_id();

    if (isset($views[$product_id])) {
        return $views[$product_id];
    }

    $products = wc_bundles_get_items($product);
    $free_ids = wc_bundles_get_free_ids($product);
    $items = [];
    $has_options = false;

    foreach ($products as $item) {
        $data = wc_bundles_prepare_item($item, in_array($item->get_id(), $free_ids, true));
        $items[] = $data;
        $has_options = $has_options || $data['attributes'] !== [];
    }

    return $views[$product_id] = [
        'items' => $items,
        'total_html' => wc_bundles_format_total(wc_bundles_calculate_total($products, true, $free_ids)),
        'has_options' => $has_options,
        'available' => wc_bundles_is_complete($product),
        'form_action' => apply_filters('woocommerce_add_to_cart_form_action', $product->get_permalink()),
    ];
}

/**
 * Prepare display values and option controls without loading variation objects.
 */
function wc_bundles_prepare_item(WC_Product $item, bool $free = false): array {
    $attributes = [];
    $is_variable = $item->is_type('variable');

    if ($is_variable) {
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
                    'selected' => ($defaults[$key] ?? '') === $value,
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
    }

    return [
        'id' => $item->get_id(),
        'name' => $item->get_name(),
        'url' => $item->get_permalink(),
        'description' => wp_trim_words(wp_strip_all_tags(strip_shortcodes($item->get_short_description())), 45),
        'price_html' => $free ? wc_bundles_free_price_html($item) : $item->get_price_html(),
        'image_html' => $item->get_image('woocommerce_thumbnail', ['class' => 'wc-bundles-product-image']),
        'thumbnail_html' => $item->get_image('woocommerce_gallery_thumbnail', ['class' => 'wc-bundles-thumbnail', 'alt' => '', 'loading' => 'lazy']),
        'is_variable' => $is_variable,
        'free' => $free,
        'attributes' => $attributes,
    ];
}
