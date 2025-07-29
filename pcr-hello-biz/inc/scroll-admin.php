<?php
/**
 * Admin customizations - Theme Settings (Vanilla WordPress)
 * Replace the entire content of scroll-admin.php with this code
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add admin menu page for theme settings
 */
function pcr_add_theme_settings_menu() {
    add_menu_page(
        'Theme Settings',           // Page title
        'Theme Settings',           // Menu title
        'manage_options',           // Capability
        'pcr-theme-settings',       // Menu slug
        'pcr_theme_settings_page',  // Callback function
        'dashicons-admin-generic',  // Icon
        30                          // Position
    );
}
add_action('admin_menu', 'pcr_add_theme_settings_menu');

/**
 * Register settings for scroll offset
 */
function pcr_register_scroll_settings() {
    // Register settings group
    register_setting('pcr_scroll_settings', 'pcr_scroll_offset_desktop', array(
        'type' => 'integer',
        'default' => 100,
        'sanitize_callback' => 'pcr_sanitize_offset_value'
    ));
    
    register_setting('pcr_scroll_settings', 'pcr_scroll_offset_mobile', array(
        'type' => 'integer',
        'default' => 80,
        'sanitize_callback' => 'pcr_sanitize_offset_value'
    ));
    
    register_setting('pcr_scroll_settings', 'pcr_scroll_offset_breakpoint', array(
        'type' => 'integer',
        'default' => 768,
        'sanitize_callback' => 'pcr_sanitize_breakpoint_value'
    ));
    
    register_setting('pcr_scroll_settings', 'pcr_scroll_offset_enable', array(
        'type' => 'boolean',
        'default' => true
    ));

    // Add settings section
    add_settings_section(
        'pcr_scroll_section',
        'Scroll Offset Settings',
        'pcr_scroll_section_callback',
        'pcr-theme-settings'
    );

    // Add settings fields
    add_settings_field(
        'pcr_scroll_offset_enable',
        'Enable Scroll Offset',
        'pcr_scroll_enable_field_callback',
        'pcr-theme-settings',
        'pcr_scroll_section'
    );

    add_settings_field(
        'pcr_scroll_offset_desktop',
        'Desktop Scroll Offset',
        'pcr_scroll_desktop_field_callback',
        'pcr-theme-settings',
        'pcr_scroll_section'
    );

    add_settings_field(
        'pcr_scroll_offset_mobile',
        'Mobile Scroll Offset',
        'pcr_scroll_mobile_field_callback',
        'pcr-theme-settings',
        'pcr_scroll_section'
    );

    add_settings_field(
        'pcr_scroll_offset_breakpoint',
        'Mobile Breakpoint',
        'pcr_scroll_breakpoint_field_callback',
        'pcr-theme-settings',
        'pcr_scroll_section'
    );
}
add_action('admin_init', 'pcr_register_scroll_settings');

/**
 * Sanitize offset values (0-500px)
 */
function pcr_sanitize_offset_value($value) {
    $value = intval($value);
    return max(0, min(500, $value));
}

/**
 * Sanitize breakpoint values (320-1200px)
 */
function pcr_sanitize_breakpoint_value($value) {
    $value = intval($value);
    return max(320, min(1200, $value));
}

/**
 * Settings section callback
 */
function pcr_scroll_section_callback() {
    echo '<p>Configure scroll offset settings to prevent your fixed header from covering content when users click on anchor links.</p>';
}

/**
 * Enable field callback
 */
function pcr_scroll_enable_field_callback() {
    $value = get_option('pcr_scroll_offset_enable', true);
    echo '<label>';
    echo '<input type="checkbox" name="pcr_scroll_offset_enable" value="1" ' . checked(1, $value, false) . ' />';
    echo ' Enable scroll offset functionality';
    echo '</label>';
    echo '<p class="description">Enable or disable the scroll offset functionality globally.</p>';
}

/**
 * Desktop offset field callback
 */
function pcr_scroll_desktop_field_callback() {
    $value = get_option('pcr_scroll_offset_desktop', 100);
    echo '<input type="number" name="pcr_scroll_offset_desktop" value="' . esc_attr($value) . '" min="0" max="500" step="1" class="small-text" />';
    echo '<span class="description"> px</span>';
    echo '<p class="description">Height in pixels to offset when scrolling to anchors on desktop (default: 100px).</p>';
}

/**
 * Mobile offset field callback
 */
function pcr_scroll_mobile_field_callback() {
    $value = get_option('pcr_scroll_offset_mobile', 80);
    echo '<input type="number" name="pcr_scroll_offset_mobile" value="' . esc_attr($value) . '" min="0" max="500" step="1" class="small-text" />';
    echo '<span class="description"> px</span>';
    echo '<p class="description">Height in pixels to offset when scrolling to anchors on mobile (default: 80px).</p>';
}

/**
 * Breakpoint field callback
 */
function pcr_scroll_breakpoint_field_callback() {
    $value = get_option('pcr_scroll_offset_breakpoint', 768);
    echo '<input type="number" name="pcr_scroll_offset_breakpoint" value="' . esc_attr($value) . '" min="320" max="1200" step="1" class="small-text" />';
    echo '<span class="description"> px</span>';
    echo '<p class="description">Screen width below which mobile offset is used (default: 768px).</p>';
}

/**
 * Theme settings page content
 */
