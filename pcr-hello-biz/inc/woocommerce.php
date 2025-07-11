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
 * Register Artists taxonomy for products
 */
function pcr_register_artists_taxonomy() {
    $labels = array(
        'name'              => _x('Artists', 'taxonomy general name'),
        'singular_name'     => _x('Artist', 'taxonomy singular name'),
        'search_items'      => __('Search Artists'),
        'all_items'         => __('All Artists'),
        'parent_item'       => __('Parent Artist'),
        'parent_item_colon' => __('Parent Artist:'),
        'edit_item'         => __('Edit Artist'),
        'update_item'       => __('Update Artist'),
        'add_new_item'      => __('Add New Artist'),
        'new_item_name'     => __('New Artist Name'),
        'menu_name'         => __('Artists'),
    );

    $args = array(
        'hierarchical'      => false, // Like tags (artists aren't hierarchical)
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_nav_menus' => true,
        'show_tagcloud'     => false,
        'public'            => true,
        'publicly_queryable' => true,
        'rewrite'           => array('slug' => 'artist'),
        'show_in_rest'      => true, // For Gutenberg/REST API
        'meta_box_cb'       => false, // We'll use a custom meta box
    );

    register_taxonomy('product_artist', array('product'), $args);
}
add_action('init', 'pcr_register_artists_taxonomy');

/**
 * Add custom meta box for artists (easier to use than default)
 */
function pcr_add_artist_meta_box() {
    add_meta_box(
        'product-artist',
        __('Artist'),
        'pcr_artist_meta_box_callback',
        'product',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'pcr_add_artist_meta_box');

/**
 * Artist meta box callback - simple text input
 */
function pcr_artist_meta_box_callback($post) {
    $terms = wp_get_post_terms($post->ID, 'product_artist');
    $artist_name = !empty($terms) ? $terms[0]->name : '';
    
    echo '<label for="product_artist_input">' . __('Artist Name:') . '</label>';
    echo '<input type="text" id="product_artist_input" name="product_artist_input" value="' . esc_attr($artist_name) . '" style="width: 100%; margin-top: 5px;" />';
    echo '<p class="description">Enter the artist name. This will be used for filtering and links.</p>';
}

/**
 * Save artist when product is saved
 */
function pcr_save_artist_meta_box($post_id) {
    if (!isset($_POST['product_artist_input'])) {
        return;
    }

    $artist_name = sanitize_text_field($_POST['product_artist_input']);
    
    if (!empty($artist_name)) {
        // Set the artist term
        wp_set_object_terms($post_id, $artist_name, 'product_artist');
    } else {
        // Remove artist if empty
        wp_set_object_terms($post_id, array(), 'product_artist');
    }
}
add_action('save_post_product', 'pcr_save_artist_meta_box');

/**
 * Helper function to get artist name for a product
 */
function pcr_get_product_artist($product_id = null) {
    if (!$product_id) {
        global $post;
        $product_id = $post->ID;
    }
    
    $terms = wp_get_post_terms($product_id, 'product_artist');
    return !empty($terms) ? $terms[0]->name : '';
}

/**
 * Helper function to get artist link for a product
 */
function pcr_get_product_artist_link($product_id = null) {
    if (!$product_id) {
        global $post;
        $product_id = $post->ID;
    }
    
    $terms = wp_get_post_terms($product_id, 'product_artist');
    if (!empty($terms)) {
        $artist_url = get_term_link($terms[0]);
        return '<a href="' . esc_url($artist_url) . '">' . esc_html($terms[0]->name) . '</a>';
    }
    return '';
}

/**
 * Shortcode to display artist link [pcr_artist_link]
 */
function pcr_artist_link_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => null,
        'format' => 'link', // 'link' or 'name'
    ), $atts);
    
    $product_id = $atts['id'] ? $atts['id'] : get_the_ID();
    
    if ($atts['format'] === 'name') {
        return pcr_get_product_artist($product_id);
    } else {
        return pcr_get_product_artist_link($product_id);
    }
}
add_shortcode('pcr_artist_link', 'pcr_artist_link_shortcode');

/**
 * Shortcode to display product title without artist [pcr_album_title]
 * Simple approach: find artist, remove it, remove any separator that follows
 */
function pcr_album_title_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => null,
        'strip_separators' => true, // set to false if you want to keep separators
    ), $atts);
    
    $product_id = $atts['id'] ? $atts['id'] : get_the_ID();
    $full_title = get_the_title($product_id);
    $artist_name = pcr_get_product_artist($product_id);
    
    // If no artist, return full title
    if (empty($artist_name)) {
        return $full_title;
    }
    
    // Simple string replacement - remove artist from beginning
    if (strpos($full_title, $artist_name) === 0) {
        $album_title = substr($full_title, strlen($artist_name));
        
        // If strip_separators is true, remove common separators from the start
        if ($atts['strip_separators']) {
            $album_title = ltrim($album_title, " \t\n\r\0\x0B-–—‒⁃");
        }
        
        $album_title = trim($album_title);
        
        // If nothing left after stripping, return original
        return empty($album_title) ? $full_title : $album_title;
    }
    
    // Artist not at beginning? Return full title
    return $full_title;
}
add_shortcode('pcr_album_title', 'pcr_album_title_shortcode');

/**
 * Your other WooCommerce customizations go below this line
 */