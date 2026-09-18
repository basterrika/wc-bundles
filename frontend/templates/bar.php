<?php
/**
 * Fixed purchase bar, shown on mobile while the summary is scrolled out of view.
 *
 * @var array $data Prepared frontend data.
 */

defined('ABSPATH') || exit;

?>

<div class="wc-bundles-bar" role="region" aria-label="<?php esc_attr_e('Purchase actions', 'wc-bundles'); ?>">
    <span class="wc-bundles-bar-total"><?php echo wc_bundles_kses_html($data['total_html']); ?></span>
    <button class="button wc-bundles-bar-purchase" type="submit" form="wc-bundles-cart" <?php disabled(!$data['available'] || $data['has_options']); ?>><?php esc_html_e('Add to cart', 'wc-bundles'); ?></button>
</div>
