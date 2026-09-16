<?php
/**
 * Save bundle items before WooCommerce persists the product.
 *
 * @var WC_Product $product Product passed by wc_bundles_save_bundle_items().
 */

defined('ABSPATH') || exit;

$submitted_ids = $_POST['wc_bundles_item_ids'] ?? [];

if (!is_array($submitted_ids)) {
    return;
}

$item_ids = wp_parse_id_list(array_filter(wp_unslash($submitted_ids), 'is_numeric'));
$valid_ids = [];

if ($item_ids) {
    _prime_post_caches($item_ids);
}

foreach ($item_ids as $item_id) {
    if (!$item_id || $item_id === $product->get_id()) {
        continue;
    }

    $item = wc_get_product($item_id);

    if (!$item || !$item->is_type(['simple', 'variable']) || !wc_products_array_filter_readable($item)) {
        continue;
    }

    $valid_ids[] = $item_id;
}

if ($valid_ids) {
    $product->update_meta_data('_wc_bundles_item_ids', $valid_ids);
}
else {
    $product->delete_meta_data('_wc_bundles_item_ids');
}
