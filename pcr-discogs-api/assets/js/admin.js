/**
 * PCR Discogs API - Admin JavaScript
 * Version: 1.0.2
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
            
            // Download images button
            $(document).on('click', '.pcr-download-images', this.downloadImages);
            
            // Save settings form
            $(document).on('submit', '#pcr-settings-form', this.saveSettings);
        },

        /**
         * Test API connection
         */
        testApiConnection: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const originalText = $button.text();
            const $resultDiv = $('.pcr-test-result');
            
            // Show loading state
            $button.html('<span class="pcr-loading"></span> ' + pcrDiscogsAjax.strings.testing)
                   .prop('disabled', true);
            
            // Get API token from form
            const apiToken = $('input[name="pcr_discogs_api_token"]').val();
            
            if (!apiToken) {
                $resultDiv.html('<div class="pcr-notice error"><p>Please enter an API token first.</p></div>');
                $button.html(originalText).prop('disabled', false);
                return;
            }
            
            // Test with a simple API call (get user identity)
            $.ajax({
                url: 'https://api.discogs.com/oauth/identity',
                method: 'GET',
                headers: {
                    'Authorization': 'Discogs token=' + apiToken,
                    'User-Agent': 'PCRDiscogsAPI/1.0 +https://pcr.sarazstudio.com'
                },
                timeout: 10000
            })
            .done(function(data) {
                $resultDiv.html('<div class="pcr-notice success"><p>' + 
                    pcrDiscogsAjax.strings.test_success + 
                    '<br>Connected as: <strong>' + (data.username || 'Unknown') + '</strong></p></div>');
                
                $button.html('✅ ' + pcrDiscogsAjax.strings.test_success)
                       .removeClass('button')
                       .addClass('button button-primary');
            })
            .fail(function(xhr) {
                let errorMsg = pcrDiscogsAjax.strings.test_error;
                
                if (xhr.status === 401) {
                    errorMsg += ' Invalid token';
                } else if (xhr.status === 0) {
                    errorMsg += ' Network error';
                } else {
                    errorMsg += ' HTTP ' + xhr.status;
                }
                
                $resultDiv.html('<div class="pcr-notice error"><p>' + errorMsg + '</p></div>');
            })
            .always(function() {
                // Reset after 3 seconds
                setTimeout(function() {
                    $button.html(originalText)
                           .removeClass('button-primary')
                           .addClass('button')
                           .prop('disabled', false);
                }, 3000);
            });
        },

        /**
         * Download images from Discogs
         */
        downloadImages: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const productId = $button.data('product-id');
            const $statusDiv = $('.pcr-download-status');
            const originalText = $button.text();
            
            // Show loading state
            $button.html('<span class="pcr-loading"></span> ' + pcrDiscogsAjax.strings.downloading)
                   .prop('disabled', true);
            
            $statusDiv.html('<div class="pcr-notice info"><p>' + pcrDiscogsAjax.strings.downloading + '</p></div>');
            
            // Make AJAX call
            $.ajax({
                url: pcrDiscogsAjax.ajaxurl,
                method: 'POST',
                data: {
                    action: 'pcr_download_discogs_images',
                    product_id: productId,
                    nonce: pcrDiscogsAjax.nonce
                },
                timeout: 60000 // 60 seconds for image downloads
            })
            .done(function(response) {
                if (response.success) {
                    const data = response.data;
                    const successMsg = data.message + 
                        '<br><strong>Release:</strong> ' + data.release_title +
                        '<br><strong>Images:</strong> ' + data.images_downloaded +
                        (data.featured_image_set ? '<br><strong>Featured image:</strong> Set' : '');
                    
                    $statusDiv.html('<div class="pcr-notice success"><p>' + successMsg + '</p></div>');
                    
                    // Show success on button temporarily
                    $button.html('✅ ' + pcrDiscogsAjax.strings.success);
                    
                    // Reload the page after 2 seconds to show new images
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                } else {
                    $statusDiv.html('<div class="pcr-notice error"><p>' + 
                        pcrDiscogsAjax.strings.error + ' ' + response.data + '</p></div>');
                }
            })
            .fail(function(xhr) {
                let errorMsg = pcrDiscogsAjax.strings.error + ' ';
                
                if (xhr.status === 0) {
                    errorMsg += 'Network error or timeout';
                } else if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMsg += xhr.responseJSON.data;
                } else {
                    errorMsg += 'HTTP ' + xhr.status;
                }
                
                $statusDiv.html('<div class="pcr-notice error"><p>' + errorMsg + '</p></div>');
            })
            .always(function() {
                // Reset button after 3 seconds if not reloading
                setTimeout(function() {
                    if (!$button.html().includes('✅')) {
                        $button.html(originalText).prop('disabled', false);
                    }
                }, 3000);
            });
        },

        /**
         * Save settings
         */
        saveSettings: function(e) {
            const $form = $(this);
            const $submitButton = $form.find('input[type="submit"]');
            const originalValue = $submitButton.val();
            
            $submitButton.val('Saving...').prop('disabled', true);
            
            // Let the form submit normally, but show feedback
            setTimeout(function() {
                $submitButton.val(originalValue).prop('disabled', false);
            }, 1500);
        },

        /**
         * Check API status
         */
        checkApiStatus: function() {
            // The status is already shown in PHP, just add some styling
            $('.pcr-api-status').show();
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
        }
    };

    // Make available globally
    window.PCRDiscogsAdmin = PCRDiscogsAdmin;

})(jQuery);
