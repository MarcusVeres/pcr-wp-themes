<?php
/**
 * PCR Discogs API - Batch Processing Module
 * Add this as a new file: includes/class-pcr-batch-processor.php
 * OR add to your existing plugin file in a separate section
 */

class PCR_Discogs_Batch_Processor {
    
    private $main_plugin;
    
    public function __construct($main_plugin) {
        error_log("PCR DEBUG: Batch processor initialized successfully");
        $this->main_plugin = $main_plugin;
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_batch_admin_page'));
        add_action('wp_ajax_pcr_get_batch_candidates', array($this, 'ajax_get_batch_candidates'));
        add_action('wp_ajax_pcr_process_batch_item', array($this, 'ajax_process_batch_item'));
        add_action('wp_ajax_pcr_get_batch_status', array($this, 'ajax_get_batch_status'));
    }
    
    /**
     * Add batch processing admin page
     */
    public function add_batch_admin_page() {
        error_log("PCR DEBUG: Adding batch admin page");
        add_submenu_page(
            'pcr-discogs-api',
            __('Batch Processing', 'pcr-discogs-api'),
            __('Batch Processing', 'pcr-discogs-api'),
            'manage_options',
            'pcr-discogs-batch',
            array($this, 'batch_admin_page')
        );
    }
    
    /**
     * Batch processing admin page
     */
    public function batch_admin_page() {
        $api_token = get_option('pcr_discogs_api_token', '');
        ?>
        <div class="wrap pcr-batch-admin">
            <h1><?php _e('🎵 Discogs Batch Processing', 'pcr-discogs-api'); ?></h1>
            
            <?php if (empty($api_token)): ?>
                <div class="notice notice-error">
                    <p>
                        <strong><?php _e('No API token configured.', 'pcr-discogs-api'); ?></strong>
                        <a href="<?php echo admin_url('admin.php?page=pcr-discogs-api-settings'); ?>">
                            <?php _e('Configure API token first', 'pcr-discogs-api'); ?>
                        </a>
                    </p>
                </div>
            <?php else: ?>
                
                <!-- Step 1: Scan for candidates -->
                <div class="card pcr-batch-step" id="pcr-step-scan">
                    <h2><?php _e('Step 1: Scan Products', 'pcr-discogs-api'); ?></h2>
                    <p><?php _e('Find products with Discogs Release IDs that can be processed.', 'pcr-discogs-api'); ?></p>
                    
                    <button type="button" class="button button-primary" id="pcr-scan-products">
                        <?php _e('Scan for Products to Process', 'pcr-discogs-api'); ?>
                    </button>
                    
                    <div id="pcr-scan-results" class="pcr-results-area"></div>
                </div>
                
                <!-- Step 2: Configure processing options -->
                <div class="card pcr-batch-step" id="pcr-step-configure" style="display: none;">
                    <h2><?php _e('Step 2: Configure Processing', 'pcr-discogs-api'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Process Images', 'pcr-discogs-api'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="pcr-process-images" checked />
                                    <?php _e('Download images (respects "search_internet_for_photos" field)', 'pcr-discogs-api'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Process Data', 'pcr-discogs-api'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="pcr-process-data" checked />
                                    <?php _e('Download year, country, and genres', 'pcr-discogs-api'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Process Categories', 'pcr-discogs-api'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="pcr-process-categories" checked />
                                    <?php _e('Create/assign categories from genres', 'pcr-discogs-api'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Processing Speed', 'pcr-discogs-api'); ?></th>
                            <td>
                                <select id="pcr-processing-speed">
                                    <option value="1000"><?php _e('1 request per second (safe)', 'pcr-discogs-api'); ?></option>
                                    <option value="1500"><?php _e('1 request per 1.5 seconds (very safe)', 'pcr-discogs-api'); ?></option>
                                    <option value="2000"><?php _e('1 request per 2 seconds (ultra safe)', 'pcr-discogs-api'); ?></option>
                                </select>
                                <p class="description"><?php _e('Slower is safer for large batches', 'pcr-discogs-api'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <button type="button" class="button button-primary" id="pcr-start-processing">
                        <?php _e('Start Batch Processing', 'pcr-discogs-api'); ?>
                    </button>
                </div>
                
                <!-- Step 3: Processing progress -->
                <div class="card pcr-batch-step" id="pcr-step-progress" style="display: none;">
                    <h2><?php _e('Step 3: Processing...', 'pcr-discogs-api'); ?></h2>
                    
                    <div class="pcr-progress-container">
                        <div class="pcr-progress-bar">
                            <div class="pcr-progress-fill" style="width: 0%"></div>
                        </div>
                        <div class="pcr-progress-text">0 / 0 processed</div>
                    </div>
                    
                    <div class="pcr-current-item">
                        <strong><?php _e('Currently processing:', 'pcr-discogs-api'); ?></strong> 
                        <span id="pcr-current-product"><?php _e('None', 'pcr-discogs-api'); ?></span>
                    </div>
                    
                    <button type="button" class="button button-secondary" id="pcr-pause-processing" style="display: none;">
                        <?php _e('Pause Processing', 'pcr-discogs-api'); ?>
                    </button>
                    
                    <div id="pcr-processing-log" class="pcr-log-area"></div>
                </div>
                
                <!-- Step 4: Results -->
                <div class="card pcr-batch-step" id="pcr-step-results" style="display: none;">
                    <h2><?php _e('Step 4: Results', 'pcr-discogs-api'); ?></h2>
                    <div id="pcr-final-results" class="pcr-results-area"></div>
                    
                    <button type="button" class="button button-primary" id="pcr-start-over">
                        <?php _e('Process Another Batch', 'pcr-discogs-api'); ?>
                    </button>
                </div>
                
            <?php endif; ?>
        </div>
        
        <style>
        .pcr-batch-admin .card {
            margin: 20px 0;
            padding: 20px;
        }
        
        .pcr-results-area {
            margin-top: 15px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
        }
        
        .pcr-progress-container {
            margin: 20px 0;
        }
        
        .pcr-progress-bar {
            width: 100%;
            height: 30px;
            background: #e0e0e0;
            border-radius: 15px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .pcr-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #45a049);
            transition: width 0.3s ease;
            border-radius: 15px;
        }
        
        .pcr-progress-text {
            text-align: center;
            font-weight: bold;
            color: #333;
        }
        
        .pcr-current-item {
            margin: 15px 0;
            padding: 10px;
            background: #fff;
            border-left: 4px solid #2196F3;
        }
        
        .pcr-log-area {
            max-height: 300px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #ddd;
            padding: 10px;
            margin-top: 15px;
            font-family: monospace;
            font-size: 12px;
        }
        
        .pcr-log-entry {
            margin: 5px 0;
            padding: 5px;
            border-radius: 3px;
        }
        
        .pcr-log-entry.success {
            background: #d4edda;
            color: #155724;
        }
        
        .pcr-log-entry.error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .pcr-log-entry.info {
            background: #cce7ff;
            color: #004085;
        }
        
        .pcr-candidate-list {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            background: #fff;
        }
        
        .pcr-candidate-item {
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        
        .pcr-candidate-item:last-child {
            border-bottom: none;
        }
        
        .pcr-loading {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        </style>
        <?php
    }
    
    /**
     * AJAX: Get batch processing candidates
     */
    public function ajax_get_batch_candidates() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'pcr-discogs-api'));
        }
        
        $candidates = $this->find_batch_candidates();
        wp_send_json_success($candidates);
    }
    
    /**
     * AJAX: Process single batch item
     */
    public function ajax_process_batch_item() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'pcr-discogs-api'));
        }
        
