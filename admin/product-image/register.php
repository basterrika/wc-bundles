<?php

defined('ABSPATH') || exit;

add_action('admin_enqueue_scripts', 'wc_bundles_enqueue_product_image_script');
function wc_bundles_enqueue_product_image_script(): void {
    $screen = get_current_screen();

    if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'product') {
        return;
    }

    wp_enqueue_script(
        'wc-bundles-product-image',
        WC_BUNDLES_PLUGIN_URL . 'admin/product-image/script.js',
        ['post'],
        WC_BUNDLES_VERSION,
        true
    );
}

// Also runs over AJAX, where WordPress re-renders the metabox after the image changes
add_filter('admin_post_thumbnail_html', 'wc_bundles_add_generate_image_link', 10, 3);
function wc_bundles_add_generate_image_link(string $content, int $post_id, mixed $thumbnail_id): string {
    // Only offered while there is no image, which WordPress passes as null, '' or -1
    if (((int)$thumbnail_id > 0 && get_post((int)$thumbnail_id)) || get_post_type($post_id) !== 'product' || !function_exists('imagecreatetruecolor')) {
        return $content;
    }

    // WooCommerce toggles show_if_bundle on load and type change, but not on AJAX re-renders
    $hidden = wp_doing_ajax() && WC_Product_Factory::get_product_type($post_id) !== 'bundle';

    return $content . sprintf(
        '<p class="show_if_bundle"%s><a href="#" class="wc-bundles-generate-image" data-nonce="%s">%s</a></p>',
        $hidden ? ' style="display: none;"' : '',
        esc_attr(wp_create_nonce('wc_bundles_generate_image_' . $post_id)),
        esc_html__('Generate from bundle products', 'wc-bundles')
    );
}

add_action('wp_ajax_wc_bundles_generate_image', 'wc_bundles_ajax_generate_image');
function wc_bundles_ajax_generate_image(): void {
    $post_id = absint($_POST['post_id'] ?? 0);

    check_ajax_referer('wc_bundles_generate_image_' . $post_id, 'nonce');

    // Generating adds a file to the media library, which needs its own capability
    if (!current_user_can('edit_post', $post_id) || !current_user_can('upload_files') || get_post_type($post_id) !== 'product') {
        wp_send_json_error(__('You are not allowed to edit this product.', 'wc-bundles'), 403);
    }

    $submitted_ids = $_POST['item_ids'] ?? [];

    require WC_BUNDLES_PLUGIN_PATH . 'admin/product-image/generate.php';

    $result = wc_bundles_generate_image($post_id, is_array($submitted_ids) ? wp_parse_id_list($submitted_ids) : []);

    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message(), 400);
    }

    wp_send_json_success(_wp_post_thumbnail_html($result, $post_id));
}
