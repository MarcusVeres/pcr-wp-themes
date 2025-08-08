<?php
/**
 * Plugin Name: PCR Discogs API
 * Plugin URI: https://pcr.sarazstudio.com
 * Description: Discogs API integration for Perfect Circle Records vinyl store
 * Version: 1.0.4
 * Author: Marcus and Claude
 * Author URI: https://pcr.sarazstudio.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pcr-discogs-api
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PCR_DISCOGS_API_VERSION', '1.0.4');
define('PCR_DISCOGS_API_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PCR_DISCOGS_API_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PCR_DISCOGS_API_PLUGIN_FILE', __FILE__);

/**
 * Main Plugin Class
 */
class PCR_Discogs_API {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('pcr-discogs-api', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Initialize admin functionality
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
            add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
            add_action('wp_ajax_pcr_download_discogs_images', array($this, 'ajax_download_discogs_images'));
        }
        
        // Initialize frontend functionality
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        add_option('pcr_discogs_api_version', PCR_DISCOGS_API_VERSION);
        add_option('pcr_discogs_api_token', '');
        add_option('pcr_discogs_auto_sync', '0');
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Admin initialization
     */
    public function admin_init() {
        register_setting('pcr_discogs_api_settings', 'pcr_discogs_api_token');
        register_setting('pcr_discogs_api_settings', 'pcr_discogs_auto_sync');
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('PCR Discogs API', 'pcr-discogs-api'),
            __('Discogs API', 'pcr-discogs-api'),
            'manage_options',
            'pcr-discogs-api',
            array($this, 'admin_page'),
            'dashicons-album',
            30
        );
        
