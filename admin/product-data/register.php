<?php

defined('ABSPATH') || exit;

add_action('admin_enqueue_scripts', 'wc_bundles_enqueue_product_data_styles');
function wc_bundles_enqueue_product_data_styles(): void {
    wp_enqueue_style(
        'wc-bundles-product-data',
        WC_BUNDLES_PLUGIN_URL . 'admin/product-data/style.css',
        ['woocommerce_admin_styles'],
        WC_BUNDLES_VERSION
    );
}

add_filter('woocommerce_product_data_tabs', 'wc_bundles_add_product_data_tab');
function wc_bundles_add_product_data_tab(array $tabs): array {
    $tabs['wc_bundles_items'] = [
        'label' => __('Bundle items', 'wc-bundles'),
        'target' => 'wc_bundles_items_data',
        'class' => ['show_if_bundle'],
        'priority' => 5,
    ];

    return $tabs;
}

add_action('woocommerce_product_data_panels', 'wc_bundles_render_bundle_items_panel');
function wc_bundles_render_bundle_items_panel(): void {
    /** @var WC_Product $product_object Current product set by WooCommerce's product-data metabox. */
    global $product_object;

    $fields = [
        'wc_bundles_item_ids' => [__('Products', 'wc-bundles'), __('One of each product is included at its own price. Drag to reorder.', 'wc-bundles')],
        'wc_bundles_free_item_ids' => [__('Free products', 'wc-bundles'), __('One of each product is included at no cost while every product above is in the cart. Drag to reorder.', 'wc-bundles')],
    ];
    $excluded_types = array_diff(array_keys(wc_get_product_types()), ['simple', 'variable']);

    ?>

    <div id="wc_bundles_items_data" class="panel woocommerce_options_panel hidden">
        <input type="hidden" name="wc_bundles_items_present" value="1">
        <div class="options_group">
            <?php

            foreach ($fields as $field => [$label, $description]) {
                // Render empty fields for other types so switching to Bundle works without a reload
                $item_ids = $product_object->is_type('bundle')
                    ? $product_object->get_meta('_' . $field, true, 'edit') ?: []
                    : [];

                if ($item_ids) {
                    _prime_post_caches($item_ids);
                }

                ?>

                <div class="wc-bundles-field">
                    <label for="<?php echo esc_attr($field); ?>"><?php echo esc_html($label); ?></label>
                    <select
                        id="<?php echo esc_attr($field); ?>"
                        name="<?php echo esc_attr($field); ?>[]"
                        class="wc-product-search"
                        multiple="multiple"
                        style="width: 100%;"
                        aria-describedby="<?php echo esc_attr($field); ?>_description"
                        data-sortable="true"
                        data-action="woocommerce_json_search_products"
                        data-placeholder="<?php esc_attr_e('Search for a product…', 'wc-bundles'); ?>"
                        data-exclude="<?php echo esc_attr($product_object->get_id()); ?>"
                        data-exclude_type="<?php echo esc_attr(implode(',', $excluded_types)); ?>"
                    >
                        <?php

                        foreach ($item_ids as $item_id) {
                            $item = wc_get_product($item_id);

                            if (!$item) {
                                continue;
                            }

                            ?>

                            <option value="<?php echo esc_attr($item_id); ?>" selected="selected"><?php echo esc_html(wp_strip_all_tags($item->get_formatted_name())); ?></option>

                            <?php
                        }

                        ?>
                    </select>
                    <p id="<?php echo esc_attr($field); ?>_description" class="description"><?php echo esc_html($description); ?></p>
                </div>

                <?php
            }

            ?>
        </div>
    </div>
    <?php
}

add_action('woocommerce_admin_process_product_object', 'wc_bundles_save_bundle_items');
function wc_bundles_save_bundle_items(WC_Product $product): void {
    if (!isset($_POST['wc_bundles_items_present']) || !$product->is_type('bundle')) {
        return;
    }

    // WooCommerce has already checked the nonce, permissions and autosave state
    require WC_BUNDLES_PLUGIN_PATH . 'admin/product-data/save.php';
}
