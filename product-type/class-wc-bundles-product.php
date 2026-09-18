<?php

defined('ABSPATH') || exit;

class WC_Bundles_Product extends WC_Product {
    public function get_type(): string {
        return 'bundle';
    }

    public function is_purchasable(): false {
        // The bundle adds its components instead of a standalone cart line
        return false;
    }

    /**
     * Show the same total as the product page: one of each paid component, free ones excluded.
     */
    public function get_price_html($deprecated = ''): string {
        $total = wc_bundles_calculate_total(wc_bundles_get_items($this), true, wc_bundles_get_free_ids($this));
        $html = $total ? wc_bundles_format_total($total) : apply_filters('woocommerce_empty_price_html', '', $this);

        return apply_filters('woocommerce_get_price_html', $html, $this);
    }
}
