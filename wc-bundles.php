<?php
/**
 * Plugin Name: Bundles for WooCommerce
 * Description: Create and manage product bundles for WooCommerce.
 * Version: 1.0.0
 * Author: Mikel
 * Author URI: https://basterrika.com
 * Update URI: https://github.com/basterrika/wc-bundles
 * Text Domain: wc-bundles
 * Requires PHP: 8.4
 * Requires at least: 6.5
 * Domain Path: /translations
 * Requires Plugins: woocommerce
 */

defined('ABSPATH') || exit;

const WC_BUNDLES_VERSION = '1.0.0';

define('WC_BUNDLES_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WC_BUNDLES_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WC_BUNDLES_PLUGIN_URL', plugin_dir_url(__FILE__));

add_action('plugins_loaded', static function() {
    if (!class_exists('WooCommerce')) {
        return;
    }

    require_once WC_BUNDLES_PLUGIN_PATH . 'product-type/register.php';

    if (!is_admin()) {
        require_once WC_BUNDLES_PLUGIN_PATH . 'frontend/init.php';
    }

    if (is_admin() && !wp_doing_ajax()) {
        require_once WC_BUNDLES_PLUGIN_PATH . 'admin/init.php';
    }
}, 20);
