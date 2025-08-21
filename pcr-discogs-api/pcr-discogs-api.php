<?php
/**
 * Plugin Name: PCR Discogs API
 * Plugin URI: https://pcr.sarazstudio.com
 * Description: Discogs API integration for Perfect Circle Records vinyl store
 * Version: 1.0.20
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
define('PCR_DISCOGS_API_VERSION', '1.0.20');
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
        
        // Initialize batch processor AFTER main init
        add_action('init', array($this, 'init_batch_processor'), 20); // Priority 20
        add_action('init', array($this, 'init_image_cleaner'), 22);
    }

    /**
     * Initialize batch processor
     */
    public function init_batch_processor() {
        if (is_admin()) {
            $batch_file = PCR_DISCOGS_API_PLUGIN_DIR . 'includes/class-pcr-batch-processor.php';
            error_log("PCR DEBUG: Looking for batch file at: " . $batch_file);
            error_log("PCR DEBUG: File exists: " . (file_exists($batch_file) ? 'YES' : 'NO'));
            
            if (file_exists($batch_file)) {
                require_once($batch_file);
                $this->batch_processor = new PCR_Discogs_Batch_Processor($this);
            } else {
                error_log("PCR DEBUG: Batch processor file not found!");
            }
        }
    }

    /**
     * Initialize image cleaner 
     */
    public function init_image_cleaner() {
        if( is_admin()) {
            $cleanup_file = PCR_DISCOGS_API_PLUGIN_DIR . 'includes/class-pcr-image-cleanup.php';
            
            if (file_exists($cleanup_file)) {
                require_once($cleanup_file);
                $this->image_cleanup = new PCR_Image_Cleanup($this);
            }
        }
    }

    /**
     * Initialize the plugin (UPDATED - add categories AJAX handler)
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
            
            // Existing AJAX handlers
            add_action('wp_ajax_pcr_download_discogs_images', array($this, 'ajax_download_discogs_images'));
            add_action('wp_ajax_pcr_download_record_data', array($this, 'ajax_download_record_data'));
            
            // NEW: Add categories AJAX handler
            add_action('wp_ajax_pcr_set_categories_from_discogs', array($this, 'ajax_set_categories_from_discogs'));
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
     * Discogs images meta box callback (UPDATED - add third button)
     */
    public function discogs_images_meta_box($post) {
        $discogs_release_id = get_field('discogs_release_id', $post->ID);
        $api_token = get_option('pcr_discogs_api_token', '');
        $genres_text = get_field('genres', $post->ID);
        
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
                    
                    <!-- EXISTING Images Button -->
                    <button type="button" class="button button-primary pcr-download-images" data-product-id="<?php echo $post->ID; ?>">
                        <?php _e('Download Images from Discogs', 'pcr-discogs-api'); ?>
                    </button>
                    <label style="margin-top: 10px; display: block;">
                        <input type="checkbox" id="pcr-force-overwrite" style="margin-right: 5px;" />
                        <?php _e('Force overwrite existing images', 'pcr-discogs-api'); ?>
                    </label>
                    
                    <!-- EXISTING Record Data Button -->
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                        <button type="button" class="button button-secondary pcr-download-record-data" data-product-id="<?php echo $post->ID; ?>">
                            <?php _e('Download Record Data', 'pcr-discogs-api'); ?>
                        </button>
                        <p class="description"><?php _e('Downloads year, country, and genres from Discogs', 'pcr-discogs-api'); ?></p>
                    </div>
                    
                    <!-- NEW: Categories Button -->
                    <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                        <?php if (!empty($genres_text)): ?>
                            <p><strong><?php _e('Current Genres:', 'pcr-discogs-api'); ?></strong> <?php echo esc_html($genres_text); ?></p>
                            <button type="button" class="button button-secondary pcr-set-categories" data-product-id="<?php echo $post->ID; ?>">
                                <?php _e('Set Categories from Discogs Data', 'pcr-discogs-api'); ?>
                            </button>
                            <p class="description"><?php _e('Creates categories and assigns them to this product', 'pcr-discogs-api'); ?></p>
                        <?php else: ?>
                            <p class="pcr-notice warning">
                                <?php _e('⚠️ No genres data found. Please download record data first.', 'pcr-discogs-api'); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                <?php endif; ?>
                
                <div class="pcr-download-status" style="margin-top: 15px;"></div>
                <div class="pcr-record-data-status" style="margin-top: 15px;"></div>
                <div class="pcr-categories-status" style="margin-top: 15px;"></div>
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
        
        // ------------------------------------------------------
        // OVERWRITE IF SAME IMAGES 

        // OLD CODE (find and replace this):
        // $result = $this->download_and_attach_images($product_id, $discogs_data);

        // NEW CODE (replace with this):
        $result = $this->smart_download_and_attach_images($product_id, $discogs_data, false);
        
        // ------------------------------------------------------

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Get release data from Discogs API
     */
    public function get_discogs_release($release_id, $api_token) {
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
     * Updated admin_enqueue_scripts method
     */
    public function admin_enqueue_scripts($hook) {
        global $post_type;
        
        // Get current page
        $current_page = $_GET['page'] ?? '';
        
        // TEMPORARY DEBUG: Log when we're on any of our pages
        if (strpos($hook, 'discogs') !== false || $current_page === 'pcr-discogs-batch') {
            error_log("PCR DEBUG: Hook name is: " . $hook);
            error_log("PCR DEBUG: Current page parameter: " . $current_page);
            error_log("PCR DEBUG: Are we on batch page? " . ($current_page === 'pcr-discogs-batch' ? 'YES' : 'NO'));
        }

        // Load on product edit screens and our plugin pages
        if (($post_type === 'product' && in_array($hook, array('post.php', 'post-new.php'))) || 
            strpos($hook, 'discogs-api') !== false ||  // Changed from 'pcr-discogs-api' to 'discogs-api'
            $current_page === 'pcr-discogs-batch') {
            
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
            
            // NEW: Enqueue batch processing JS on batch page
            if (strpos($hook, 'pcr-discogs-batch') !== false) {
                wp_enqueue_script(
                    'pcr-discogs-api-batch',
                    PCR_DISCOGS_API_PLUGIN_URL . 'assets/js/batch.js',
                    array('jquery'),
                    PCR_DISCOGS_API_VERSION,
                    true
                );
                
                // Localize batch script
                wp_localize_script('pcr-discogs-api-batch', 'pcrBatchAjax', array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('pcr_batch_processing'),
                    'strings' => array(
                        'scanning' => __('Scanning...', 'pcr-discogs-api'),
                        'processing' => __('Processing...', 'pcr-discogs-api'),
                        'complete' => __('Complete!', 'pcr-discogs-api')
                    )
                ));
            }
            
            // Existing localization for individual processing
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

    // --------------------------------------------------------------------------
    // 2025-08-08
    
    /**
     * Smart image download with existing image handling
     * Add this method inside your PCR_Discogs_API class
    */
    public function smart_download_and_attach_images($product_id, $discogs_data, $force_overwrite = false) {
        if (empty($discogs_data['images'])) {
            return new WP_Error('no_images', __('No images found for this release', 'pcr-discogs-api'));
        }
        
        // Get existing images and check their source
        $existing_images = $this->categorize_existing_images($product_id);
        
        error_log("PCR DEBUG: Found " . count($existing_images['user_uploaded']) . " user images, " . count($existing_images['discogs_downloaded']) . " discogs images");
        
        // If force_overwrite is false and user images exist, skip
        if (!$force_overwrite && !empty($existing_images['user_uploaded'])) {
            return new WP_Error('user_images_exist', __('User-uploaded images exist. Check "Force Overwrite" to replace.', 'pcr-discogs-api'));
        }
        
        // Clean up old Discogs images only
        $this->cleanup_discogs_images($existing_images['discogs_downloaded']);
        
        // Download new images using your existing method
        $downloaded_images = array();
        $featured_image_set = false;
        
        foreach ($discogs_data['images'] as $index => $image) {
            if (empty($image['uri'])) {
                continue;
            }
            
            // Use your existing download method
            $attachment_id = $this->download_image_to_media_library($image['uri'], $product_id, $index);
            
            if (is_wp_error($attachment_id)) {
                continue; 
            }
            
            // Mark as Discogs-downloaded for future identification
            update_post_meta($attachment_id, '_pcr_image_source', 'discogs');
            update_post_meta($attachment_id, '_pcr_discogs_release_id', $discogs_data['id']);
            update_post_meta($attachment_id, '_pcr_download_date', current_time('mysql'));
            
            $downloaded_images[] = $attachment_id;
            
            // Set first image as featured if no user thumbnail exists
            if (!$featured_image_set && empty($existing_images['user_thumbnail'])) {
                set_post_thumbnail($product_id, $attachment_id);
                $featured_image_set = true;
            }
        }
        
        if (empty($downloaded_images)) {
            return new WP_Error('download_failed', __('Failed to download any images', 'pcr-discogs-api'));
        }
        
        // Combine preserved user images with new Discogs images
        $final_gallery = array_merge($existing_images['user_uploaded'], $downloaded_images);
        $this->add_images_to_product_gallery($product_id, $final_gallery);
        
        return array(
            'message' => sprintf(__('Successfully downloaded %d new images. Preserved %d user images.', 'pcr-discogs-api'), 
            count($downloaded_images), count($existing_images['user_uploaded'])),
            'images_downloaded' => count($downloaded_images),
            'user_images_preserved' => count($existing_images['user_uploaded']),
            'featured_image_set' => $featured_image_set
        );
    }
    
    /**
     * Categorize existing images by source
    */
    public function categorize_existing_images($product_id) {
        $existing_gallery = get_post_meta($product_id, '_product_image_gallery', true);
        $existing_ids = !empty($existing_gallery) ? explode(',', $existing_gallery) : array();
        $thumbnail_id = get_post_thumbnail_id($product_id);
        
        if ($thumbnail_id && !in_array($thumbnail_id, $existing_ids)) {
            $existing_ids[] = $thumbnail_id;
        }
        
        $categorized = array(
            'user_uploaded' => array(),
            'discogs_downloaded' => array(),
            'user_thumbnail' => null
        );
        
        foreach ($existing_ids as $attachment_id) {
            if (empty($attachment_id)) continue;
            
            $source = get_post_meta($attachment_id, '_pcr_image_source', true);
            
            if ($source === 'discogs') {
                $categorized['discogs_downloaded'][] = $attachment_id;
            } else {
                // Assume it's user uploaded if not marked as discogs
                $categorized['user_uploaded'][] = $attachment_id;
                
                // Check if this user image is the current thumbnail
                if ($attachment_id == $thumbnail_id) {
                    $categorized['user_thumbnail'] = $attachment_id;
                }
            }
        }
        
        return $categorized;
    }
    
    /**
     * Clean up old Discogs images
    */
    private function cleanup_discogs_images($discogs_image_ids) {
        foreach ($discogs_image_ids as $attachment_id) {
            error_log("PCR DEBUG: Deleting old Discogs image {$attachment_id}");
            wp_delete_attachment($attachment_id, true);
        }
    }
    
    /**
     * Updated gallery management to handle existing images properly
    */
    private function add_images_to_product_gallery($product_id, $all_image_ids) {
        // Remove empty values and duplicates
        $all_image_ids = array_filter(array_unique($all_image_ids));
        
        // Update gallery with the combined list
        update_post_meta($product_id, '_product_image_gallery', implode(',', $all_image_ids));
        
        error_log("PCR DEBUG: Updated gallery for product {$product_id} with images: " . implode(',', $all_image_ids));
    }
    
    // -------------------------------------------
    // 2025-08-14
    
    /**
     * NEW: AJAX handler for downloading Discogs record data
    */
    public function ajax_download_record_data() {
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
        
        // Call Discogs API (reuse existing method)
        $discogs_data = $this->get_discogs_release($discogs_release_id, $api_token);
        
        if (is_wp_error($discogs_data)) {
            wp_send_json_error($discogs_data->get_error_message());
        }
        
        // Extract and update record data
        $result = $this->extract_and_update_record_data($product_id, $discogs_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Updated extract_and_update_record_data method with debugging
     * Replace the existing method in your PCR_Discogs_API class with this version
    */
    public function extract_and_update_record_data($product_id, $discogs_data) {
        $updated_fields = array();
        $errors = array();
        
        // Extract Year
        if (isset($discogs_data['year']) && !empty($discogs_data['year'])) {
            $year = intval($discogs_data['year']);
            if ($year > 0) {
                update_field('year', $year, $product_id);
                $updated_fields[] = 'Year: ' . $year;
            }
        } else {
            $errors[] = 'Year not found in Discogs data';
        }
        
        // Extract Country
        if (isset($discogs_data['country']) && !empty($discogs_data['country'])) {
            $country = sanitize_text_field($discogs_data['country']);
            update_field('country_of_origin', $country, $product_id);
            $updated_fields[] = 'Country: ' . $country;
        } else {
            $errors[] = 'Country not found in Discogs data';
        }

        // Extract Labels (add this to your existing method)
        if (isset($discogs_data['labels']) && is_array($discogs_data['labels']) && !empty($discogs_data['labels'])) {
            // Get the first label's name (most common use case)
            $primary_label = sanitize_text_field($discogs_data['labels'][0]['name']);
            
            // Or combine multiple labels with commas if there are several
            $all_labels = array();
            foreach ($discogs_data['labels'] as $label) {
                if (!empty($label['name'])) {
                    $all_labels[] = sanitize_text_field($label['name']);
                }
            }
            $labels_string = implode(', ', $all_labels);
            
            // Update the field (assuming you have a 'label' ACF field)
            update_field('label', $labels_string, $product_id);
            $updated_fields[] = 'Labels (' . count($all_labels) . '): ' . $labels_string;
            
            error_log("PCR DEBUG - Labels from Discogs: " . print_r($discogs_data['labels'], true));
        } else {
            $errors[] = 'Labels not found in Discogs data';
        }
        
        // Extract Genres (comma-separated) - WITH DEBUGGING
        if (isset($discogs_data['genres']) && is_array($discogs_data['genres']) && !empty($discogs_data['genres'])) {
            $genres_array = array_map('sanitize_text_field', $discogs_data['genres']);
            $genres_string = implode(', ', $genres_array);
            
            // DEBUG: Log what we're working with
            error_log("PCR DEBUG - Product ID: {$product_id}");
            error_log("PCR DEBUG - Raw genres from Discogs: " . print_r($discogs_data['genres'], true));
            error_log("PCR DEBUG - Sanitized genres array: " . print_r($genres_array, true));
            error_log("PCR DEBUG - Final genres string: " . $genres_string);
            error_log("PCR DEBUG - Genre count: " . count($genres_array));
            
            update_field('genres', $genres_string, $product_id);
            $updated_fields[] = 'Genres (' . count($genres_array) . '): ' . $genres_string;
        } else {
            // DEBUG: Log when no genres found
            if (isset($discogs_data['genres'])) {
                error_log("PCR DEBUG - Genres field exists but: " . print_r($discogs_data['genres'], true));
            } else {
                error_log("PCR DEBUG - No genres field in Discogs response");
            }
            $errors[] = 'Genres not found in Discogs data';
        }
        
        // Also check for 'styles' field which might contain additional genre info
        if (isset($discogs_data['styles']) && is_array($discogs_data['styles']) && !empty($discogs_data['styles'])) {
            error_log("PCR DEBUG - Styles from Discogs: " . print_r($discogs_data['styles'], true));
        }
        
        // Prepare response
        if (empty($updated_fields)) {
            return new WP_Error('no_data', __('No record data could be extracted from Discogs', 'pcr-discogs-api'));
        }
        
        $response = array(
            'message' => sprintf(__('Successfully updated %d field(s)', 'pcr-discogs-api'), count($updated_fields)),
            'updated_fields' => $updated_fields,
            'errors' => $errors,
            'release_title' => isset($discogs_data['title']) ? $discogs_data['title'] : 'Unknown Release'
        );
        
        return $response;
    }

    // --------------------------------------------------------------------------
    // 2025-08-08 // CATEGORIES 

    /**
     * NEW: AJAX handler for setting categories from Discogs data
     */
    public function ajax_set_categories_from_discogs() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'pcr_download_images')) {
            wp_die(__('Security check failed', 'pcr-discogs-api'));
        }
        
        // Check user permissions
        if (!current_user_can('edit_products') || !current_user_can('manage_product_terms')) {
            wp_die(__('Insufficient permissions', 'pcr-discogs-api'));
        }
        
        $product_id = intval($_POST['product_id']);
        $genres_text = get_field('genres', $product_id);
        
        if (empty($genres_text)) {
            wp_send_json_error(__('No genres data found for this product', 'pcr-discogs-api'));
        }
        
        // Process genres and set categories
        $result = $this->process_genres_to_categories($product_id, $genres_text);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }

    /**
     * NEW: Process genres text and create/assign categories
     */
    public function process_genres_to_categories($product_id, $genres_text) {
        $created_categories = array();
        $assigned_categories = array();
        $errors = array();
        $category_ids = array();
        
        // Parse comma-separated genres
        $genres_array = array_map('trim', explode(',', $genres_text));
        $genres_array = array_filter($genres_array); // Remove empty values
        
        error_log("PCR DEBUG - Processing genres for product {$product_id}: " . print_r($genres_array, true));
        
        foreach ($genres_array as $genre_name) {
            if (empty($genre_name)) {
                continue;
            }
            
            // Check if category exists
            $existing_term = get_term_by('name', $genre_name, 'product_cat');
            
            if ($existing_term) {
                // Category exists, just add to assignment list
                $category_ids[] = $existing_term->term_id;
                $assigned_categories[] = $genre_name;
                error_log("PCR DEBUG - Found existing category: {$genre_name} (ID: {$existing_term->term_id})");
            } else {
                // Create new category
                $new_term = wp_insert_term(
                    $genre_name,           // Term name
                    'product_cat',         // Taxonomy
                    array(
                        'description' => 'Auto-created from Discogs genre data',
                        'slug' => sanitize_title($genre_name)
                    )
                );
                
                if (is_wp_error($new_term)) {
                    $errors[] = "Failed to create category '{$genre_name}': " . $new_term->get_error_message();
                    error_log("PCR DEBUG - Failed to create category {$genre_name}: " . $new_term->get_error_message());
                } else {
                    $category_ids[] = $new_term['term_id'];
                    $created_categories[] = $genre_name;
                    $assigned_categories[] = $genre_name;
                    error_log("PCR DEBUG - Created new category: {$genre_name} (ID: {$new_term['term_id']})");
                }
            }
        }
        
        // Assign categories to product
        if (!empty($category_ids)) {
            $assignment_result = wp_set_post_terms($product_id, $category_ids, 'product_cat', false);
            
            if (is_wp_error($assignment_result)) {
                $errors[] = "Failed to assign categories to product: " . $assignment_result->get_error_message();
                error_log("PCR DEBUG - Failed to assign categories: " . $assignment_result->get_error_message());
            } else {
                error_log("PCR DEBUG - Successfully assigned categories to product {$product_id}: " . implode(', ', $category_ids));
            }
        }
        
        // Prepare response
        if (empty($assigned_categories) && empty($created_categories)) {
            return new WP_Error('no_categories', __('No categories could be processed', 'pcr-discogs-api'));
        }
        
        $response = array(
            'message' => sprintf(__('Successfully processed %d genre(s)', 'pcr-discogs-api'), count($assigned_categories)),
            'created_categories' => $created_categories,
            'assigned_categories' => $assigned_categories,
            'total_assigned' => count($assigned_categories),
            'total_created' => count($created_categories),
            'errors' => $errors
        );
        
        return $response;
    }
    
}

// Initialize the plugin
new PCR_Discogs_API();
