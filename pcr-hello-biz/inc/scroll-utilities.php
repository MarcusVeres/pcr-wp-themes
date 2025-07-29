<?php
/**
 * Additional scroll offset utilities
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode to create an anchor point with proper offset
 * Usage: [pcr_anchor id="my-section" class="my-class"]
 */
function pcr_anchor_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => '',
        'class' => '',
        'style' => '', // Additional inline styles
    ), $atts);

    if (empty($atts['id'])) {
        return '<!-- PCR Anchor: ID is required -->';
    }

    $classes = array('pcr-anchor-point');
    if (!empty($atts['class'])) {
        $classes[] = $atts['class'];
    }

    $style_attr = '';
    if (!empty($atts['style'])) {
        $style_attr = ' style="' . esc_attr($atts['style']) . '"';
    }

    return '<div id="' . esc_attr($atts['id']) . '" class="' . esc_attr(implode(' ', $classes)) . '"' . $style_attr . '></div>';
}
add_shortcode('pcr_anchor', 'pcr_anchor_shortcode');

/**
 * Function to automatically add anchor offsets to headings
 * Call this function to automatically add the offset class to all headings with IDs
 */
function pcr_add_heading_anchor_offsets($content) {
    if (!function_exists('pcr_is_scroll_offset_enabled') || !pcr_is_scroll_offset_enabled()) {
        return $content;
    }

    // Add the anchor offset class to headings that have IDs
    $content = preg_replace('/(<h[1-6][^>]*id=[^>]*)(class="[^"]*")/', '$1$2', $content);
    $content = preg_replace('/(<h[1-6][^>]*id=[^>]*class="[^"]*)"/', '$1 pcr-anchor-offset"', $content);
    $content = preg_replace('/(<h[1-6][^>]*id=[^>]*)(?!.*class=)([^>]*>)/', '$1 class="pcr-anchor-offset"$2', $content);

    return $content;
}
// Uncomment the next line if you want automatic heading offset (be careful with this on complex sites)
// add_filter('the_content', 'pcr_add_heading_anchor_offsets');

/**
 * Helper function to get scroll offset for use in other code
 */
function pcr_get_current_scroll_offset() {
    if (!function_exists('pcr_is_scroll_offset_enabled') || !pcr_is_scroll_offset_enabled()) {
        return 0;
    }

    // This is a server-side approximation - for exact offset, use JavaScript
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $is_mobile = wp_is_mobile() || (strpos($user_agent, 'Mobile') !== false);
    
    if ($is_mobile) {
        return function_exists('pcr_get_scroll_offset_mobile') ? pcr_get_scroll_offset_mobile() : 80;
    } else {
        return function_exists('pcr_get_scroll_offset_desktop') ? pcr_get_scroll_offset_desktop() : 100;
    }
}

/**
 * Add Elementor compatibility
 * This ensures Elementor anchors work with the scroll offset
 */
function pcr_elementor_anchor_compatibility() {
    if (!function_exists('pcr_is_scroll_offset_enabled') || !pcr_is_scroll_offset_enabled()) {
        return;
    }

    // Add specific styles for Elementor anchor links
    echo '<style>
        /* Elementor anchor compatibility */
        .elementor-menu-anchor {
            margin-top: calc(var(--pcr-scroll-offset-desktop) * -1);
            padding-top: var(--pcr-scroll-offset-desktop);
        }
        
        @media (max-width: ' . (function_exists('pcr_get_scroll_offset_breakpoint') ? pcr_get_scroll_offset_breakpoint() : 768) . 'px) {
            .elementor-menu-anchor {
                margin-top: calc(var(--pcr-scroll-offset-mobile) * -1);
                padding-top: var(--pcr-scroll-offset-mobile);
            }
        }
    </style>';
}
add_action('wp_head', 'pcr_elementor_anchor_compatibility', 20);

/**
 * Add admin notice to point users to the new settings page
 */
