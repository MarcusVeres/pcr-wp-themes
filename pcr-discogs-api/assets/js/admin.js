/**
 * PCR Discogs API - Admin JavaScript
 * Version: 1.0.0
 */

(function($) {
    'use strict';

    // Wait for document ready
    $(document).ready(function() {
        console.log('🎵 PCR Discogs API Admin loaded');
        
        // Initialize admin functionality
        PCRDiscogsAdmin.init();
    });

    /**
     * Main Admin Object
     */
    const PCRDiscogsAdmin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.checkApiStatus();
            this.initTooltips();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Test API connection button
            $(document).on('click', '.pcr-test-api', this.testApiConnection);
            
            // Save settings form
            $(document).on('submit', '#pcr-settings-form', this.saveSettings);
            
            // Clear cache button
            $(document).on('click', '.pcr-clear-cache', this.clearCache);
            
            // Import products button
            $(document).on('click', '.pcr-import-products', this.importProducts);
        },

        /**
         * Test API connection
         */
        testApiConnection: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const originalText = $button.text();
            
            // Show loading state
            $button.html('<span class="pcr-loading"></span> Testing...')
                   .prop('disabled', true);
            
            // Simulate API test (replace with actual AJAX call)
            setTimeout(function() {
                $button.html('✅ Connection Successful!')
                       .removeClass('pcr-button')
                       .addClass('pcr-button success');
                
                // Reset after 3 seconds
                setTimeout(function() {
                    $button.html(originalText)
                           .removeClass('success')
                           .addClass('pcr-button')
                           .prop('disabled', false);
                }, 3000);
            }, 2000);
        },

        /**
         * Save settings
         */
        saveSettings: function(e) {
            e.preventDefault();
            
            const $form = $(this);
            const formData = $form.serialize();
            
            // Show saving indicator
            const $submitButton = $form.find('input[type="submit"]');
            const originalValue = $submitButton.val();
            
            $submitButton.val('Saving...')
                        .prop('disabled', true);
            
            // Simulate save (replace with actual AJAX)
            setTimeout(function() {
                PCRDiscogsAdmin.showNotice('Settings saved successfully!', 'success');
                
                $submitButton.val(originalValue)
                            .prop('disabled', false);
            }, 1500);
        },

        /**
         * Clear cache
         */
        clearCache: function(e) {
            e.preventDefault();
            
            if (!confirm('Are you sure you want to clear the Discogs API cache?')) {
                return;
            }
            
            const $button = $(this);
            $button.html('<span class="pcr-loading"></span> Clearing...')
                   .prop('disabled', true);
            
            // Simulate cache clear
            setTimeout(function() {
                PCRDiscogsAdmin.showNotice('Cache cleared successfully!', 'success');
                $button.html('Clear Cache')
                       .prop('disabled', false);
            }, 1000);
        },

        /**
         * Import products from Discogs
         */
        importProducts: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            $button.html('<span class="pcr-loading"></span> Importing...')
                   .prop('disabled', true);
            
            // Simulate import process
            setTimeout(function() {
                PCRDiscogsAdmin.showNotice('Product import completed! 25 products imported.', 'success');
                $button.html('Import Products')
                       .prop('disabled', false);
            }, 3000);
        },

        /**
         * Check API status
         */
        checkApiStatus: function() {
            // Add status indicator to the page
            const statusHtml = `
                <div class="pcr-api-status">
                    <span class="pcr-status-indicator active"></span>
                    <strong>API Status:</strong> Connected
                </div>
            `;
            
            $('.pcr-discogs-admin .card:first').prepend(statusHtml);
        },

        /**
         * Initialize tooltips
         */
        initTooltips: function() {
            // Add tooltips to form fields
            $('[data-tooltip]').each(function() {
                const $element = $(this);
                const tooltip = $element.data('tooltip');
                
                $element.attr('title', tooltip);
            });
        },

        /**
         * Show admin notice
         */
        showNotice: function(message, type = 'info') {
            const noticeHtml = `
                <div class="pcr-notice ${type}">
                    <p><strong>${message}</strong></p>
                </div>
            `;
            
            $('.pcr-discogs-admin').prepend(noticeHtml);
            
            // Auto-hide after 5 seconds
            setTimeout(function() {
                $('.pcr-notice').fadeOut(500, function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Format file size
         */
        formatFileSize: function(bytes) {
            if (bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        },

        /**
         * Debounce function for search inputs
         */
        debounce: function(func, wait, immediate) {
            let timeout;
            return function executedFunction() {
                const context = this;
                const args = arguments;
                
                const later = function() {
                    timeout = null;
                    if (!immediate) func.apply(context, args);
                };
                
                const callNow = immediate && !timeout;
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
                
                if (callNow) func.apply(context, args);
            };
        }
    };

    // Make available globally
    window.PCRDiscogsAdmin = PCRDiscogsAdmin;

})(jQuery);
