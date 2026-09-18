<?php
/**
 * One bundled product as an accordion group, with data prepared by wc_bundles_prepare_item().
 *
 * @var array $item Prepared item supplied by summary.php.
 */

defined('ABSPATH') || exit;

?>

<details class="wc-bundles-item" name="wc-bundles-item" data-product-id="<?php echo esc_attr($item['id']); ?>" <?php if ($item['variations']) : ?>data-variations="<?php echo esc_attr(wp_json_encode($item['variations'])); ?>"<?php endif; ?> <?php echo $item['open'] ? 'open' : ''; ?>>
    <summary class="wc-bundles-item-header">
        <?php echo $item['thumbnail_html']; ?>
        <span>
            <span class="wc-bundles-item-title"><?php echo esc_html($item['name']); ?></span>

            <?php

            if ($item['free']) {
                ?>

                <span class="wc-bundles-note"><?php esc_html_e('Free with this bundle', 'wc-bundles'); ?></span>

                <?php
            }

            if ($item['attributes']) {
                ?>

                <span class="wc-bundles-selection wc-bundles-note"><?php esc_html_e('Choose options', 'wc-bundles'); ?></span>

                <?php
            }

            ?>
        </span>
        <span class="wc-bundles-price<?php echo $item['free'] ? ' wc-bundles-free-price' : ''; ?>"><?php echo wc_bundles_kses_html($item['price_html']); ?></span>
    </summary>
    <div class="wc-bundles-item-body">
        <?php

        foreach ($item['attributes'] as $attribute) {
            ?>

            <fieldset class="wc-bundles-attribute" data-attribute="<?php echo esc_attr($attribute['name']); ?>" data-label="<?php echo esc_attr($attribute['label']); ?>" aria-describedby="wc-bundles-status-<?php echo esc_attr($item['id']); ?>">
                <legend class="wc-bundles-attribute-label"><?php echo esc_html($attribute['label']); ?>: <span class="wc-bundles-attribute-value"></span></legend>
                <div class="wc-bundles-options">
                    <?php

                    foreach ($attribute['options'] as $option) {
                        ?>

                        <label class="wc-bundles-option<?php echo $option['image_html'] ? ' wc-bundles-swatch' : ''; ?>">
                            <input class="wc-bundles-option-input" type="radio" form="wc-bundles-cart" name="<?php echo esc_attr($attribute['input_name']); ?>" value="<?php echo esc_attr($option['value']); ?>" data-label="<?php echo esc_attr($option['label']); ?>" required <?php checked($option['selected']); ?>>
                            <span class="wc-bundles-option-label">
                                <?php

                                if ($option['image_html']) {
                                    echo $option['image_html'];
                                    echo '<span class="screen-reader-text">' . esc_html($option['label']) . '</span>';
                                }
                                else {
                                    echo esc_html($option['label']);
                                }

                                ?>
                            </span>
                        </label>

                        <?php
                    }

                    ?>
                </div>
            </fieldset>

            <?php
        }

        if ($item['attributes']) {
            ?>

            <p id="wc-bundles-status-<?php echo esc_attr($item['id']); ?>" class="wc-bundles-availability" role="status" aria-atomic="true"></p>

            <?php
        }

        ?>

        <a class="wc-bundles-details-link" href="<?php echo esc_url($item['url']); ?>" aria-label="<?php echo esc_attr(sprintf(__('View product details for %s', 'wc-bundles'), $item['name'])); ?>"><?php esc_html_e('Product details', 'wc-bundles'); ?></a>
    </div>
</details>