function pcr_scroll_offset_admin_notice() {
    // Only show on dashboard and a few key admin pages
    $screen = get_current_screen();
    if (!in_array($screen->id, ['dashboard', 'themes', 'plugins', 'edit-page', 'edit-post'])) {
        return;
    }

    // Don't show if already on our settings page
    if ($screen->id === 'toplevel_page_pcr-theme-settings') {
        return;
    }

    // Only show if scroll offset is enabled (so they know it's working)
    if (!function_exists('pcr_is_scroll_offset_enabled') || !pcr_is_scroll_offset_enabled()) {
        return;
    }

    // Check if user has dismissed this notice
    if (get_user_meta(get_current_user_id(), 'pcr_scroll_notice_dismissed', true)) {
        return;
    }

    echo '<div class="notice notice-success is-dismissible" data-notice="pcr-scroll-offset">
        <p><strong>🎯 Scroll Offset is Active!</strong> Your fixed header no longer covers content when users click anchor links. <a href="' . admin_url('admin.php?page=pcr-theme-settings') . '">Adjust settings</a> or <a href="#" onclick="this.closest(\'.notice\').style.display=\'none\'; fetch(ajaxurl, {method:\'POST\', headers:{\'Content-Type\':\'application/x-www-form-urlencoded\'}, body:\'action=pcr_dismiss_notice&nonce=' . wp_create_nonce('pcr_dismiss_notice') . '\'})">dismiss this notice</a>.</p>
    </div>';
}
add_action('admin_notices', 'pcr_scroll_offset_admin_notice');

/**
 * Handle dismissing the admin notice
 */
function pcr_dismiss_notice() {
    if (!wp_verify_nonce($_POST['nonce'], 'pcr_dismiss_notice')) {
        wp_die('Security check failed');
    }
    
    update_user_meta(get_current_user_id(), 'pcr_scroll_notice_dismissed', true);
    wp_die(); // This is required to terminate immediately and return a proper response
}
add_action('wp_ajax_pcr_dismiss_notice', 'pcr_dismiss_notice');

/**
 * Ajax handler to test scroll offset (for debugging)
 */
function pcr_test_scroll_offset() {
    // Create nonce for security (simple approach)
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $desktop_offset = pcr_get_scroll_offset_desktop();
    $mobile_offset = pcr_get_scroll_offset_mobile();
    $breakpoint = pcr_get_scroll_offset_breakpoint();
    $enabled = pcr_is_scroll_offset_enabled();
    
    wp_send_json_success(array(
        'desktop_offset' => $desktop_offset,
        'mobile_offset' => $mobile_offset,
        'breakpoint' => $breakpoint,
        'enabled' => $enabled,
        'message' => 'Scroll offset settings retrieved successfully'
    ));
}
add_action('wp_ajax_pcr_test_scroll_offset', 'pcr_test_scroll_offset');

/**
 * Add quick settings link to admin bar
 */
function pcr_add_admin_bar_link($wp_admin_bar) {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Only show if scroll offset is enabled
    if (!function_exists('pcr_is_scroll_offset_enabled') || !pcr_is_scroll_offset_enabled()) {
        return;
    }

    $args = array(
        'id'    => 'pcr-scroll-settings',
        'title' => '🎯 Scroll Settings',
        'href'  => admin_url('admin.php?page=pcr-theme-settings'),
        'meta'  => array(
            'title' => 'Configure scroll offset settings'
        ),
    );
    $wp_admin_bar->add_node($args);
}
add_action('admin_bar_menu', 'pcr_add_admin_bar_link', 999);

/**
 * Add some helpful CSS for anchor debugging (only for admins)
 */
function pcr_debug_anchor_styles() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Only add debug styles if enabled and user is admin
    if (!function_exists('pcr_is_scroll_offset_enabled') || !pcr_is_scroll_offset_enabled()) {
        return;
    }

    // Add debug parameter check
    if (!isset($_GET['pcr_debug'])) {
        return;
    }

    echo '<style>
        /* PCR Debug Mode: Highlight anchor targets */
        [id] {
            position: relative;
        }
        [id]::before {
            content: "🎯 " attr(id);
            position: absolute;
            top: -25px;
            left: 0;
            background: #ff6b6b;
            color: white;
            padding: 2px 8px;
            font-size: 11px;
            border-radius: 3px;
            z-index: 9999;
            font-family: monospace;
            opacity: 0.8;
        }
        
        /* Show scroll offset visualization */
        body::after {
            content: "Desktop: ' . pcr_get_scroll_offset_desktop() . 'px | Mobile: ' . pcr_get_scroll_offset_mobile() . 'px | Breakpoint: ' . pcr_get_scroll_offset_breakpoint() . 'px";
            position: fixed;
            top: 0;
            right: 0;
            background: #333;
            color: white;
            padding: 10px;
            font-size: 12px;
            z-index: 99999;
            font-family: monospace;
        }
    </style>';
}
add_action('wp_head', 'pcr_debug_anchor_styles');