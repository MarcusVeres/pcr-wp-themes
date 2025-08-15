/**
 * PCR Discogs API - Batch Processing JavaScript
 * Add this as assets/js/batch.js or include in your main admin.js
 */

(function($) {
    'use strict';

    const PCRBatchProcessor = {
        candidates: [],
        currentIndex: 0,
        isProcessing: false,
        isPaused: false,
        processingOptions: {},
        results: {
            total: 0,
            processed: 0,
            successful: 0,
            failed: 0,
            skipped: 0,
            details: []
        },

        init: function() {
            this.bindEvents();
            this.resetState();
        },

        bindEvents: function() {
            // Step 1: Scan for candidates
            $(document).on('click', '#pcr-scan-products', this.scanProducts.bind(this));
            
            // Step 2: Start processing
            $(document).on('click', '#pcr-start-processing', this.startProcessing.bind(this));
            
            // Step 3: Pause/Resume
            $(document).on('click', '#pcr-pause-processing', this.pauseProcessing.bind(this));
            
            // Step 4: Start over
            $(document).on('click', '#pcr-start-over', this.startOver.bind(this));
        },

        resetState: function() {
            this.candidates = [];
            this.currentIndex = 0;
            this.isProcessing = false;
            this.isPaused = false;
            this.results = {
                total: 0,
                processed: 0,
                successful: 0,
                failed: 0,
                skipped: 0,
                details: []
            };
        },

        scanProducts: function() {
            const $button = $('#pcr-scan-products');
            const $results = $('#pcr-scan-results');
            
            $button.html('<span class="pcr-loading"></span> Scanning...').prop('disabled', true);
            $results.html('');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'pcr_get_batch_candidates',
                    _ajax_nonce: pcrBatchAjax.nonce
                }
            })
            .done((response) => {
                if (response.success) {
                    this.candidates = response.data;
                    this.displayCandidates();
                    this.showStep('configure');
                } else {
                    $results.html('<div class="notice notice-error"><p>Error scanning products: ' + response.data + '</p></div>');
                }
            })
            .fail((xhr) => {
                $results.html('<div class="notice notice-error"><p>Network error while scanning products</p></div>');
            })
            .always(() => {
                $button.html('Scan for Products to Process').prop('disabled', false);
            });
        },

        displayCandidates: function() {
            const $results = $('#pcr-scan-results');
            
            if (this.candidates.length === 0) {
                $results.html('<div class="notice notice-warning"><p>No products found with Discogs Release IDs.</p></div>');
                return;
            }

            let html = `
                <div class="notice notice-success">
                    <p><strong>Found ${this.candidates.length} product(s) ready for processing:</strong></p>
                </div>
                <div class="pcr-candidate-list">
            `;

            this.candidates.forEach((candidate, index) => {
                const photoStatus = candidate.search_photos ? '🖼️ Images' : '🚫 No Images';
                html += `
                    <div class="pcr-candidate-item">
                        <strong>${candidate.title}</strong> 
                        <span style="color: #666;">(ID: ${candidate.id}, Release: ${candidate.release_id}, ${photoStatus})</span>
                    </div>
                `;
            });

            html += '</div>';
            $results.html(html);
        },

        showStep: function(step) {
            $('.pcr-batch-step').hide();
            $('#pcr-step-' + step).show();
        },

        /**
         * UPDATED: startProcessing method with explicit boolean conversion
         * Replace this method in your batch.js file
         */
        startProcessing: function() {
            if (this.candidates.length === 0) {
                alert('No products to process. Please scan first.');
                return;
            }

            // Get processing options with explicit boolean conversion
            const processImages = $('#pcr-process-images').is(':checked');
            const processData = $('#pcr-process-data').is(':checked');
            const processCategories = $('#pcr-process-categories').is(':checked');
            
            this.processingOptions = {
                process_images: processImages,
                process_data: processData,
                process_categories: processCategories,
                delay: parseInt($('#pcr-processing-speed').val())
            };

            // DEBUG: Log what we're sending
            console.log('PCR BATCH DEBUG - Processing options:', this.processingOptions);
            console.log('PCR BATCH DEBUG - Images checkbox checked:', processImages);
            console.log('PCR BATCH DEBUG - Data checkbox checked:', processData);
            console.log('PCR BATCH DEBUG - Categories checkbox checked:', processCategories);

            // Initialize results
            this.results.total = this.candidates.length;
            this.currentIndex = 0;
            this.isProcessing = true;
            this.isPaused = false;

            // Show progress step
            this.showStep('progress');
            this.updateProgress();
            
            // Clear log
            $('#pcr-processing-log').html('');
            
            // Start processing
            this.logMessage('Starting batch processing...', 'info');
            this.logMessage(`Options: Images=${processImages}, Data=${processData}, Categories=${processCategories}`, 'info');
            $('#pcr-pause-processing').show();
            
            this.processNextItem();
        },

        processNextItem: function() {
            if (!this.isProcessing || this.isPaused) {
                return;
            }

            if (this.currentIndex >= this.candidates.length) {
                this.completeProcessing();
                return;
            }

            const candidate = this.candidates[this.currentIndex];
            
            // Update current item display
            $('#pcr-current-product').text(candidate.title);
            
            this.logMessage(`Processing: ${candidate.title} (${this.currentIndex + 1}/${this.candidates.length})`, 'info');

            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'pcr_process_batch_item',
                    product_id: candidate.id,
                    options: this.processingOptions,
                    _ajax_nonce: pcrBatchAjax.nonce
                },
                timeout: 60000
            })
            .done((response) => {
                this.handleItemResult(response);
            })
            .fail((xhr) => {
                this.handleItemResult({
                    success: false,
                    product_id: candidate.id,
                    product_title: candidate.title,
                    errors: ['Network error: ' + xhr.status]
                });
            })
            .always(() => {
                this.currentIndex++;
                this.updateProgress();
                
                // Rate limiting delay
                if (this.isProcessing && !this.isPaused && this.currentIndex < this.candidates.length) {
                    setTimeout(() => {
                        this.processNextItem();
                    }, this.processingOptions.delay);
                }
            });
        },

        handleItemResult: function(result) {
            this.results.processed++;
            this.results.details.push(result);

            if (result.success) {
                this.results.successful++;
                this.logMessage(`✅ ${result.product_title}: ${result.operations.join(', ')}`, 'success');
            } else {
                this.results.failed++;
                this.logMessage(`❌ ${result.product_title}: ${result.errors.join(', ')}`, 'error');
            }
        },

        updateProgress: function() {
            const percentage = this.results.total > 0 ? (this.results.processed / this.results.total) * 100 : 0;
            
            $('.pcr-progress-fill').css('width', percentage + '%');
            $('.pcr-progress-text').text(`${this.results.processed} / ${this.results.total} processed`);
        },

        pauseProcessing: function() {
            if (this.isPaused) {
                // Resume
                this.isPaused = false;
                $('#pcr-pause-processing').text('Pause Processing');
                this.logMessage('Resuming processing...', 'info');
                this.processNextItem();
            } else {
                // Pause
                this.isPaused = true;
                $('#pcr-pause-processing').text('Resume Processing');
                this.logMessage('Processing paused by user', 'info');
            }
        },

        completeProcessing: function() {
            this.isProcessing = false;
            this.isPaused = false;
            
            $('#pcr-current-product').text('Complete!');
            $('#pcr-pause-processing').hide();
            
            this.logMessage('Batch processing complete!', 'success');
            
            // Show results
            this.displayFinalResults();
            this.showStep('results');
        },

        displayFinalResults: function() {
            const $results = $('#pcr-final-results');
            
            let html = `
                <div class="notice notice-success">
                    <h3>🎉 Batch Processing Complete!</h3>
                    <p>
                        <strong>Total Products:</strong> ${this.results.total}<br>
                        <strong>Successful:</strong> ${this.results.successful}<br>
                        <strong>Failed:</strong> ${this.results.failed}<br>
                        <strong>Success Rate:</strong> ${Math.round((this.results.successful / this.results.total) * 100)}%
                    </p>
                </div>
            `;

            if (this.results.failed > 0) {
                html += '<h4>❌ Failed Products:</h4><ul>';
                this.results.details.forEach(detail => {
                    if (!detail.success) {
                        html += `<li><strong>${detail.product_title}</strong>: ${detail.errors.join(', ')}</li>`;
                    }
                });
                html += '</ul>';
            }

            if (this.results.successful > 0) {
                html += '<h4>✅ Successful Products:</h4><ul>';
                this.results.details.forEach(detail => {
                    if (detail.success) {
                        html += `<li><strong>${detail.product_title}</strong>: ${detail.operations.join(', ')}</li>`;
                    }
                });
                html += '</ul>';
            }

            $results.html(html);
        },

        logMessage: function(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString();
            const $log = $('#pcr-processing-log');
            
            const logEntry = `
                <div class="pcr-log-entry ${type}">
                    <span class="timestamp">[${timestamp}]</span> ${message}
                </div>
            `;
            
            $log.append(logEntry);
            $log.scrollTop($log[0].scrollHeight);
        },

        startOver: function() {
            this.resetState();
            this.showStep('scan');
            $('#pcr-scan-results').html('');
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.pcr-batch-admin').length > 0) {
            PCRBatchProcessor.init();
        }
    });

    // Make available globally for debugging
    window.PCRBatchProcessor = PCRBatchProcessor;

})(jQuery);

// REQUIREMENT :: Add this to your PHP localization
/*
wp_localize_script('pcr-batch-js', 'pcrBatchAjax', array(
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('pcr_batch_processing'),
    'strings' => array(
        'scanning' => __('Scanning...', 'pcr-discogs-api'),
        'processing' => __('Processing...', 'pcr-discogs-api'),
        'complete' => __('Complete!', 'pcr-discogs-api')
    )
));
*/
// Done on 2025-08-14
