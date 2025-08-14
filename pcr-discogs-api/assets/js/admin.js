/**
 * PCR Discogs API - Admin JavaScript (UPDATED)
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
     * Main Admin Object (UPDATED)
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
         * Bind event handlers (UPDATED - add categories button handler)
         */
        bindEvents: function() {
            // Test API connection button
            $(document).on('click', '.pcr-test-api', this.testApiConnection);
            
            // Download images button (existing)
            $(document).on('click', '.pcr-download-images', this.downloadImages);
            
            // Download record data button
            $(document).on('click', '.pcr-download-record-data', this.downloadRecordData);
            
            // NEW: Set categories button
            $(document).on('click', '.pcr-set-categories', this.setCategoriesFromDiscogs);
            
            // Save settings form
            $(document).on('submit', '#pcr-settings-form', this.saveSettings);
        },

        /**
         * Test API connection (existing method)
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
         * Download images from Discogs (existing method)
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
         * NEW: Download record data from Discogs
         */
        downloadRecordData: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const productId = $button.data('product-id');
            const $statusDiv = $('.pcr-record-data-status');
            const originalText = $button.text();
            
            // Show loading state
            $button.html('<span class="pcr-loading"></span> Downloading record data...')
                   .prop('disabled', true);
            
            $statusDiv.html('<div class="pcr-notice info"><p>Downloading record data from Discogs...</p></div>');
            
            // Make AJAX call
            $.ajax({
                url: pcrDiscogsAjax.ajaxurl,
                method: 'POST',
                data: {
                    action: 'pcr_download_record_data',
                    product_id: productId,
                    nonce: pcrDiscogsAjax.nonce
                },
                timeout: 30000 // 30 seconds for data download
            })
            .done(function(response) {
                if (response.success) {
                    const data = response.data;
                    let successMsg = data.message + '<br><strong>Release:</strong> ' + data.release_title;
                    
                    if (data.updated_fields && data.updated_fields.length > 0) {
                        successMsg += '<br><strong>Updated:</strong><br>';
                        data.updated_fields.forEach(function(field) {
                            successMsg += '• ' + field + '<br>';
                        });
                    }
                    
                    if (data.errors && data.errors.length > 0) {
                        successMsg += '<br><strong>Warnings:</strong><br>';
                        data.errors.forEach(function(error) {
                            successMsg += '• ' + error + '<br>';
                        });
                    }
                    
                    $statusDiv.html('<div class="pcr-notice success"><p>' + successMsg + '</p></div>');
                    
                    // Show success on button temporarily
                    $button.html('✅ Record Data Downloaded');
                    
                    // Reload the page after 3 seconds to show updated fields
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    $statusDiv.html('<div class="pcr-notice error"><p>Error downloading record data: ' + response.data + '</p></div>');
                }
            })
            .fail(function(xhr) {
                let errorMsg = 'Error downloading record data: ';
                
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
                // Reset button after 5 seconds if not reloading
                setTimeout(function() {
                    if (!$button.html().includes('✅')) {
                        $button.html(originalText).prop('disabled', false);
                    }
                }, 5000);
            });
        },

        /**
         * NEW: Set categories from Discogs data
         */
        setCategoriesFromDiscogs: function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const productId = $button.data('product-id');
            const $statusDiv = $('.pcr-categories-status');
            const originalText = $button.text();
            
            // Show loading state
            $button.html('<span class="pcr-loading"></span> Setting categories...')
                   .prop('disabled', true);
            
            $statusDiv.html('<div class="pcr-notice info"><p>Creating categories from Discogs genres...</p></div>');
            
            // Make AJAX call
            $.ajax({
                url: pcrDiscogsAjax.ajaxurl,
                method: 'POST',
                data: {
                    action: 'pcr_set_categories_from_discogs',
                    product_id: productId,
                    nonce: pcrDiscogsAjax.nonce
                },
                timeout: 30000 // 30 seconds for category processing
            })
            .done(function(response) {
                if (response.success) {
                    const data = response.data;
                    let successMsg = data.message;
                    
                    if (data.created_categories && data.created_categories.length > 0) {
                        successMsg += '<br><strong>Created categories:</strong><br>';
                        data.created_categories.forEach(function(category) {
                            successMsg += '• ' + category + '<br>';
                        });
                    }
                    
                    if (data.assigned_categories && data.assigned_categories.length > 0) {
                        successMsg += '<br><strong>Assigned categories:</strong><br>';
                        data.assigned_categories.forEach(function(category) {
                            successMsg += '• ' + category + '<br>';
                        });
                    }
                    
                    if (data.errors && data.errors.length > 0) {
                        successMsg += '<br><strong>Warnings:</strong><br>';
                        data.errors.forEach(function(error) {
                            successMsg += '• ' + error + '<br>';
                        });
                    }
                    
                    $statusDiv.html('<div class="pcr-notice success"><p>' + successMsg + '</p></div>');
                    
                    // Show success on button temporarily
                    $button.html('✅ Categories Set');
                    
                    // Reload the page after 3 seconds to show updated categories
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                } else {
                    $statusDiv.html('<div class="pcr-notice error"><p>Error setting categories: ' + response.data + '</p></div>');
                }
            })
            .fail(function(xhr) {
                let errorMsg = 'Error setting categories: ';
                
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
                // Reset button after 5 seconds if not reloading
                setTimeout(function() {
                    if (!$button.html().includes('✅')) {
                        $button.html(originalText).prop('disabled', false);
                    }
                }, 5000);
            });
        },

        /**
         * Save settings (existing method)
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
         * Check API status (existing method)
         */
        checkApiStatus: function() {
            // The status is already shown in PHP, just add some styling
            $('.pcr-api-status').show();
        },

        /**
         * Initialize tooltips (existing method)
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
         * Show admin notice (existing method)
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
