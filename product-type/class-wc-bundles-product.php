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
}
