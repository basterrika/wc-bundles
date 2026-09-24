<?php

defined('ABSPATH') || exit;

add_action('current_screen', 'wc_bundles_init_attribute_color');
function wc_bundles_init_attribute_color(WP_Screen $screen): void {
    if ($screen->base !== 'term' || !taxonomy_is_product_attribute($screen->taxonomy)) {
        return;
    }

    add_action($screen->taxonomy . '_edit_form_fields', 'wc_bundles_render_attribute_color_field');

    wp_enqueue_style('wp-color-picker');
    wp_enqueue_script('wp-color-picker');
    wp_add_inline_script('wp-color-picker', "jQuery('#wc-bundles-color').wpColorPicker();");
}

function wc_bundles_render_attribute_color_field(WP_Term $term): void {
    ?>

    <tr class="form-field">
        <th scope="row"><label for="wc-bundles-color"><?php esc_html_e('Bundle swatch color', 'wc-bundles'); ?></label></th>
        <td>
            <input id="wc-bundles-color" name="wc_bundles_color" type="text" value="<?php echo esc_attr(get_term_meta($term->term_id, 'wc_bundles_color', true)); ?>">
            <p class="description"><?php esc_html_e('Shown as a color swatch in bundles. Leave empty to show the term name.', 'wc-bundles'); ?></p>
        </td>
    </tr>

    <?php
}

add_action('edited_term', 'wc_bundles_save_attribute_color', 10, 3);
function wc_bundles_save_attribute_color(int $term_id, int $tt_id, string $taxonomy): void {
    $raw = $_POST['wc_bundles_color'] ?? null;

    if (!is_string($raw) || !taxonomy_is_product_attribute($taxonomy)) {
        return;
    }

    $raw = trim(wp_unslash($raw));

    if ($raw === '') {
        delete_term_meta($term_id, 'wc_bundles_color');
    }
    elseif ($color = sanitize_hex_color($raw)) {
        update_term_meta($term_id, 'wc_bundles_color', $color);
    }
}
