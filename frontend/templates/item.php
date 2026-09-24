<?php
/**
 * One bundled product as an accordion group, with data prepared by wc_bundles_prepare_item().
 *
 * @var array $item Prepared item supplied by summary.php.
 */

defined('ABSPATH') || exit;

?>

<details class="wc-bundles-item" name="wc-bundles-item" data-product-id="<?php echo esc_attr($item['id']); ?>" <?php echo $item['open'] ? 'open' : ''; ?>>
    <summary class="wc-bundles-item-header">
        <?php echo $item['thumbnail_html']; ?>
        <span>
            <span class="wc-bundles-item-title"><?php echo esc_html($item['name']); ?></span>

            <?php

            if ($item['attributes']) {
                ?>

                <span class="wc-bundles-selection wc-bundles-note"><?php esc_html_e('Choose options', 'wc-bundles'); ?></span>

                <?php
            }

            if ($item['free']) {
                ?>

                <span class="wc-bundles-free-badge"><?php esc_html_e('Free with this bundle', 'wc-bundles'); ?></span>

                <?php
            }

            ?>
        </span>
    </summary>
    <div class="wc-bundles-item-body">
        <?php

        foreach ($item['attributes'] as $attribute) {
            ?>

            <fieldset class="wc-bundles-attribute" data-attribute="<?php echo esc_attr($attribute['name']); ?>" data-label="<?php echo esc_attr($attribute['label']); ?>" aria-describedby="wc-bundles-status-<?php echo esc_attr($item['id']); ?>">
                <legend class="wc-bundles-attribute-label"><?php echo esc_html($attribute['label']); ?></legend>
                <?php

                foreach ($attribute['options'] as $option) {
                    ?>

                    <label class="wc-bundles-option<?php echo $option['color'] ? ' wc-bundles-swatch' : ''; ?>"<?php echo $option['color'] ? ' style="background-color: ' . esc_attr($option['color']) . '"' : ''; ?>>
                        <input class="wc-bundles-option-input" type="radio" form="wc-bundles-cart" name="<?php echo esc_attr($attribute['input_name']); ?>" value="<?php echo esc_attr($option['value']); ?>" required <?php checked($option['selected']); ?>>
                        <?php echo $option['color'] ? '<span class="wc-bundles-swatch-label">' . esc_html($option['label']) . '</span>' : esc_html($option['label']); ?>
                    </label>

                    <?php
                }

                ?>
            </fieldset>

            <?php
        }

        if ($item['attributes']) {
            ?>

            <p id="wc-bundles-status-<?php echo esc_attr($item['id']); ?>" class="wc-bundles-availability" role="status" aria-atomic="true"></p>

            <?php
        }

        ?>

        <div class="wc-bundles-item-footer">
            <a class="wc-bundles-details-link" href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr(sprintf(__('View full product page for %s (opens in a new tab)', 'wc-bundles'), $item['name'])); ?>"><?php esc_html_e('Full product page', 'wc-bundles'); ?></a>
            <span class="wc-bundles-price<?php echo $item['free'] ? ' wc-bundles-free-price' : ''; ?>"><?php echo wc_bundles_kses_html($item['price_html']); ?></span>
        </div>
    </div>
</details>
