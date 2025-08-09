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

// --------------------------------------------------------------
// BUG FIX 

/*
This filter specifically targets the WooCommerce gallery thumbnails and removes the featured image from appearing in the thumbnail gallery if it matches the product's featured image ID php - Remove featured image from the WooCommerce gallery - Stack Overflow.
The issue occurs because WooCommerce automatically includes the featured image in the gallery thumbnails, and if that same image is also manually added to the product gallery, it displays twice Product Gallery Slider, Additional Variation Images for WooCommerce – WordPress plugin | WordPress.org.
*/

add_filter('woocommerce_product_get_gallery_image_ids', 'remove_featured_from_gallery', 10, 1);
function remove_featured_from_gallery($gallery_ids) {
    global $product;
    
    if (!$product) return $gallery_ids;
    
    $featured_image_id = $product->get_image_id();
    
    // Remove featured image from gallery if it exists there
    if ($featured_image_id && in_array($featured_image_id, $gallery_ids)) {
        $gallery_ids = array_diff($gallery_ids, array($featured_image_id));
    }
    
    return $gallery_ids;
}


// --------------------------------------------------------------
// ARTIST STUFF

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
 * Shortcode to display all artists [pcr_artists_list]
 */
function pcr_artists_list_shortcode($atts) {
    $atts = shortcode_atts(array(
        'columns' => 1,           // Number of columns
        'orderby' => 'name',      // name, count, slug
        'order' => 'ASC',         // ASC or DESC
        'show_count' => false,    // Show product count
        'hide_empty' => true,     // Hide artists with no products
    ), $atts);
    
    // Get all artist terms
    $artists = get_terms(array(
        'taxonomy' => 'product_artist',
        'hide_empty' => $atts['hide_empty'],
        'orderby' => $atts['orderby'],
        'order' => $atts['order'],
    ));
    
    if (empty($artists) || is_wp_error($artists)) {
        return '<p>No artists found.</p>';
    }
    
    $output = ''; 

    // List style
    $output .= '<div class="pcr-artists-list">';
    $output .= '<ul style="list-style: none; padding: 0; columns: ' . $atts['columns'] . '; column-gap: 30px;">';
    
    foreach ($artists as $artist) {
        $artist_url = get_term_link($artist);
        $count_text = $atts['show_count'] ? ' (' . $artist->count . ')' : '';
        
        $output .= '<li style="margin-bottom: 10px; break-inside: avoid;">';
        $output .= '<a href="' . esc_url($artist_url) . '" style="text-decoration: none; font-size: 16px;">' . esc_html($artist->name) . '</a>';
        $output .= $count_text;
        $output .= '</li>';
    }
    
    $output .= '</ul>';
    $output .= '</div>';

    return $output;
}
add_shortcode('pcr_artists_list', 'pcr_artists_list_shortcode');

/**
 * Shortcode for alphabetical artist index [pcr_artists_index]
 */
function pcr_artists_index_shortcode($atts) {
    $atts = shortcode_atts(array(
        'show_count' => false,
        'hide_empty' => true,
    ), $atts);
    
    // Get all artist terms
    $artists = get_terms(array(
        'taxonomy' => 'product_artist',
        'hide_empty' => $atts['hide_empty'],
        'orderby' => 'name',
        'order' => 'ASC',
    ));
    
    if (empty($artists) || is_wp_error($artists)) {
        return '<p>No artists found.</p>';
    }
    
    // Group artists by first letter
    $grouped_artists = array();
    foreach ($artists as $artist) {
        $first_letter = strtoupper(substr($artist->name, 0, 1));
        if (!isset($grouped_artists[$first_letter])) {
            $grouped_artists[$first_letter] = array();
        }
        $grouped_artists[$first_letter][] = $artist;
    }
    
    ksort($grouped_artists);
    
    $output = '<div class="pcr-artists-index">';
    
    // Create alphabet navigation
    $output .= '<div class="alphabet-nav" style="margin-bottom: 30px; text-align: center;">';
    foreach ($grouped_artists as $letter => $artists_in_letter) {
        $output .= '<a href="#letter-' . $letter . '" style="margin: 0 5px; padding: 5px 10px; background: #f0f0f0; text-decoration: none; border-radius: 3px;">' . $letter . '</a>';
    }
    $output .= '</div>';
    
    // Display artists grouped by letter
    foreach ($grouped_artists as $letter => $artists_in_letter) {
        $output .= '<div id="letter-' . $letter . '" class="letter-group" style="margin-bottom: 30px;">';
        $output .= '<h3 style="font-size: 24px; border-bottom: 2px solid #ddd; padding-bottom: 10px;">' . $letter . '</h3>';
        $output .= '<div style="columns: 3; column-gap: 30px;">';
        
        foreach ($artists_in_letter as $artist) {
            $artist_url = get_term_link($artist);
            $count_text = $atts['show_count'] ? ' (' . $artist->count . ')' : '';
            
            $output .= '<div style="margin-bottom: 8px; break-inside: avoid;">';
            $output .= '<a href="' . esc_url($artist_url) . '" style="text-decoration: none;">' . esc_html($artist->name) . '</a>';
            $output .= $count_text;
            $output .= '</div>';
        }
        
        $output .= '</div>';
        $output .= '</div>';
    }
    
    $output .= '</div>';
    
    return $output;
}
add_shortcode('pcr_artists_index', 'pcr_artists_index_shortcode');