        add_submenu_page(
            'pcr-discogs-api',
            __('Settings', 'pcr-discogs-api'),
            __('Settings', 'pcr-discogs-api'),
            'manage_options',
            'pcr-discogs-api-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Admin page callback
     */
    public function admin_page() {
        $api_token = get_option('pcr_discogs_api_token', '');
        $token_status = !empty($api_token) ? 'configured' : 'not-configured';
        ?>
        <div class="wrap pcr-discogs-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="notice notice-success">
                <p>
                    <strong><?php _e('🎵 PCR Discogs API Plugin is Active!', 'pcr-discogs-api'); ?></strong>
                </p>
                <p><?php _e('Version:', 'pcr-discogs-api'); ?> <?php echo PCR_DISCOGS_API_VERSION; ?></p>
            </div>
            
            <div class="card">
                <h2><?php _e('Plugin Status', 'pcr-discogs-api'); ?></h2>
                <div class="pcr-api-status">
                    <span class="pcr-status-indicator <?php echo $token_status === 'configured' ? 'active' : 'inactive'; ?>"></span>
                    <strong><?php _e('API Status:', 'pcr-discogs-api'); ?></strong> 
                    <?php echo $token_status === 'configured' ? __('Token Configured', 'pcr-discogs-api') : __('Token Not Configured', 'pcr-discogs-api'); ?>
                </div>
                
                <p><?php _e('This plugin allows you to download album images from Discogs for your vinyl records store.', 'pcr-discogs-api'); ?></p>
                
                <h3><?php _e('How to use:', 'pcr-discogs-api'); ?></h3>
                <ol>
                    <li><?php _e('Configure your Discogs API token in Settings', 'pcr-discogs-api'); ?></li>
                    <li><?php _e('Edit a WooCommerce product', 'pcr-discogs-api'); ?></li>
                    <li><?php _e('Enter the Discogs Release ID in the custom field', 'pcr-discogs-api'); ?></li>
                    <li><?php _e('Click "Download Images from Discogs" button', 'pcr-discogs-api'); ?></li>
                </ol>
            </div>
        </div>
        <?php
    }
    
    /**
     * Settings page callback
     */
    public function settings_page() {
        $api_token = get_option('pcr_discogs_api_token', '');
        $auto_sync = get_option('pcr_discogs_auto_sync', '0');
        
        if (isset($_POST['submit'])) {
            update_option('pcr_discogs_api_token', sanitize_text_field($_POST['pcr_discogs_api_token']));
            update_option('pcr_discogs_auto_sync', isset($_POST['pcr_discogs_auto_sync']) ? '1' : '0');
            echo '<div class="notice notice-success"><p>' . __('Settings saved!', 'pcr-discogs-api') . '</p></div>';
            $api_token = get_option('pcr_discogs_api_token', '');
            $auto_sync = get_option('pcr_discogs_auto_sync', '0');
        }
        ?>
        <div class="wrap pcr-discogs-admin">
            <h1><?php _e('PCR Discogs API Settings', 'pcr-discogs-api'); ?></h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('pcr_discogs_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Discogs API Token', 'pcr-discogs-api'); ?></th>
                        <td>
                            <input type="text" name="pcr_discogs_api_token" value="<?php echo esc_attr($api_token); ?>" class="regular-text pcr-api-token-field" />
                            <p class="description">
                                <?php _e('Enter your Discogs Personal Access Token. Get one at:', 'pcr-discogs-api'); ?> 
                                <a href="https://www.discogs.com/settings/developers" target="_blank">https://www.discogs.com/settings/developers</a>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Auto Sync', 'pcr-discogs-api'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="pcr_discogs_auto_sync" value="1" <?php checked($auto_sync, '1'); ?> />
                                <?php _e('Enable automatic product synchronization (future feature)', 'pcr-discogs-api'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <?php if (!empty($api_token)): ?>
            <div class="card">
                <h3><?php _e('Test API Connection', 'pcr-discogs-api'); ?></h3>
                <p><?php _e('Test your API token with a sample request:', 'pcr-discogs-api'); ?></p>
                <button type="button" class="button pcr-test-api"><?php _e('Test Connection', 'pcr-discogs-api'); ?></button>
                <div class="pcr-test-result"></div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Add meta boxes to product edit screen
     */
    public function add_meta_boxes() {
        add_meta_box(
            'pcr-discogs-images',
            __('Discogs Images', 'pcr-discogs-api'),
            array($this, 'discogs_images_meta_box'),
            'product',
            'side',
            'default'
        );
    }
    
    /**
     * Discogs images meta box callback
     */
    public function discogs_images_meta_box($post) {
        $discogs_release_id = get_field('discogs_release_id', $post->ID);
        $api_token = get_option('pcr_discogs_api_token', '');
        
        wp_nonce_field('pcr_download_images', 'pcr_download_images_nonce');
        ?>
        <div class="pcr-discogs-meta-box">
            <?php if (empty($api_token)): ?>
                <p class="pcr-notice error">
                    <?php _e('⚠️ No API token configured.', 'pcr-discogs-api'); ?>
                    <a href="<?php echo admin_url('admin.php?page=pcr-discogs-api-settings'); ?>">
                        <?php _e('Configure now', 'pcr-discogs-api'); ?>
                    </a>
                </p>
            <?php else: ?>
                <?php if (empty($discogs_release_id)): ?>
                    <p class="pcr-notice warning">
                        <?php _e('⚠️ No Discogs ID found. Please enter a Discogs Release ID in the custom field below.', 'pcr-discogs-api'); ?>
                    </p>
                <?php else: ?>
                    <p><strong><?php _e('Discogs Release ID:', 'pcr-discogs-api'); ?></strong> <?php echo esc_html($discogs_release_id); ?></p>
                    <button type="button" class="button button-primary pcr-download-images" data-product-id="<?php echo $post->ID; ?>">
                        <?php _e('Download Images from Discogs', 'pcr-discogs-api'); ?>
                    </button>
                <?php endif; ?>
                
                <div class="pcr-download-status" style="margin-top: 15px;"></div>
            <?php endif; ?>
        </div>
        
        <style>
        .pcr-discogs-meta-box .pcr-notice {
            padding: 8px 12px;
            margin: 10px 0;
            border-radius: 3px;
        }
        .pcr-discogs-meta-box .pcr-notice.error {
            background: #ffeaa7;
            border-left: 4px solid #e17055;
        }
        .pcr-discogs-meta-box .pcr-notice.warning {
            background: #ffeaa7;
            border-left: 4px solid #fdcb6e;
        }
        .pcr-discogs-meta-box .pcr-notice.success {
            background: #d4edda;
            border-left: 4px solid #00b894;
        }
        </style>
        <?php
    }
    
    /**
     * AJAX handler for downloading Discogs images
     */
    public function ajax_download_discogs_images() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'pcr_download_images')) {
            wp_die(__('Security check failed', 'pcr-discogs-api'));
        }
        
        // Check user permissions
        if (!current_user_can('edit_products')) {
            wp_die(__('Insufficient permissions', 'pcr-discogs-api'));
        }
        
        $product_id = intval($_POST['product_id']);
        $discogs_release_id = get_field('discogs_release_id', $product_id);
        $api_token = get_option('pcr_discogs_api_token', '');
        
        if (empty($discogs_release_id)) {
            wp_send_json_error(__('No Discogs ID found for this product', 'pcr-discogs-api'));
        }
        
        if (empty($api_token)) {
            wp_send_json_error(__('No API token configured', 'pcr-discogs-api'));
        }
        
        // Call Discogs API
        $discogs_data = $this->get_discogs_release($discogs_release_id, $api_token);
        
        if (is_wp_error($discogs_data)) {
            wp_send_json_error($discogs_data->get_error_message());
        }
        
        // Download and attach images
        $result = $this->download_and_attach_images($product_id, $discogs_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Get release data from Discogs API
     */
    private function get_discogs_release($release_id, $api_token) {
        $url = "https://api.discogs.com/releases/{$release_id}";
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Discogs token=' . $api_token,
                'User-Agent' => 'PCRDiscogsAPI/1.0 +https://pcr.sarazstudio.com'
            ),
            'timeout' => 30
        );
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            return new WP_Error('api_error', __('Failed to connect to Discogs API', 'pcr-discogs-api'));
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            if ($status_code === 404) {
                return new WP_Error('release_not_found', __('Release not found on Discogs', 'pcr-discogs-api'));
            } elseif ($status_code === 401) {
                return new WP_Error('auth_error', __('Invalid API token', 'pcr-discogs-api'));
            } else {
                return new WP_Error('api_error', sprintf(__('API error: %d', 'pcr-discogs-api'), $status_code));
            }
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', __('Invalid JSON response from API', 'pcr-discogs-api'));
        }
        
