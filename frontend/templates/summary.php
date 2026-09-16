<?php
/**
 * Bundle summary, rendered at WooCommerce's bundle add-to-cart hook.
 *
 * @var array $data Prepared data supplied by wc_bundles_render_summary().
 */

defined('ABSPATH') || exit;

?>

<h2 class="wc-bundles-summary-title"><?php esc_html_e('Your bundle', 'wc-bundles'); ?></h2>
<p class="wc-bundles-hint"><?php esc_html_e('One of each product is included.', 'wc-bundles'); ?></p>
<ul class="wc-bundles-summary-list">
    <?php

    foreach ($data['items'] as $item) {
        ?>

        <li class="wc-bundles-summary-item">
            <?php echo $item['thumbnail_html']; ?>
            <div class="wc-bundles-summary-details">
                <a href="#wc-bundles-item-<?php echo esc_attr($item['id']); ?>"><?php echo esc_html($item['name']); ?></a>

                <?php

                if ($item['is_variable']) {
                    ?>

                    <p id="wc-bundles-selection-<?php echo esc_attr($item['id']); ?>" class="wc-bundles-selection" role="status" aria-atomic="true" data-placeholder="<?php esc_attr_e('Choose options', 'wc-bundles'); ?>"><?php esc_html_e('Choose options', 'wc-bundles'); ?></p>

                    <?php
                }

                ?>
            </div>
            <span class="wc-bundles-quantity">&times; 1</span>
        </li>

        <?php
    }

    ?>
</ul>

<div class="wc-bundles-total" role="status" aria-atomic="true">
    <span class="wc-bundles-total-label"><?php esc_html_e('Bundle total', 'wc-bundles'); ?></span>
    <span class="wc-bundles-total-value"><?php echo wc_bundles_kses_html($data['total_html']); ?></span>
</div>

<?php

if ($data['has_options']) {
    ?>

    <button class="wc-bundles-retry" type="button" hidden><?php esc_html_e('Try again', 'wc-bundles'); ?></button>

    <?php
}

do_action('woocommerce_before_add_to_cart_form');

?>

<form class="cart wc-bundles-cart" action="<?php echo esc_url($data['form_action']); ?>" method="post" enctype="multipart/form-data">
    <?php do_action('woocommerce_before_add_to_cart_button'); ?>
    <button class="single_add_to_cart_button button alt wc-bundles-purchase" type="button" disabled><?php esc_html_e('Add to cart', 'wc-bundles'); ?></button>
    <?php do_action('woocommerce_after_add_to_cart_button'); ?>
</form>

<?php do_action('woocommerce_after_add_to_cart_form'); ?>