// --------------------------------------------------------------
// CATEGORY STUFF

/**
 * Shortcode to display product categories [pcr_categories_list]
 * Simple list format, just like artists
 */
function pcr_categories_list_shortcode($atts) {
    $atts = shortcode_atts(array(
        'columns' => 1,           // Number of columns
        'orderby' => 'name',      // name, count, slug
        'order' => 'ASC',         // ASC or DESC
        'show_count' => false,    // Show product count
        'hide_empty' => true,     // Hide categories with no products
    ), $atts);
    
    // Get all product categories (flat, no hierarchy)
    $categories = get_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => $atts['hide_empty'],
        'orderby' => $atts['orderby'],
        'order' => $atts['order'],
    ));
    
    if (empty($categories) || is_wp_error($categories)) {
        return '<p>No categories found.</p>';
    }
    
    $output = '<div class="pcr-categories-list">';
    $output .= '<ul style="list-style: none; padding: 0; columns: ' . $atts['columns'] . '; column-gap: 30px;">';
    
    foreach ($categories as $category) {
        $category_url = get_term_link($category);
        $count_text = $atts['show_count'] ? ' (' . $category->count . ')' : '';
        
        $output .= '<li style="margin-bottom: 10px; break-inside: avoid;">';
        $output .= '<a href="' . esc_url($category_url) . '" style="text-decoration: none; font-size: 16px;">' . esc_html($category->name) . '</a>';
        $output .= $count_text;
        $output .= '</li>';
    }
    
    $output .= '</ul>';
    $output .= '</div>';
    
    return $output;
}
add_shortcode('pcr_categories_list', 'pcr_categories_list_shortcode');

/**
 * Shortcode to display product tags [pcr_tags_list]
 * Simple list format, just like artists
 */
function pcr_tags_list_shortcode($atts) {
    $atts = shortcode_atts(array(
        'columns' => 4,           // Number of columns
        'orderby' => 'name',      // name, count, slug
        'order' => 'ASC',         // ASC or DESC
        'show_count' => false,    // Show product count
        'hide_empty' => true,     // Hide tags with no products
    ), $atts);
    
    // Get all product tags
    $tags = get_terms(array(
        'taxonomy' => 'product_tag',
        'hide_empty' => $atts['hide_empty'],
        'orderby' => $atts['orderby'],
        'order' => $atts['order'],
    ));
    
    if (empty($tags) || is_wp_error($tags)) {
        return '<p>No tags found.</p>';
    }
    
    $output = '<div class="pcr-tags-list">';
    $output .= '<ul style="list-style: none; padding: 0; columns: ' . $atts['columns'] . '; column-gap: 30px;">';
    
    foreach ($tags as $tag) {
        $tag_url = get_term_link($tag);
        $count_text = $atts['show_count'] ? ' (' . $tag->count . ')' : '';
        
        $output .= '<li style="margin-bottom: 8px; break-inside: avoid;">';
        $output .= '<a href="' . esc_url($tag_url) . '" style="text-decoration: none; font-size: 16px;">' . esc_html($tag->name) . '</a>';
        $output .= $count_text;
        $output .= '</li>';
    }
    
    $output .= '</ul>';
    $output .= '</div>';
    
    return $output;
}
add_shortcode('pcr_tags_list', 'pcr_tags_list_shortcode');

/**
 * Shortcode for alphabetical categories index [pcr_categories_index]
 * Just like the artists index, simple and clean
 */
