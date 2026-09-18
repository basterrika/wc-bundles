<?php
/**
 * Bundled products and their summary, rendered at WooCommerce's bundle add-to-cart hook.
 *
 * @var array $data Prepared data supplied by wc_bundles_render_summary().
 * @var WC_Product $product Current bundle supplied by wc_bundles_render_summary().
 */

defined('ABSPATH') || exit;

?>

<div class="wc-bundles">
    <?php

    // Progress only means something when there are options to choose
    if ($data['has_options']) {
        ?>

        <div class="wc-bundles-progress">
            <span class="wc-bundles-progress-label"><?php esc_html_e('Items selected', 'wc-bundles'); ?></span>
            <span class="wc-bundles-count" role="status"><?php echo esc_html($data['ready'] . '/' . count($data['items'])); ?></span>
        </div>
        <p class="wc-bundles-hint wc-bundles-note" data-ready="<?php esc_attr_e('Your bundle is ready to add.', 'wc-bundles'); ?>"><?php esc_html_e('Choose the options for each product.', 'wc-bundles'); ?></p>

        <?php
    }

    ?>

    <div class="wc-bundles-items">
        <?php

        foreach ($data['items'] as $item) {
            require WC_BUNDLES_PLUGIN_PATH . 'frontend/templates/item.php';
        }

        ?>
    </div>

    <div class="wc-bundles-total" role="status" aria-atomic="true">
        <span class="wc-bundles-total-label"><?php esc_html_e('Bundle total', 'wc-bundles'); ?></span>
        <span class="wc-bundles-total-value"><?php echo wc_bundles_kses_html($data['total_html']); ?></span>
    </div>

    <?php

    if (!$data['available']) {
        ?>

        <p class="wc-bundles-hint wc-bundles-unavailable" role="status"><?php esc_html_e('This bundle is currently unavailable.', 'wc-bundles'); ?></p>

        <?php
    }

    if ($data['has_options']) {
        ?>

        <button class="wc-bundles-retry" type="button" hidden><?php esc_html_e('Try again', 'wc-bundles'); ?></button>

        <?php
    }

    do_action('woocommerce_before_add_to_cart_form');

    ?>

    <form id="wc-bundles-cart" class="cart wc-bundles-cart" action="<?php echo esc_url($data['form_action']); ?>" method="post" enctype="multipart/form-data">
        <input type="hidden" name="add-to-cart" value="<?php echo esc_attr($product->get_id()); ?>">

        <?php

        foreach ($data['items'] as $item) {
            ?>

            <input type="hidden" name="wc_bundles_items[]" value="<?php echo esc_attr($item['id']); ?>">

            <?php
        }

        do_action('woocommerce_before_add_to_cart_button');

        ?>

        <button class="single_add_to_cart_button button alt wc-bundles-purchase" type="submit" <?php disabled(!$data['available']); ?>><?php esc_html_e('Add to cart', 'wc-bundles'); ?></button>

        <?php do_action('woocommerce_after_add_to_cart_button'); ?>
    </form>
</div>

<?php

do_action('woocommerce_after_add_to_cart_form');
