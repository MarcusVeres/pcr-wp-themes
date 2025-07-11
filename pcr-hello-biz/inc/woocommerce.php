<?php
/**
 * WooCommerce customizations
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add WooCommerce theme support
 */
function pcr_woocommerce_support() {
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
}
add_action('after_setup_theme', 'pcr_woocommerce_support');

/**
 * Your WooCommerce customizations go below this line
 */