function pcr_categories_index_shortcode($atts) {
    $atts = shortcode_atts(array(
        'columns' => 1,           // Number of columns (now works!)
        'show_count' => false,
        'hide_empty' => true,
    ), $atts);
    
    // Get all categories (flat, no hierarchy complications)
    $categories = get_terms(array(
        'taxonomy' => 'product_cat',
        'hide_empty' => $atts['hide_empty'],
        'orderby' => 'name',
        'order' => 'ASC',
    ));
    
    if (empty($categories) || is_wp_error($categories)) {
        return '<p>No categories found.</p>';
    }
    
    // Group categories by first letter
    $grouped_categories = array();
    foreach ($categories as $category) {
        $first_letter = strtoupper(substr($category->name, 0, 1));
        if (!isset($grouped_categories[$first_letter])) {
            $grouped_categories[$first_letter] = array();
        }
        $grouped_categories[$first_letter][] = $category;
    }
    
    ksort($grouped_categories);
    
    $output = '<div class="pcr-categories-index">';
    
    // Create alphabet navigation
    $output .= '<div class="alphabet-nav" style="margin-bottom: 30px; text-align: center;">';
    foreach ($grouped_categories as $letter => $cats_in_letter) {
        $output .= '<a href="#cat-letter-' . $letter . '" style="margin: 0 5px; padding: 5px 10px; background: #f0f0f0; text-decoration: none; border-radius: 3px;">' . $letter . '</a>';
    }
    $output .= '</div>';
    
    // Display categories grouped by letter
    foreach ($grouped_categories as $letter => $cats_in_letter) {
        $output .= '<div id="cat-letter-' . $letter . '" class="letter-group" style="margin-bottom: 30px;">';
        $output .= '<h3 style="font-size: 24px; border-bottom: 2px solid #ddd; padding-bottom: 10px;">' . $letter . '</h3>';
        $output .= '<div style="columns: ' . $atts['columns'] . '; column-gap: 30px;">';
        
        foreach ($cats_in_letter as $category) {
            $category_url = get_term_link($category);
            $count_text = $atts['show_count'] ? ' (' . $category->count . ')' : '';
            
            $output .= '<div style="margin-bottom: 8px; break-inside: avoid;">';
            $output .= '<a href="' . esc_url($category_url) . '" style="text-decoration: none;">' . esc_html($category->name) . '</a>';
            $output .= $count_text;
            $output .= '</div>';
        }
        
        $output .= '</div>';
        $output .= '</div>';
    }
    
    $output .= '</div>';
    
    return $output;
}
add_shortcode('pcr_categories_index', 'pcr_categories_index_shortcode');

/**
 * Shortcode for alphabetical tags index [pcr_tags_index]
 * Just like the artists index, simple and clean
 */
function pcr_tags_index_shortcode($atts) {
    $atts = shortcode_atts(array(
        'columns' => 4,           // Number of columns (now works!)
        'show_count' => false,
        'hide_empty' => true,
    ), $atts);
    
    // Get all tags
    $tags = get_terms(array(
        'taxonomy' => 'product_tag',
        'hide_empty' => $atts['hide_empty'],
        'orderby' => 'name',
        'order' => 'ASC',
    ));
    
    if (empty($tags) || is_wp_error($tags)) {
        return '<p>No tags found.</p>';
    }
    
    // Group tags by first letter
    $grouped_tags = array();
    foreach ($tags as $tag) {
        $first_letter = strtoupper(substr($tag->name, 0, 1));
        if (!isset($grouped_tags[$first_letter])) {
            $grouped_tags[$first_letter] = array();
        }
        $grouped_tags[$first_letter][] = $tag;
    }
    
    ksort($grouped_tags);
    
    $output = '<div class="pcr-tags-index">';
    
    // Create alphabet navigation
    $output .= '<div class="alphabet-nav" style="margin-bottom: 30px; text-align: center;">';
    foreach ($grouped_tags as $letter => $tags_in_letter) {
        $output .= '<a href="#tag-letter-' . $letter . '" style="margin: 0 5px; padding: 5px 10px; background: #f0f0f0; text-decoration: none; border-radius: 3px;">' . $letter . '</a>';
    }
    $output .= '</div>';
    
    // Display tags grouped by letter
    foreach ($grouped_tags as $letter => $tags_in_letter) {
        $output .= '<div id="tag-letter-' . $letter . '" class="letter-group" style="margin-bottom: 30px;">';
        $output .= '<h3 style="font-size: 24px; border-bottom: 2px solid #ddd; padding-bottom: 10px;">' . $letter . '</h3>';
        $output .= '<div style="columns: ' . $atts['columns'] . '; column-gap: 30px;">';
        
        foreach ($tags_in_letter as $tag) {
            $tag_url = get_term_link($tag);
            $count_text = $atts['show_count'] ? ' (' . $tag->count . ')' : '';
            
            $output .= '<div style="margin-bottom: 8px; break-inside: avoid;">';
            $output .= '<a href="' . esc_url($tag_url) . '" style="text-decoration: none;">' . esc_html($tag->name) . '</a>';
            $output .= $count_text;
            $output .= '</div>';
        }
        
        $output .= '</div>';
        $output .= '</div>';
    }
    
    $output .= '</div>';
    
    return $output;
}
add_shortcode('pcr_tags_index', 'pcr_tags_index_shortcode');

// --------------------------------------------------------------
// MORE STUFF 
