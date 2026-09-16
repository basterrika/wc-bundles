<?php
/**
 * Bundle product page.
 *
 * @var WC_Product $product Current bundle supplied by wc_bundles_render_product().
 * @var array $data Prepared frontend data.
 */

defined('ABSPATH') || exit;

?>

<div id="product-<?php echo esc_attr($product->get_id()); ?>" <?php wc_product_class('wc-bundles', $product); ?>>
    <header class="wc-bundles-header">
        <?php woocommerce_template_single_title(); ?>
    </header>

    <?php do_action('woocommerce_before_single_product_summary'); ?>

    <div class="wc-bundles-layout">
        <div class="wc-bundles-items">
            <?php

            if ($data['items']) {
                foreach ($data['items'] as $item) {
                    require WC_BUNDLES_PLUGIN_PATH . 'frontend/templates/item.php';
                }
            }
            else {
                ?>

                <p class="wc-bundles-hint"><?php esc_html_e('No products are available in this bundle yet.', 'wc-bundles'); ?></p>

                <?php
            }

            ?>
        </div>

        <aside class="summary entry-summary wc-bundles-summary" aria-label="<?php esc_attr_e('Product summary', 'wc-bundles'); ?>">
            <?php do_action('woocommerce_single_product_summary'); ?>
        </aside>
    </div>

    <?php do_action('woocommerce_after_single_product_summary'); ?>
</div>

<?php do_action('woocommerce_after_single_product'); ?>