        $product_id = intval($_POST['product_id']);
        $options = $_POST['options'];
        
        $result = $this->process_single_product($product_id, $options);
        wp_send_json($result);
    }
    
    /**
     * AJAX: Get batch processing status
     */
    public function ajax_get_batch_status() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'pcr-discogs-api'));
        }
        
        $status = get_transient('pcr_batch_status');
        wp_send_json_success($status ?: array());
    }
    
    /**
     * Find products eligible for batch processing
     */
    private function find_batch_candidates() {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => 'discogs_release_id',
                    'value' => '',
                    'compare' => '!='
                )
            )
        );
        
        $products = get_posts($args);
        $candidates = array();
        
        foreach ($products as $product) {
            $release_id = get_field('discogs_release_id', $product->ID);
            $search_photos = get_field('search_internet_for_photos', $product->ID);
            
            if (!empty($release_id)) {
                $candidates[] = array(
                    'id' => $product->ID,
                    'title' => $product->post_title,
                    'release_id' => $release_id,
                    'search_photos' => $search_photos ? true : false,
                    'status' => $product->post_status
                );
            }
        }
        
        return $candidates;
    }
    
    /**
     * UPDATED: process_single_product method with debugging and proper boolean handling
     * Replace this method in your class-pcr-batch-processor.php file
     */
    private function process_single_product($product_id, $options) {
        $api_token = get_option('pcr_discogs_api_token', '');
        $release_id = get_field('discogs_release_id', $product_id);
        $search_photos = get_field('search_internet_for_photos', $product_id);
        
        $result = array(
            'success' => false,
            'product_id' => $product_id,
            'product_title' => get_the_title($product_id),
            'operations' => array(),
            'errors' => array()
        );
        
        // DEBUG: Log exactly what options we received
        error_log("PCR BATCH DEBUG - Product: " . get_the_title($product_id));
        error_log("PCR BATCH DEBUG - Raw options received: " . print_r($options, true));
        error_log("PCR BATCH DEBUG - search_internet_for_photos field: " . ($search_photos ? 'true' : 'false'));
        
        // IMPORTANT: Convert string booleans to actual booleans
        $process_images = ($options['process_images'] === true || $options['process_images'] === 'true' || $options['process_images'] === '1');
        $process_data = ($options['process_data'] === true || $options['process_data'] === 'true' || $options['process_data'] === '1');
        $process_categories = ($options['process_categories'] === true || $options['process_categories'] === 'true' || $options['process_categories'] === '1');
        
        error_log("PCR BATCH DEBUG - Converted process_images: " . ($process_images ? 'TRUE' : 'FALSE'));
        error_log("PCR BATCH DEBUG - Converted process_data: " . ($process_data ? 'TRUE' : 'FALSE'));
        error_log("PCR BATCH DEBUG - Converted process_categories: " . ($process_categories ? 'TRUE' : 'FALSE'));
        
        if (empty($release_id)) {
            $result['errors'][] = 'No Discogs Release ID found';
            return $result;
        }
        
        if (empty($api_token)) {
            $result['errors'][] = 'No API token configured';
            return $result;
        }
        
        // Get Discogs data
        $discogs_data = $this->main_plugin->get_discogs_release($release_id, $api_token);
        
        if (is_wp_error($discogs_data)) {
            $result['errors'][] = 'Discogs API error: ' . $discogs_data->get_error_message();
            return $result;
        }
        
        // Process images if requested and allowed
        if ($process_images && $search_photos) {
            error_log("PCR BATCH DEBUG - PROCESSING IMAGES: Both conditions met");
            $image_result = $this->main_plugin->smart_download_and_attach_images($product_id, $discogs_data, false);
            if (is_wp_error($image_result)) {
                $result['errors'][] = 'Images: ' . $image_result->get_error_message();
            } else {
                $result['operations'][] = 'Images: ' . $image_result['images_downloaded'] . ' downloaded';
            }
        } elseif ($process_images && !$search_photos) {
            error_log("PCR BATCH DEBUG - SKIPPING IMAGES: process_images=true but search_photos=false");
            $result['operations'][] = 'Images: Skipped (search_internet_for_photos = false)';
        } else {
            error_log("PCR BATCH DEBUG - SKIPPING IMAGES: process_images=false");
            $result['operations'][] = 'Images: Skipped (not requested)';
        }
        
        // Process record data if requested
        if ($process_data) {
            error_log("PCR BATCH DEBUG - PROCESSING DATA");
            $data_result = $this->main_plugin->extract_and_update_record_data($product_id, $discogs_data);
            if (is_wp_error($data_result)) {
                $result['errors'][] = 'Data: ' . $data_result->get_error_message();
            } else {
                $result['operations'][] = 'Data: ' . count($data_result['updated_fields']) . ' fields updated';
            }
        } else {
            error_log("PCR BATCH DEBUG - SKIPPING DATA: not requested");
            $result['operations'][] = 'Data: Skipped (not requested)';
        }
        
        // Process categories if requested
        if ($process_categories) {
            error_log("PCR BATCH DEBUG - PROCESSING CATEGORIES");
            $genres = get_field('genres', $product_id);
            if (!empty($genres)) {
                $categories_result = $this->main_plugin->process_genres_to_categories($product_id, $genres);
                if (is_wp_error($categories_result)) {
                    $result['errors'][] = 'Categories: ' . $categories_result->get_error_message();
                } else {
                    $result['operations'][] = 'Categories: ' . $categories_result['total_assigned'] . ' assigned (' . $categories_result['total_created'] . ' created)';
                }
            } else {
                $result['operations'][] = 'Categories: No genres data available';
            }
        } else {
            error_log("PCR BATCH DEBUG - SKIPPING CATEGORIES: not requested");
            $result['operations'][] = 'Categories: Skipped (not requested)';
        }
        
        $result['success'] = empty($result['errors']);
        return $result;
    }

}

// REQURIEMENT :: Add to your main plugin initialization:
// $this->batch_processor = new PCR_Discogs_Batch_Processor($this);
// Done on 2025-08-14
