<?php
/**
 * PCR Discogs API - Image Cleanup Module
 * Handles cleanup of Discogs images when products are deleted or Release IDs change
 */

class PCR_Image_Cleanup {
    
    private $main_plugin;
    
    public function __construct($main_plugin) {
        $this->main_plugin = $main_plugin;
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Product deletion cleanup
        add_action('before_delete_post', array($this, 'cleanup_product_images'));
        
        // Discogs Release ID change detection
        add_action('acf/save_post', array($this, 'check_release_id_change'), 20);
        
        // Admin page hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_cleanup_admin_page'));
            add_action('wp_ajax_pcr_delete_all_discogs_images', array($this, 'ajax_delete_all_discogs_images'));
            add_action('wp_ajax_pcr_get_discogs_image_stats', array($this, 'ajax_get_discogs_image_stats'));
        }
    }
    
    /**
     * Cleanup images when a product is permanently deleted
     */
    public function cleanup_product_images($post_id) {
        // Only process WooCommerce products
        if (get_post_type($post_id) !== 'product') {
            return;
        }
        
        error_log("PCR CLEANUP: Product {$post_id} being deleted, cleaning up Discogs images");
        
        // Get all Discogs images for this product
        $discogs_images = $this->get_product_discogs_images($post_id);
        
        if (!empty($discogs_images)) {
            $this->delete_discogs_images($discogs_images);
            error_log("PCR CLEANUP: Deleted " . count($discogs_images) . " Discogs images for product {$post_id}");
        }
    }
    
    /**
     * Check if Discogs Release ID has changed and cleanup old images
     */
    public function check_release_id_change($post_id) {
        // Only process WooCommerce products
        if (get_post_type($post_id) !== 'product') {
            return;
        }
        
        // Get current and previous Release IDs
        $current_release_id = get_field('discogs_release_id', $post_id);
        $stored_release_id = get_post_meta($post_id, '_pcr_last_known_release_id', true);
        
        // If this is the first time or IDs haven't changed, just update stored ID
        if (empty($stored_release_id) || $current_release_id === $stored_release_id) {
            if (!empty($current_release_id)) {
                update_post_meta($post_id, '_pcr_last_known_release_id', $current_release_id);
            }
            return;
        }
        
        // Release ID has changed - cleanup old Discogs images
        error_log("PCR CLEANUP: Release ID changed for product {$post_id} (was: {$stored_release_id}, now: {$current_release_id})");
        
        // Get images that belong to the OLD release
        $old_discogs_images = $this->get_product_discogs_images_by_release($post_id, $stored_release_id);
        
        if (!empty($old_discogs_images)) {
            $this->delete_discogs_images($old_discogs_images);
            error_log("PCR CLEANUP: Deleted " . count($old_discogs_images) . " old Discogs images for changed release ID");
        }
        
        // Update stored release ID
        update_post_meta($post_id, '_pcr_last_known_release_id', $current_release_id);
    }
    
    /**
     * Get all Discogs images for a specific product
     */
    private function get_product_discogs_images($product_id) {
        // Use the existing categorization method from main plugin
        if (method_exists($this->main_plugin, 'categorize_existing_images')) {
            $categorized = $this->main_plugin->categorize_existing_images($product_id);
            return $categorized['discogs_downloaded'];
        }
        
        // Fallback: manual search for Discogs images
        return $this->find_discogs_images_for_product($product_id);
    }
    
    /**
     * Get Discogs images for a product filtered by specific release ID
     */
    private function get_product_discogs_images_by_release($product_id, $release_id) {
        $all_discogs_images = $this->get_product_discogs_images($product_id);
        $filtered_images = array();
        
        foreach ($all_discogs_images as $attachment_id) {
            $image_release_id = get_post_meta($attachment_id, '_pcr_discogs_release_id', true);
            if ($image_release_id === $release_id) {
                $filtered_images[] = $attachment_id;
            }
        }
        
        return $filtered_images;
    }
    
    /**
     * Fallback method to find Discogs images for a product
     */
    private function find_discogs_images_for_product($product_id) {
        $discogs_images = array();
        
        // Check gallery images
        $gallery = get_post_meta($product_id, '_product_image_gallery', true);
        if (!empty($gallery)) {
            $gallery_ids = explode(',', $gallery);
            foreach ($gallery_ids as $attachment_id) {
                if ($this->is_discogs_image($attachment_id)) {
                    $discogs_images[] = $attachment_id;
                }
            }
        }
        
        // Check featured image
        $thumbnail_id = get_post_thumbnail_id($product_id);
        if ($thumbnail_id && $this->is_discogs_image($thumbnail_id)) {
            $discogs_images[] = $thumbnail_id;
        }
        
        return array_unique($discogs_images);
    }
    
    /**
     * Check if an image is from Discogs
     */
    private function is_discogs_image($attachment_id) {
        $source = get_post_meta($attachment_id, '_pcr_image_source', true);
        return $source === 'discogs';
    }
    
    /**
     * Delete Discogs images
     */
    private function delete_discogs_images($image_ids) {
        foreach ($image_ids as $attachment_id) {
            if ($this->is_discogs_image($attachment_id)) {
                wp_delete_attachment($attachment_id, true);
                error_log("PCR CLEANUP: Deleted Discogs image {$attachment_id}");
            }
        }
    }
    
    /**
     * Add cleanup admin page
     */
    public function add_cleanup_admin_page() {
        add_submenu_page(
            'pcr-discogs-api',
            __('Image Cleanup', 'pcr-discogs-api'),
            __('Image Cleanup', 'pcr-discogs-api'),
            'manage_options',
            'pcr-image-cleanup',
            array($this, 'cleanup_admin_page')
        );
    }
    
    /**
     * Cleanup admin page
     */
    public function cleanup_admin_page() {
        ?>
        <div class="wrap pcr-cleanup-admin">
            <h1><?php _e('🧹 Image Cleanup Tools', 'pcr-discogs-api'); ?></h1>
            
            <div class="notice notice-warning">
                <p><strong><?php _e('⚠️ Warning:', 'pcr-discogs-api'); ?></strong> <?php _e('These actions cannot be undone. Discogs images can be re-downloaded, but user-uploaded images cannot be recovered.', 'pcr-discogs-api'); ?></p>
            </div>
            
            <div class="card">
                <h2><?php _e('Discogs Image Statistics', 'pcr-discogs-api'); ?></h2>
                <p><?php _e('Get current statistics about Discogs images in your media library.', 'pcr-discogs-api'); ?></p>
                
                <button type="button" class="button button-secondary" id="pcr-get-stats">
                    <?php _e('Get Statistics', 'pcr-discogs-api'); ?>
                </button>
                
                <div id="pcr-stats-results" class="pcr-results-area"></div>
            </div>
            
            <div class="card">
                <h2><?php _e('Delete All Discogs Images', 'pcr-discogs-api'); ?></h2>
                <p><?php _e('This will delete ALL images downloaded from Discogs across your entire site. User-uploaded images will be preserved.', 'pcr-discogs-api'); ?></p>
                <p><?php _e('This is useful for testing batch processing or completely resetting your Discogs image library.', 'pcr-discogs-api'); ?></p>
                
                <button type="button" class="button button-primary pcr-danger-button" id="pcr-delete-all-discogs">
                    <?php _e('🗑️ Delete ALL Discogs Images', 'pcr-discogs-api'); ?>
                </button>
                
                <div id="pcr-delete-results" class="pcr-results-area"></div>
            </div>
            
            <div class="card">
                <h2><?php _e('Automatic Cleanup Settings', 'pcr-discogs-api'); ?></h2>
                <p><?php _e('Current automatic cleanup behavior:', 'pcr-discogs-api'); ?></p>
                <ul>
                    <li>✅ <?php _e('Delete Discogs images when product is permanently deleted', 'pcr-discogs-api'); ?></li>
                    <li>✅ <?php _e('Delete old Discogs images when Release ID changes', 'pcr-discogs-api'); ?></li>
                    <li>✅ <?php _e('Preserve user-uploaded images always', 'pcr-discogs-api'); ?></li>
                </ul>
            </div>
        </div>
        
        <style>
        .pcr-cleanup-admin .card {
            margin: 20px 0;
            padding: 20px;
        }
        
        .pcr-results-area {
            margin-top: 15px;
            padding: 15px;
            background: #f9f9f9;
            border-radius: 4px;
            display: none;
        }
        
        .pcr-danger-button {
            background: #dc3232 !important;
            border-color: #dc3232 !important;
            color: #fff !important;
        }
        
        .pcr-danger-button:hover {
            background: #a00 !important;
            border-color: #a00 !important;
        }
        
        .pcr-stats-table {
            border-collapse: collapse;
            width: 100%;
            margin: 10px 0;
        }
        
        .pcr-stats-table th,
        .pcr-stats-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        
        .pcr-stats-table th {
            background: #f5f5f5;
            font-weight: bold;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Get statistics
            $('#pcr-get-stats').on('click', function() {
                const $button = $(this);
                const $results = $('#pcr-stats-results');
                
                $button.prop('disabled', true).text('Loading...');
                $results.show().html('<p>Calculating statistics...</p>');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'pcr_get_discogs_image_stats',
                        nonce: '<?php echo wp_create_nonce('pcr_image_cleanup'); ?>'
                    }
                })
                .done(function(response) {
                    if (response.success) {
                        const data = response.data;
                        let html = '<h3>📊 Statistics</h3>';
                        html += '<table class="pcr-stats-table">';
                        html += '<tr><th>Metric</th><th>Count</th></tr>';
                        html += '<tr><td>Total Discogs Images</td><td>' + data.total_discogs_images + '</td></tr>';
                        html += '<tr><td>Products with Discogs Images</td><td>' + data.products_with_discogs_images + '</td></tr>';
                        html += '<tr><td>Estimated Storage Used</td><td>' + data.estimated_size + '</td></tr>';
                        html += '</table>';
                        
                        if (data.orphaned_images > 0) {
                            html += '<p><strong>⚠️ Found ' + data.orphaned_images + ' potentially orphaned Discogs images</strong></p>';
                        }
                        
                        $results.html(html);
                    } else {
                        $results.html('<p class="notice notice-error">Error: ' + response.data + '</p>');
                    }
                })
                .fail(function() {
                    $results.html('<p class="notice notice-error">Network error occurred</p>');
                })
                .always(function() {
                    $button.prop('disabled', false).text('Get Statistics');
                });
            });
            
            // Delete all Discogs images
            $('#pcr-delete-all-discogs').on('click', function() {
                if (!confirm('Are you sure you want to delete ALL Discogs images? This cannot be undone!')) {
                    return;
                }
                
                if (!confirm('This will delete hundreds or thousands of images. Are you absolutely sure?')) {
                    return;
                }
                
                const $button = $(this);
                const $results = $('#pcr-delete-results');
                
                $button.prop('disabled', true).text('🗑️ Deleting...');
                $results.show().html('<p>Deleting all Discogs images... This may take a while.</p>');
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'pcr_delete_all_discogs_images',
                        nonce: '<?php echo wp_create_nonce('pcr_image_cleanup'); ?>'
                    },
                    timeout: 300000 // 5 minutes
                })
                .done(function(response) {
                    if (response.success) {
                        const data = response.data;
                        let resultHtml = '<div class="notice notice-success"><p><strong>✅ Complete!</strong><br>' + 
                            'Deleted ' + data.deleted_count + ' Discogs images<br>' +
                            'Freed up approximately ' + data.size_freed + ' of storage';
                        
                        // Show cleanup info if any products were cleaned
                        if (data.cleaned_products > 0) {
                            resultHtml += '<br>Fixed orphaned references in ' + data.cleaned_products + ' products';
                        }
                        
                        resultHtml += '</p></div>';
                        $results.html(resultHtml);
                    } else {
                        $results.html('<div class="notice notice-error"><p>Error: ' + response.data + '</p></div>');
                    }
                })
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX: Get Discogs image statistics
     */
    public function ajax_get_discogs_image_stats() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'pcr-discogs-api'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'pcr_image_cleanup')) {
            wp_die(__('Security check failed', 'pcr-discogs-api'));
        }
        
        $stats = $this->calculate_discogs_image_stats();
        wp_send_json_success($stats);
    }
    
    /**
     * AJAX: Delete all Discogs images
     */
    public function ajax_delete_all_discogs_images() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'pcr-discogs-api'));
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'pcr_image_cleanup')) {
            wp_die(__('Security check failed', 'pcr-discogs-api'));
        }
        
        $result = $this->delete_all_discogs_images();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Calculate statistics about Discogs images
     */
    private function calculate_discogs_image_stats() {
        global $wpdb;
        
        // Get all Discogs images
        $discogs_images = $wpdb->get_results("
            SELECT p.ID, pm.meta_value as file_path
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            INNER JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id
            WHERE p.post_type = 'attachment'
            AND pm.meta_key = '_wp_attached_file'
            AND pm2.meta_key = '_pcr_image_source'
            AND pm2.meta_value = 'discogs'
        ");
        
        $total_count = count($discogs_images);
        $total_size = 0;
        
        // Calculate total size
        $upload_dir = wp_upload_dir();
        foreach ($discogs_images as $image) {
            $file_path = $upload_dir['basedir'] . '/' . $image->file_path;
            if (file_exists($file_path)) {
                $total_size += filesize($file_path);
            }
        }
        
        // Count products with Discogs images
        $products_with_discogs = $wpdb->get_var("
            SELECT COUNT(DISTINCT p.post_parent)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'attachment'
            AND pm.meta_key = '_pcr_image_source'
            AND pm.meta_value = 'discogs'
            AND p.post_parent > 0
        ");
        
        // Check for orphaned images (Discogs images not attached to any product)
        $orphaned_count = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'attachment'
            AND pm.meta_key = '_pcr_image_source'
            AND pm.meta_value = 'discogs'
            AND (p.post_parent = 0 OR p.post_parent NOT IN (
                SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product'
            ))
        ");
        
        return array(
            'total_discogs_images' => $total_count,
            'products_with_discogs_images' => intval($products_with_discogs),
            'orphaned_images' => intval($orphaned_count),
            'estimated_size' => $this->format_bytes($total_size)
        );
    }
    
    /**
     * Delete all Discogs images from the site + cleanup orphaned references
     * Replace this method in your class-pcr-image-cleanup.php file
     */
    private function delete_all_discogs_images() {
        global $wpdb;
        
        // Get all Discogs images
        $discogs_image_ids = $wpdb->get_col("
            SELECT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'attachment'
            AND pm.meta_key = '_pcr_image_source'
            AND pm.meta_value = 'discogs'
        ");
        
        $deleted_count = 0;
        $total_size = 0;
        
        // Delete the actual image files
        foreach ($discogs_image_ids as $attachment_id) {
            // Get file size before deletion
            $file_path = get_attached_file($attachment_id);
            if ($file_path && file_exists($file_path)) {
                $total_size += filesize($file_path);
            }
            
            // Delete the attachment
            if (wp_delete_attachment($attachment_id, true)) {
                $deleted_count++;
            }
        }
        
        // NOW: Clean up orphaned references in product galleries and thumbnails
        $cleaned_products = $this->cleanup_orphaned_image_references();
        
        return array(
            'deleted_count' => $deleted_count,
            'size_freed' => $this->format_bytes($total_size),
            'cleaned_products' => $cleaned_products
        );
    }

    /**
     * Clean up orphaned image references in product galleries and thumbnails
     * Add this method to your class-pcr-image-cleanup.php file
     */
    private function cleanup_orphaned_image_references() {
        // Get all products
        $products = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'any',
            'posts_per_page' => -1
        ));
        
        $fixed_count = 0;
        
        foreach ($products as $product) {
            $product_id = $product->ID;
            $fixed = false;
            
            // Check gallery
            $gallery = get_post_meta($product_id, '_product_image_gallery', true);
            if (!empty($gallery)) {
                $gallery_ids = explode(',', $gallery);
                $valid_ids = array();
                
                foreach ($gallery_ids as $attachment_id) {
                    if (!empty($attachment_id) && get_post($attachment_id)) {
                        $valid_ids[] = $attachment_id;
                    } else {
                        error_log("PCR CLEANUP: Removing orphaned gallery reference {$attachment_id} from product {$product_id}");
                        $fixed = true;
                    }
                }
                
                if ($fixed) {
                    update_post_meta($product_id, '_product_image_gallery', implode(',', $valid_ids));
                }
            }
            
            // Check featured image
            $thumbnail_id = get_post_thumbnail_id($product_id);
            if ($thumbnail_id && !get_post($thumbnail_id)) {
                error_log("PCR CLEANUP: Removing orphaned thumbnail reference {$thumbnail_id} from product {$product_id}");
                delete_post_meta($product_id, '_thumbnail_id');
                $fixed = true;
            }
            
            if ($fixed) {
                $fixed_count++;
            }
        }
        
        error_log("PCR CLEANUP: Fixed orphaned references in {$fixed_count} products");
        return $fixed_count;
    }

    /**
     * Format bytes for human reading
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

// Add to your main plugin initialization:
// if (class_exists('PCR_Image_Cleanup')) {
//     $this->image_cleanup = new PCR_Image_Cleanup($this);
// }
// DONE - 2025-08-14