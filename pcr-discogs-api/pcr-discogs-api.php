<?php
/**
 * Plugin Name: PCR Discogs API
 * Plugin URI: https://pcr.sarazstudio.com
 * Description: Discogs API integration for Perfect Circle Records vinyl store
 * Version: 1.0.0
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
define('PCR_DISCOGS_API_VERSION', '1.0.0');
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
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        }
        
        // Initialize frontend functionality
        add_action('wp_enqueue_scripts', array($this, 'frontend_enqueue_scripts'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables, add options, etc.
        add_option('pcr_discogs_api_version', PCR_DISCOGS_API_VERSION);
        
        // Flush rewrite rules if needed
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up if needed
        flush_rewrite_rules();
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('PCR Discogs API', 'pcr-discogs-api'),           // Page title
            __('Discogs API', 'pcr-discogs-api'),               // Menu title
            'manage_options',                                     // Capability
            'pcr-discogs-api',                                   // Menu slug
            array($this, 'admin_page'),                          // Callback
            'dashicons-album',                                   // Icon (vinyl record icon)
            30                                                   // Position
        );
        
        // Add submenu pages
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
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <div class="notice notice-success">
                <p>
                    <strong><?php _e('🎵 PCR Discogs API Plugin is Active!', 'pcr-discogs-api'); ?></strong>
                </p>
                <p><?php _e('Version:', 'pcr-discogs-api'); ?> <?php echo PCR_DISCOGS_API_VERSION; ?></p>
            </div>
            
            <div class="card">
                <h2><?php _e('Plugin Status', 'pcr-discogs-api'); ?></h2>
                <p><?php _e('This plugin is successfully installed and activated. You can now configure Discogs API integration for your vinyl records store.', 'pcr-discogs-api'); ?></p>
                
                <h3><?php _e('Next Steps:', 'pcr-discogs-api'); ?></h3>
                <ul>
                    <li><?php _e('Configure your Discogs API credentials in Settings', 'pcr-discogs-api'); ?></li>
                    <li><?php _e('Set up automatic product imports', 'pcr-discogs-api'); ?></li>
                    <li><?php _e('Configure pricing and inventory sync', 'pcr-discogs-api'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Settings page callback
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('PCR Discogs API Settings', 'pcr-discogs-api'); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('pcr_discogs_api_settings'); ?>
                <?php do_settings_sections('pcr_discogs_api_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('API Token', 'pcr-discogs-api'); ?></th>
                        <td>
                            <input type="text" name="pcr_discogs_api_token" value="" class="regular-text" />
                            <p class="description"><?php _e('Enter your Discogs API token here.', 'pcr-discogs-api'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Auto Sync', 'pcr-discogs-api'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="pcr_discogs_auto_sync" value="1" />
                                <?php _e('Enable automatic product synchronization', 'pcr-discogs-api'); ?>
                            </label>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'pcr-discogs-api') === false) {
            return;
        }
        
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
        
        wp_enqueue_script(
            'pcr-discogs-api-frontend',
            PCR_DISCOGS_API_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            PCR_DISCOGS_API_VERSION,
            true
        );
    }
}

// Initialize the plugin
new PCR_Discogs_API();
