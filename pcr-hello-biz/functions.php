<?php
/**
 * PCR Hello Biz Child Theme - Clean Reference File
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('CHILD_THEME_DIR', get_stylesheet_directory());
define('CHILD_THEME_URL', get_stylesheet_directory_uri());

/**
 * Core theme setup - always loaded
 */
require_once CHILD_THEME_DIR . '/inc/theme-setup.php';
require_once CHILD_THEME_DIR . '/inc/scroll-admin.php';
require_once CHILD_THEME_DIR . '/inc/scroll-utilities.php';

/**
 * Conditional includes - only load when plugins are active
 */

// WooCommerce (only if WooCommerce is active)
if (class_exists('WooCommerce')) {
    require_once CHILD_THEME_DIR . '/inc/woocommerce.php';
}

// Elementor (only if Elementor is loaded)
if (did_action('elementor/loaded')) {
    require_once CHILD_THEME_DIR . '/inc/elementor.php';
}

// ACF (only if ACF is active)
if (class_exists('ACF')) {
    require_once CHILD_THEME_DIR . '/inc/acf.php';
}

// Uncomment these as needed:
// require_once CHILD_THEME_DIR . '/inc/custom-post-types.php';
// require_once CHILD_THEME_DIR . '/inc/admin.php';