function pcr_theme_settings_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // Show success message if settings were saved
    if (isset($_GET['settings-updated'])) {
        add_settings_error('pcr_messages', 'pcr_message', 'Settings Saved', 'updated');
    }

    // Show error/update messages
    settings_errors('pcr_messages');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="notice notice-info">
            <h3>🎯 Scroll Offset Feature</h3>
            <p><strong>What this does:</strong> Prevents your fixed header from covering content when users click on anchor links (like menu items that jump to page sections).</p>
            
            <h4>Features:</h4>
            <ul style="margin-left: 20px;">
                <li>• <strong>Responsive:</strong> Different settings for desktop and mobile</li>
                <li>• <strong>Automatic:</strong> Works with all existing anchor links</li>
                <li>• <strong>Compatible:</strong> Works with Elementor and other page builders</li>
                <li>• <strong>Modern:</strong> Uses CSS scroll-padding-top for smooth performance</li>
                <li>• <strong>Fallback:</strong> JavaScript backup for older browsers</li>
            </ul>

            <h4>How to test:</h4>
            <ol style="margin-left: 20px;">
                <li>Save your settings below</li>
                <li>Create anchor links on your pages (like <code>&lt;a href="#section1"&gt;Go to Section 1&lt;/a&gt;</code>)</li>
                <li>Add corresponding anchor targets (like <code>&lt;div id="section1"&gt;</code>)</li>
                <li>Click the links to see the offset in action!</li>
            </ol>
        </div>

        <form action="options.php" method="post">
            <?php
            settings_fields('pcr_scroll_settings');
            do_settings_sections('pcr-theme-settings');
            submit_button('Save Settings');
            ?>
        </form>

        <div class="card" style="margin-top: 20px;">
            <h3>🛠️ Advanced Usage</h3>
            <p>Need more control? Use these shortcodes and classes:</p>
            
            <h4>Shortcodes:</h4>
            <ul>
                <li><code>[pcr_anchor id="my-section"]</code> - Creates an invisible anchor point</li>
                <li><code>[pcr_artist_link]</code> - Display artist link (for your vinyl store)</li>
                <li><code>[pcr_album_title]</code> - Display album title without artist name</li>
            </ul>

            <h4>CSS Classes:</h4>
            <ul>
                <li><code>.pcr-anchor-offset</code> - Add to any element to give it proper scroll offset</li>
                <li><code>.pcr-scroll-offset-enabled</code> - Added to body when feature is active</li>
            </ul>

            <h4>CSS Variables Available:</h4>
            <ul>
                <li><code>--pcr-scroll-offset-desktop</code> - Current desktop offset value</li>
                <li><code>--pcr-scroll-offset-mobile</code> - Current mobile offset value</li>
                <li><code>--pcr-scroll-offset-breakpoint</code> - Current breakpoint value</li>
            </ul>
        </div>

        <?php if (get_option('pcr_scroll_offset_enable', true)): ?>
        <div class="card" style="margin-top: 20px; border-left: 4px solid #00a0d2;">
            <h3>✅ Current Settings Active</h3>
            <p><strong>Desktop Offset:</strong> <?php echo esc_html(get_option('pcr_scroll_offset_desktop', 100)); ?>px</p>
            <p><strong>Mobile Offset:</strong> <?php echo esc_html(get_option('pcr_scroll_offset_mobile', 80)); ?>px</p>
            <p><strong>Mobile Breakpoint:</strong> <?php echo esc_html(get_option('pcr_scroll_offset_breakpoint', 768)); ?>px</p>
            <p><em>Test by creating anchor links on your pages!</em></p>
        </div>
        <?php else: ?>
        <div class="card" style="margin-top: 20px; border-left: 4px solid #dc3232;">
            <h3>⚠️ Scroll Offset Disabled</h3>
            <p>Enable the feature above to start using scroll offset functionality.</p>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Helper functions to get scroll offset values (replacing ACF functions)
 */
function pcr_get_scroll_offset_desktop() {
    return intval(get_option('pcr_scroll_offset_desktop', 100));
}

function pcr_get_scroll_offset_mobile() {
    return intval(get_option('pcr_scroll_offset_mobile', 80));
}

function pcr_get_scroll_offset_breakpoint() {
    return intval(get_option('pcr_scroll_offset_breakpoint', 768));
}

function pcr_is_scroll_offset_enabled() {
    return (bool) get_option('pcr_scroll_offset_enable', true);
}

/**
 * Add some admin styles for better UX
 */
function pcr_admin_styles($hook) {
    if ($hook !== 'toplevel_page_pcr-theme-settings') {
        return;
    }
    
    echo '<style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            padding: 20px;
            margin-top: 20px;
        }
        .card h3 {
            margin-top: 0;
        }
        .card h4 {
            margin-bottom: 5px;
        }
        .card ul {
            margin-top: 5px;
        }
        .card code {
            background: #f1f1f1;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 13px;
        }
        .notice h3 {
            margin-top: 0;
        }
        .notice h4 {
            margin-bottom: 8px;
            margin-top: 15px;
        }
    </style>';
}
add_action('admin_head', 'pcr_admin_styles');

/**
 * Add settings link to plugins page (if this were a plugin)
 */
function pcr_add_settings_link($links) {
    $settings_link = '<a href="admin.php?page=pcr-theme-settings">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
// Uncomment if you want a quick link from somewhere else
// add_filter('theme_action_links', 'pcr_add_settings_link');