        return $data;
    }
    
    /**
     * Download and attach images to product
     */
    private function download_and_attach_images($product_id, $discogs_data) {
        if (empty($discogs_data['images'])) {
            return new WP_Error('no_images', __('No images found for this release', 'pcr-discogs-api'));
        }
        
        $downloaded_images = array();
        $featured_image_set = false;
        
        foreach ($discogs_data['images'] as $index => $image) {
            if (empty($image['uri'])) {
                continue;
            }
            
            // Download the image
            $attachment_id = $this->download_image_to_media_library($image['uri'], $product_id, $index);
            
            if (is_wp_error($attachment_id)) {
                continue; // Skip this image and try the next one
            }
            
            $downloaded_images[] = $attachment_id;
            
            // Set first image as featured image
            if (!$featured_image_set) {
                set_post_thumbnail($product_id, $attachment_id);
                $featured_image_set = true;
            }
        }
        
        if (empty($downloaded_images)) {
            return new WP_Error('download_failed', __('Failed to download any images', 'pcr-discogs-api'));
        }
        
        // Add images to product gallery
        $this->add_images_to_product_gallery($product_id, $downloaded_images);
        
        return array(
            'message' => sprintf(__('Successfully downloaded %d images', 'pcr-discogs-api'), count($downloaded_images)),
            'images_downloaded' => count($downloaded_images),
            'featured_image_set' => $featured_image_set,
            'release_title' => $discogs_data['title'] ?? __('Unknown Release', 'pcr-discogs-api')
        );
    }
    
    /**
     * Download image to WordPress media library
     */
    private function download_image_to_media_library($image_url, $product_id, $index) {
        if (!function_exists('media_sideload_image')) {
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        
        // Create a descriptive filename
        $product_title = get_the_title($product_id);
        $filename = sanitize_file_name($product_title . '-discogs-' . ($index + 1));
        
        // Download the image
        $attachment_id = media_sideload_image($image_url, $product_id, $filename, 'id');
        
        if (is_wp_error($attachment_id)) {
            return $attachment_id;
        }
        
        // Set alt text
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $product_title . ' - Image ' . ($index + 1));
        
        return $attachment_id;
    }
    
    /**
     * Add images to product gallery
     */
    private function add_images_to_product_gallery($product_id, $new_image_ids) {
        // Get existing gallery images
        $existing_gallery = get_post_meta($product_id, '_product_image_gallery', true);
        $existing_ids = !empty($existing_gallery) ? explode(',', $existing_gallery) : array();
        
        // Merge with new images (avoid duplicates)
        $all_ids = array_unique(array_merge($existing_ids, $new_image_ids));
        
        // Update gallery
        update_post_meta($product_id, '_product_image_gallery', implode(',', $all_ids));
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        global $post_type;
        
        // Load on product edit screens and our plugin pages
        if (($post_type === 'product' && in_array($hook, array('post.php', 'post-new.php'))) || 
            strpos($hook, 'pcr-discogs-api') !== false) {
            
            wp_enqueue_style(
                'pcr-discogs-api-admin',
                PCR_DISCOGS_API_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                PCR_DISCOGS_API_VERSION
            );
            
            wp_enqueue_script(
                'pcr-discogs-api-admin',
                PCR_DISCOGS_API_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                PCR_DISCOGS_API_VERSION,
                true
            );
            
            // Localize script for AJAX
            wp_localize_script('pcr-discogs-api-admin', 'pcrDiscogsAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pcr_download_images'),
                'strings' => array(
                    'downloading' => __('Downloading images...', 'pcr-discogs-api'),
                    'success' => __('Images downloaded successfully!', 'pcr-discogs-api'),
                    'error' => __('Error downloading images:', 'pcr-discogs-api'),
                    'testing' => __('Testing connection...', 'pcr-discogs-api'),
                    'test_success' => __('✅ Connection successful!', 'pcr-discogs-api'),
                    'test_error' => __('❌ Connection failed:', 'pcr-discogs-api')
                )
            ));
        }
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function frontend_enqueue_scripts() {
        wp_enqueue_style(
            'pcr-discogs-api-frontend',
            PCR_DISCOGS_API_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            PCR_DISCOGS_API_VERSION
        );
    }
}

// Initialize the plugin
new PCR_Discogs_API();
