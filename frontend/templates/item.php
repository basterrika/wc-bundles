<?php
/**
 * One bundled product, with data prepared by wc_bundles_prepare_item().
 *
 * @var array $item Prepared item supplied by single-product.php.
 */

defined('ABSPATH') || exit;

?>

<section id="wc-bundles-item-<?php echo esc_attr($item['id']); ?>" class="wc-bundles-item" data-product-id="<?php echo esc_attr($item['id']); ?>" aria-labelledby="wc-bundles-item-title-<?php echo esc_attr($item['id']); ?>" <?php if ($item['is_variable']) : ?>data-summary-id="wc-bundles-selection-<?php echo esc_attr($item['id']); ?>"<?php endif; ?>>
    <div class="wc-bundles-image">
        <?php echo $item['image_html']; ?>
    </div>
    <div class="wc-bundles-details">
        <h2 id="wc-bundles-item-title-<?php echo esc_attr($item['id']); ?>" class="wc-bundles-item-title"><?php echo esc_html($item['name']); ?></h2>

        <?php

        if ($item['description']) {
            ?>

            <p class="wc-bundles-description"><?php echo esc_html($item['description']); ?></p>

            <?php
        }

        foreach ($item['attributes'] as $attribute) {
            ?>

            <fieldset class="wc-bundles-attribute" data-attribute="<?php echo esc_attr($attribute['name']); ?>" data-label="<?php echo esc_attr($attribute['label']); ?>">
                <legend class="wc-bundles-attribute-label"><?php echo esc_html($attribute['label']); ?></legend>
                <div class="wc-bundles-options">
                    <?php

                    foreach ($attribute['options'] as $option) {
                        ?>

                        <label class="wc-bundles-option">
                            <input class="wc-bundles-option-input" type="radio" form="wc-bundles-cart" name="<?php echo esc_attr($attribute['input_name']); ?>" value="<?php echo esc_attr($option['value']); ?>" required <?php checked($option['selected']); ?>>
                            <span class="wc-bundles-option-label"><?php echo esc_html($option['label']); ?></span>
                        </label>

                        <?php
                    }

                    ?>
                </div>
            </fieldset>

            <?php
        }

        ?>

        <div class="wc-bundles-item-footer">
            <a href="<?php echo esc_url($item['url']); ?>" aria-label="<?php echo esc_attr(sprintf(__('View product details for %s', 'wc-bundles'), $item['name'])); ?>"><?php esc_html_e('View product details', 'wc-bundles'); ?></a>

            <?php

            if ($item['is_variable'] || $item['price_html']) {
                ?>

                <span class="wc-bundles-price"><?php echo wc_bundles_kses_html($item['price_html']); ?></span>

                <?php
            }

            ?>
        </div>

        <?php

        if ($item['is_variable']) {
            ?>

            <p class="wc-bundles-availability" role="status" aria-atomic="true"></p>

            <?php
        }

        ?>
    </div>
</section>
