<?php
/**
 * Core theme setup - enqueue styles
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enqueue parent and child theme styles
 */
function pcr_enqueue_child_styles() {
    // Parent theme CSS
    wp_enqueue_style(
        'hello-biz-parent',
        get_template_directory_uri() . '/style.css',
        array(),
        wp_get_theme(get_template())->get('Version') // Fixed: get parent theme version
    );
    
    // Child theme CSS
    wp_enqueue_style(
        'pcr-hello-biz-child',
        get_stylesheet_directory_uri() . '/style.css', // Fixed: was styles.css
        array('hello-biz-parent'),
        wp_get_theme()->get('Version') // Child theme version
    );
}
add_action('wp_enqueue_scripts', 'pcr_enqueue_child_styles');