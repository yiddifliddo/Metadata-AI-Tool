<?php
/**
 * Plugin Name: ABS AI Meta Generator
 * Plugin URI: https://americanbiotechsupply.com
 * Description: AI-powered meta description and focus keyword generator for product pages. Scans the live product URL and generates SEO-optimized 160 character meta descriptions and focus keywords for Yoast.
 * Version: 1.2.0
 * Author: Standex Scientific
 * Author URI: https://americanbiotechsupply.com
 * Text Domain: abs-ai-meta
 */

if (!defined('ABSPATH')) {
    exit;
}

class ABS_AI_Meta_Generator {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_abs_generate_meta', [$this, 'ajax_generate_meta']);
        add_action('wp_ajax_abs_save_yoast_meta', [$this, 'ajax_save_yoast_meta']);
        add_action('wp_ajax_abs_scan_missing_meta', [$this, 'ajax_scan_missing_meta']);
        add_action('wp_ajax_abs_batch_generate_meta', [$this, 'ajax_batch_generate_meta']);
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
    }
    
    /**
     * Add settings page under Settings menu
     */
    public function add_settings_page() {
        add_options_page(
            'AI Meta Generator Settings',
            'AI Meta Generator',
            'manage_options',
            'abs-ai-meta-settings',
            [$this, 'render_settings_page']
        );
        
        // Add batch scanner page under Products menu
        add_submenu_page(
            'edit.php?post_type=product',
            'Batch Meta Generator',
            'Batch Meta Generator',
            'manage_options',
            'abs-batch-meta-generator',
            [$this, 'render_batch_page']
        );
    }
    
    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('abs_ai_meta_settings', 'abs_ai_meta_api_key');
        register_setting('abs_ai_meta_settings', 'abs_ai_meta_api_provider');
        register_setting('abs_ai_meta_settings', 'abs_ai_meta_custom_prompt');
        
        add_settings_section(
            'abs_ai_meta_main',
            'API Configuration',
            [$this, 'settings_section_callback'],
            'abs-ai-meta-settings'
        );
        
        add_settings_field(
            'abs_ai_meta_api_provider',
            'AI Provider',
            [$this, 'provider_field_callback'],
            'abs-ai-meta-settings',
            'abs_ai_meta_main'
        );
        
        add_settings_field(
            'abs_ai_meta_api_key',
            'API Key',
            [$this, 'api_key_field_callback'],
            'abs-ai-meta-settings',
            'abs_ai_meta_main'
        );
        
        add_settings_field(
            'abs_ai_meta_custom_prompt',
            'Custom Instructions (Optional)',
            [$this, 'custom_prompt_field_callback'],
            'abs-ai-meta-settings',
            'abs_ai_meta_main'
        );
    }
    
    public function settings_section_callback() {
        echo '<p>Configure your AI provider and API key for generating meta descriptions.</p>';
    }
    
    public function provider_field_callback() {
        $provider = get_option('abs_ai_meta_api_provider', 'openai');
        ?>
        <select name="abs_ai_meta_api_provider" id="abs_ai_meta_api_provider">
            <option value="openai" <?php selected($provider, 'openai'); ?>>OpenAI (GPT-4)</option>
            <option value="anthropic" <?php selected($provider, 'anthropic'); ?>>Anthropic (Claude)</option>
        </select>
        <?php
    }
    
    public function api_key_field_callback() {
        $api_key = get_option('abs_ai_meta_api_key', '');
        ?>
        <input type="password" 
               name="abs_ai_meta_api_key" 
               id="abs_ai_meta_api_key" 
               value="<?php echo esc_attr($api_key); ?>" 
               class="regular-text"
               autocomplete="off">
        <p class="description">Your OpenAI or Anthropic API key.</p>
        <?php
    }
    
    public function custom_prompt_field_callback() {
        $custom_prompt = get_option('abs_ai_meta_custom_prompt', '');
        ?>
        <textarea name="abs_ai_meta_custom_prompt" 
                  id="abs_ai_meta_custom_prompt" 
                  rows="4" 
                  class="large-text"><?php echo esc_textarea($custom_prompt); ?></textarea>
        <p class="description">Optional: Add custom instructions for the AI (e.g., "Focus on CDC compliance and temperature control features").</p>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>AI Meta Generator Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('abs_ai_meta_settings');
                do_settings_sections('abs-ai-meta-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render batch processing page
     */
    public function render_batch_page() {
        $api_key = get_option('abs_ai_meta_api_key', '');
        ?>
        <div class="wrap">
            <h1>Batch Meta Description Generator</h1>
            
            <?php if (empty($api_key)) : ?>
                <div class="notice notice-error">
                    <p>⚠️ Please configure your API key in <a href="<?php echo admin_url('options-general.php?page=abs-ai-meta-settings'); ?>">Settings → AI Meta Generator</a> before using the batch tool.</p>
                </div>
            <?php else : ?>
            
            <div class="card" style="max-width: 100%; padding: 20px; margin-top: 20px;">
                <h2>Step 1: Scan for Products Missing SEO Data</h2>
                <p>Find published products missing a Yoast meta description and/or focus keyword.</p>

                <div style="margin-bottom: 15px;">
                    <label style="font-weight: bold;">Scan for products missing:</label><br>
                    <label style="margin-right: 15px;"><input type="radio" name="abs-scan-mode" value="either" checked> Meta description OR focus keyword</label>
                    <label style="margin-right: 15px;"><input type="radio" name="abs-scan-mode" value="meta"> Meta description only</label>
                    <label style="margin-right: 15px;"><input type="radio" name="abs-scan-mode" value="focus"> Focus keyword only</label>
                    <label><input type="radio" name="abs-scan-mode" value="both"> Missing BOTH</label>
                </div>

                <button type="button" id="abs-scan-btn" class="button button-primary button-large">
                    <span class="dashicons dashicons-search" style="margin-top: 4px;"></span>
                    Scan for Missing SEO Data
                </button>

                <div id="abs-scan-loading" style="display: none; margin-top: 15px;">
                    <span class="spinner is-active" style="float: left;"></span>
                    <span style="margin-left: 10px;">Scanning products...</span>
                </div>
            </div>
            
            <div id="abs-scan-results" class="card" style="max-width: 100%; padding: 20px; margin-top: 20px; display: none;">
                <h2>Step 2: Review & Process</h2>
                
                <div id="abs-results-summary" style="margin-bottom: 20px;"></div>
                
                <div style="margin-bottom: 15px;">
                    <label>
                        <input type="checkbox" id="abs-select-all" checked>
                        Select All
                    </label>
                    <span style="margin-left: 20px;">
                        Batch Size:
                        <select id="abs-batch-size">
                            <option value="1">1 at a time (safest)</option>
                            <option value="3" selected>3 at a time</option>
                            <option value="5">5 at a time</option>
                            <option value="10">10 at a time</option>
                        </select>
                    </span>
                    <span style="margin-left: 20px;">
                        Delay between batches:
                        <select id="abs-batch-delay">
                            <option value="1000">1 second</option>
                            <option value="2000" selected>2 seconds</option>
                            <option value="3000">3 seconds</option>
                            <option value="5000">5 seconds</option>
                        </select>
                    </span>
                </div>

                <div style="margin-bottom: 15px; padding: 10px; background: #f0f6fc; border-left: 4px solid #0073aa;">
                    <label style="font-weight: bold;">Update mode:</label><br>
                    <label style="margin-right: 15px;"><input type="radio" name="abs-update-mode" value="both" checked> Meta description + focus keyword</label>
                    <label style="margin-right: 15px;"><input type="radio" name="abs-update-mode" value="meta"> Meta description only</label>
                    <label><input type="radio" name="abs-update-mode" value="focus"> Focus keyword only</label>
                    <br>
                    <label style="margin-top: 8px; display: inline-block;"><input type="checkbox" id="abs-overwrite"> Overwrite existing values (by default only empty fields are filled)</label>
                </div>
                
                <div id="abs-products-list" style="max-height: 400px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; background: #f9f9f9;"></div>
                
                <div style="margin-top: 20px;">
                    <button type="button" id="abs-start-batch-btn" class="button button-primary button-large">
                        <span class="dashicons dashicons-admin-generic" style="margin-top: 4px;"></span>
                        Start Batch Processing
                    </button>
                    <button type="button" id="abs-stop-batch-btn" class="button button-large" style="display: none;">
                        <span class="dashicons dashicons-no" style="margin-top: 4px;"></span>
                        Stop Processing
                    </button>
                </div>
            </div>
            
            <div id="abs-progress-section" class="card" style="max-width: 100%; padding: 20px; margin-top: 20px; display: none;">
                <h2>Processing Progress</h2>
                
                <div style="margin-bottom: 15px;">
                    <div id="abs-progress-bar-container" style="width: 100%; height: 30px; background: #e0e0e0; border-radius: 5px; overflow: hidden;">
                        <div id="abs-progress-bar" style="width: 0%; height: 100%; background: #0073aa; transition: width 0.3s;"></div>
                    </div>
                    <p id="abs-progress-text" style="margin-top: 10px;">0 of 0 processed</p>
                </div>
                
                <div id="abs-progress-log" style="max-height: 300px; overflow-y: auto; border: 1px solid #ccc; padding: 10px; background: #1e1e1e; color: #fff; font-family: monospace; font-size: 12px;"></div>
            </div>
            
            <div id="abs-complete-section" class="card" style="max-width: 100%; padding: 20px; margin-top: 20px; display: none; background: #d4edda; border-color: #c3e6cb;">
                <h2 style="color: #155724;">✓ Batch Processing Complete!</h2>
                <p id="abs-complete-summary"></p>
                <button type="button" id="abs-restart-btn" class="button button-primary">
                    Start New Scan
                </button>
            </div>
            
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            var products = [];
            var processing = false;
            var stopRequested = false;
            var processed = 0;
            var successful = 0;
            var failed = 0;
            
            // Scan for missing meta
            $('#abs-scan-btn').on('click', function() {
                var $btn = $(this);
                var scanMode = $('input[name="abs-scan-mode"]:checked').val() || 'either';
                $btn.prop('disabled', true);
                $('#abs-scan-loading').show();
                $('#abs-scan-results').hide();

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'abs_scan_missing_meta',
                        nonce: '<?php echo wp_create_nonce('abs_batch_nonce'); ?>',
                        scan_mode: scanMode
                    },
                    success: function(response) {
                        $btn.prop('disabled', false);
                        $('#abs-scan-loading').hide();

                        if (response.success) {
                            products = response.data.products;
                            displayResults(products);
                        } else {
                            alert('Error: ' + response.data.message);
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false);
                        $('#abs-scan-loading').hide();
                        alert('Request failed');
                    }
                });
            });
            
            function displayResults(products) {
                var $results = $('#abs-scan-results');
                var $summary = $('#abs-results-summary');
                var $list = $('#abs-products-list');
                
                if (products.length === 0) {
                    $summary.html('<p style="color: #155724; font-weight: bold;">✓ All products have the requested SEO data!</p>');
                    $list.hide();
                    $('#abs-start-batch-btn').hide();
                } else {
                    $summary.html('<p><strong>' + products.length + ' products</strong> found missing SEO data.</p>');

                    var html = '<table style="width: 100%; border-collapse: collapse;">';
                    html += '<thead><tr style="background: #fff;"><th style="padding: 8px; text-align: left; border-bottom: 2px solid #ccc;"><input type="checkbox" id="abs-header-select-all" checked></th><th style="padding: 8px; text-align: left; border-bottom: 2px solid #ccc;">Product</th><th style="padding: 8px; text-align: left; border-bottom: 2px solid #ccc;">Missing</th><th style="padding: 8px; text-align: left; border-bottom: 2px solid #ccc;">Status</th></tr></thead>';
                    html += '<tbody>';

                    products.forEach(function(product) {
                        var missingBadges = '';
                        if (product.missing_desc) {
                            missingBadges += '<span style="background:#fde2e4;color:#b02a37;padding:2px 6px;border-radius:3px;margin-right:4px;font-size:11px;">Meta</span>';
                        }
                        if (product.missing_focus) {
                            missingBadges += '<span style="background:#fff3cd;color:#856404;padding:2px 6px;border-radius:3px;font-size:11px;">Focus</span>';
                        }
                        html += '<tr data-id="' + product.id + '" style="border-bottom: 1px solid #ddd;">';
                        html += '<td style="padding: 8px;"><input type="checkbox" class="abs-product-checkbox" value="' + product.id + '" checked></td>';
                        html += '<td style="padding: 8px;"><a href="' + product.edit_url + '" target="_blank">' + product.title + '</a><br><small style="color: #666;">' + product.url + '</small></td>';
                        html += '<td style="padding: 8px;">' + missingBadges + '</td>';
                        html += '<td style="padding: 8px;" class="abs-status"><span style="color: #666;">Pending</span></td>';
                        html += '</tr>';
                    });

                    html += '</tbody></table>';
                    $list.html(html).show();
                    $('#abs-start-batch-btn').show();
                }
                
                $results.show();
            }
            
            // Select all checkbox
            $(document).on('change', '#abs-select-all, #abs-header-select-all', function() {
                $('.abs-product-checkbox').prop('checked', $(this).is(':checked'));
                $('#abs-select-all, #abs-header-select-all').prop('checked', $(this).is(':checked'));
            });
            
            // Start batch processing
            $('#abs-start-batch-btn').on('click', function() {
                var selected = $('.abs-product-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();
                
                if (selected.length === 0) {
                    alert('Please select at least one product');
                    return;
                }
                
                processing = true;
                stopRequested = false;
                processed = 0;
                successful = 0;
                failed = 0;
                
                $('#abs-start-batch-btn').hide();
                $('#abs-stop-batch-btn').show();
                $('#abs-progress-section').show();
                $('#abs-complete-section').hide();
                $('#abs-progress-log').html('');
                
                var batchSize = parseInt($('#abs-batch-size').val());
                var delay = parseInt($('#abs-batch-delay').val());
                
                processBatch(selected, 0, batchSize, delay);
            });
            
            // Stop processing
            $('#abs-stop-batch-btn').on('click', function() {
                stopRequested = true;
                $(this).text('Stopping...').prop('disabled', true);
                logMessage('Stop requested. Finishing current batch...', 'warning');
            });
            
            function processBatch(productIds, startIndex, batchSize, delay) {
                if (stopRequested || startIndex >= productIds.length) {
                    finishProcessing();
                    return;
                }
                
                var batch = productIds.slice(startIndex, startIndex + batchSize);
                var batchPromises = batch.map(function(productId) {
                    return processProduct(productId);
                });
                
                Promise.all(batchPromises).then(function() {
                    processed += batch.length;
                    updateProgress(processed, productIds.length);
                    
                    if (startIndex + batchSize < productIds.length && !stopRequested) {
                        logMessage('Waiting ' + (delay/1000) + 's before next batch...', 'info');
                        setTimeout(function() {
                            processBatch(productIds, startIndex + batchSize, batchSize, delay);
                        }, delay);
                    } else {
                        finishProcessing();
                    }
                });
            }
            
            function processProduct(productId) {
                return new Promise(function(resolve) {
                    var $row = $('tr[data-id="' + productId + '"]');
                    var productTitle = $row.find('a').text();
                    
                    $row.find('.abs-status').html('<span style="color: #0073aa;">Processing...</span>');
                    logMessage('Processing: ' + productTitle, 'info');
                    
                    var updateMode = $('input[name="abs-update-mode"]:checked').val() || 'both';
                    var overwrite = $('#abs-overwrite').is(':checked') ? '1' : '0';

                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'abs_batch_generate_meta',
                            nonce: '<?php echo wp_create_nonce('abs_batch_nonce'); ?>',
                            post_id: productId,
                            update_mode: updateMode,
                            overwrite: overwrite
                        },
                        success: function(response) {
                            if (response.success) {
                                successful++;
                                var savedFields = (response.data.savedFields || []).join(', ') || 'none (existing values kept)';
                                var focusTxt = response.data.focusKeyword ? ' | Focus: "' + response.data.focusKeyword + '"' : '';
                                $row.find('.abs-status').html('<span style="color: #155724;">✓ ' + savedFields + ' (' + response.data.charCount + ' chars)</span>');
                                logMessage('✓ Success: ' + productTitle + ' - Saved: ' + savedFields + focusTxt, 'success');
                            } else {
                                failed++;
                                $row.find('.abs-status').html('<span style="color: #dc3545;">✗ ' + response.data.message + '</span>');
                                logMessage('✗ Failed: ' + productTitle + ' - ' + response.data.message, 'error');
                            }
                            resolve();
                        },
                        error: function() {
                            failed++;
                            $row.find('.abs-status').html('<span style="color: #dc3545;">✗ Request failed</span>');
                            logMessage('✗ Failed: ' + productTitle + ' - Request failed', 'error');
                            resolve();
                        }
                    });
                });
            }
            
            function updateProgress(current, total) {
                var percent = Math.round((current / total) * 100);
                $('#abs-progress-bar').css('width', percent + '%');
                $('#abs-progress-text').text(current + ' of ' + total + ' processed (' + successful + ' successful, ' + failed + ' failed)');
            }
            
            function logMessage(message, type) {
                var colors = {
                    'info': '#87ceeb',
                    'success': '#90ee90',
                    'error': '#ff6b6b',
                    'warning': '#ffa500'
                };
                var time = new Date().toLocaleTimeString();
                var $log = $('#abs-progress-log');
                $log.append('<div style="color: ' + colors[type] + ';">[' + time + '] ' + message + '</div>');
                $log.scrollTop($log[0].scrollHeight);
            }
            
            function finishProcessing() {
                processing = false;
                $('#abs-stop-batch-btn').hide().text('Stop Processing').prop('disabled', false);
                $('#abs-start-batch-btn').show();
                
                $('#abs-complete-section').show();
                $('#abs-complete-summary').html(
                    '<strong>' + processed + '</strong> products processed<br>' +
                    '<span style="color: #155724;">✓ ' + successful + ' successful</span><br>' +
                    (failed > 0 ? '<span style="color: #dc3545;">✗ ' + failed + ' failed</span>' : '')
                );
                
                logMessage('=== Batch processing complete ===', 'info');
            }
            
            // Restart
            $('#abs-restart-btn').on('click', function() {
                $('#abs-scan-results').hide();
                $('#abs-progress-section').hide();
                $('#abs-complete-section').hide();
                $('#abs-scan-btn').trigger('click');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Add meta box to product edit screen
     */
    public function add_meta_box() {
        add_meta_box(
            'abs_ai_meta_generator',
            'AI Meta Description Generator',
            [$this, 'render_meta_box'],
            'product',
            'side',
            'high'
        );
    }
    
    /**
     * Render the meta box content
     */
    public function render_meta_box($post) {
        $api_key = get_option('abs_ai_meta_api_key', '');
        
        if (empty($api_key)) {
            echo '<p style="color: #d63638;">⚠️ Please configure your API key in <a href="' . admin_url('options-general.php?page=abs-ai-meta-settings') . '">Settings → AI Meta Generator</a></p>';
            return;
        }
        ?>
        <div id="abs-ai-meta-container">
            <p class="description">Scans the live product URL and generates a 160-character SEO meta description plus a focus keyword.</p>

            <div style="margin: 10px 0;">
                <button type="button" id="abs-generate-meta-btn" class="button button-primary" style="width: 100%;">
                    <span class="dashicons dashicons-admin-generic" style="margin-top: 3px;"></span>
                    Scan URL &amp; Generate
                </button>
            </div>

            <div id="abs-meta-preview" style="display: none; margin-top: 10px;">
                <label><strong>Generated Meta Description:</strong></label>
                <textarea id="abs-generated-meta" rows="4" style="width: 100%; margin-top: 5px;"></textarea>
                <p id="abs-meta-char-count" style="margin: 5px 0; font-size: 11px; color: #666;"></p>

                <label style="margin-top: 10px; display: block;"><strong>Focus Keyword:</strong></label>
                <input type="text" id="abs-generated-focus" style="width: 100%; margin-top: 5px;" />
                <p style="margin: 5px 0; font-size: 11px; color: #666;">You can edit both fields before applying.</p>

                <div style="display: flex; flex-direction: column; gap: 5px; margin-top: 10px;">
                    <button type="button" id="abs-apply-both-btn" class="button button-primary" style="width: 100%;">
                        Apply Both to Yoast
                    </button>
                    <div style="display: flex; gap: 5px;">
                        <button type="button" id="abs-apply-meta-btn" class="button" style="flex: 1;">
                            Apply Meta Only
                        </button>
                        <button type="button" id="abs-apply-focus-btn" class="button" style="flex: 1;">
                            Apply Focus Only
                        </button>
                    </div>
                    <button type="button" id="abs-regenerate-meta-btn" class="button" style="width: 100%;">
                        Regenerate
                    </button>
                </div>
            </div>

            <div id="abs-meta-loading" style="display: none; text-align: center; padding: 20px;">
                <span class="spinner is-active" style="float: none;"></span>
                <p>Scanning live URL...</p>
            </div>

            <div id="abs-meta-error" style="display: none; color: #d63638; margin-top: 10px;"></div>
        </div>
        <?php
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        global $post;
        
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        
        if (!$post || $post->post_type !== 'product') {
            return;
        }
        
        wp_enqueue_script(
            'abs-ai-meta-admin',
            plugin_dir_url(__FILE__) . 'admin.js',
            ['jquery'],
            '1.2.0',
            true
        );
        
        wp_localize_script('abs-ai-meta-admin', 'absAiMeta', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('abs_generate_meta_nonce'),
            'postId' => $post->ID,
        ]);
    }
    
    /**
     * AJAX handler for generating meta description
     */
    public function ajax_generate_meta() {
        check_ajax_referer('abs_generate_meta_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(['message' => 'Product not found']);
        }
        
        // Get the live URL
        $url = get_permalink($post_id);
        
        if (!$url) {
            wp_send_json_error(['message' => 'Could not get product URL']);
        }
        
        // Fetch and parse the live page content
        $page_content = $this->fetch_and_parse_url($url);
        
        if (is_wp_error($page_content)) {
            wp_send_json_error(['message' => $page_content->get_error_message()]);
        }
        
        // Generate meta description via AI
        $result = $this->call_ai_api($page_content, $url);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'meta' => $result['meta_description'],
            'charCount' => strlen($result['meta_description']),
            'focusKeyword' => $result['focus_keyword'],
        ]);
    }

    /**
     * Fetch and parse content from the live URL
     */
    private function fetch_and_parse_url($url) {
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'sslverify' => false,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ]);
        
        if (is_wp_error($response)) {
            return new WP_Error('fetch_error', 'Could not fetch page: ' . $response->get_error_message());
        }
        
        $html = wp_remote_retrieve_body($response);
        
        if (empty($html)) {
            return new WP_Error('empty_response', 'Page returned empty content');
        }
        
        // Parse the HTML content
        $parsed = $this->parse_html_content($html);
        
        return $parsed;
    }
    
    /**
     * Parse HTML to extract relevant product information
     */
    private function parse_html_content($html) {
        $data = [];
        
        // Suppress HTML parsing errors
        libxml_use_internal_errors(true);
        
        $doc = new DOMDocument();
        
        // PHP 8.2+ compatible encoding handling
        $html = '<?xml encoding="UTF-8">' . $html;
        $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        // Remove the XML declaration we added
        foreach ($doc->childNodes as $item) {
            if ($item->nodeType == XML_PI_NODE) {
                $doc->removeChild($item);
            }
        }
        
        libxml_clear_errors();
        
        $xpath = new DOMXPath($doc);
        
        // Get page title
        $title_nodes = $xpath->query('//title');
        if ($title_nodes->length > 0) {
            $data['page_title'] = trim($title_nodes->item(0)->textContent);
        }
        
        // Get H1
        $h1_nodes = $xpath->query('//h1');
        if ($h1_nodes->length > 0) {
            $data['h1'] = trim($h1_nodes->item(0)->textContent);
        }
        
        // Get product title (common selectors)
        $product_title_selectors = [
            '//h1[contains(@class, "product")]',
            '//h1[contains(@class, "entry-title")]',
            '//*[contains(@class, "product-title")]',
            '//*[contains(@class, "product_title")]',
        ];
        foreach ($product_title_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $data['product_title'] = trim($nodes->item(0)->textContent);
                break;
            }
        }
        
        // Get product description/summary
        $desc_selectors = [
            '//*[contains(@class, "product-description")]',
            '//*[contains(@class, "woocommerce-product-details__short-description")]',
            '//*[contains(@class, "product-short-description")]',
            '//*[contains(@class, "entry-summary")]//*[contains(@class, "description")]',
            '//*[@id="tab-description"]',
            '//*[contains(@class, "product-summary")]',
        ];
        foreach ($desc_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $text = $this->get_text_content($nodes->item(0));
                if (strlen($text) > 50) {
                    $data['description'] = $text;
                    break;
                }
            }
        }
        
        // Get product features/specifications from lists
        $spec_selectors = [
            '//*[contains(@class, "product-features")]//li',
            '//*[contains(@class, "specifications")]//li',
            '//*[contains(@class, "product-specs")]//li',
            '//*[contains(@class, "features")]//li',
            '//table[contains(@class, "spec")]//tr',
            '//*[contains(@class, "entry-summary")]//ul//li',
        ];
        $features = [];
        foreach ($spec_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                for ($i = 0; $i < min($nodes->length, 15); $i++) {
                    $text = trim($nodes->item($i)->textContent);
                    if (strlen($text) > 5 && strlen($text) < 200) {
                        $features[] = $text;
                    }
                }
                if (!empty($features)) {
                    break;
                }
            }
        }
        if (!empty($features)) {
            $data['features'] = $features;
        }
        
        // Get main content area text
        $content_selectors = [
            '//*[contains(@class, "product-content")]',
            '//*[contains(@class, "entry-content")]',
            '//*[@id="content"]',
            '//main',
            '//*[contains(@class, "site-content")]',
        ];
        foreach ($content_selectors as $selector) {
            $nodes = $xpath->query($selector);
            if ($nodes->length > 0) {
                $text = $this->get_text_content($nodes->item(0));
                if (strlen($text) > 100 && !isset($data['main_content'])) {
                    $data['main_content'] = substr($text, 0, 3000);
                    break;
                }
            }
        }
        
        // Get meta description if exists (for reference)
        $meta_desc = $xpath->query('//meta[@name="description"]/@content');
        if ($meta_desc->length > 0) {
            $data['existing_meta'] = trim($meta_desc->item(0)->textContent);
        }
        
        // Get any visible text that looks like product info
        $paragraphs = $xpath->query('//p');
        $p_content = [];
        for ($i = 0; $i < min($paragraphs->length, 10); $i++) {
            $text = trim($paragraphs->item($i)->textContent);
            if (strlen($text) > 50 && strlen($text) < 500) {
                $p_content[] = $text;
            }
        }
        if (!empty($p_content) && !isset($data['description'])) {
            $data['paragraphs'] = $p_content;
        }
        
        return $data;
    }
    
    /**
     * Get clean text content from a DOM node
     */
    private function get_text_content($node) {
        $text = $node->textContent;
        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        return $text;
    }
    
    /**
     * AJAX handler for directly saving meta to Yoast
     */
    public function ajax_save_yoast_meta() {
        check_ajax_referer('abs_generate_meta_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $post_id = intval($_POST['post_id']);
        $meta_description = isset($_POST['meta_description']) ? sanitize_text_field($_POST['meta_description']) : '';
        $focus_keyword = isset($_POST['focus_keyword']) ? sanitize_text_field($_POST['focus_keyword']) : '';

        $saved = [];

        // Save to Yoast SEO meta
        if (!empty($meta_description)) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_description);
            $saved[] = 'meta_description';
        }

        if (!empty($focus_keyword)) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', $focus_keyword);
            $saved[] = 'focus_keyword';
        }

        wp_send_json_success([
            'message' => 'Saved: ' . implode(', ', $saved),
            'saved' => $saved,
        ]);
    }
    
    /**
     * AJAX handler for scanning products with missing meta
     */
    public function ajax_scan_missing_meta() {
        check_ajax_referer('abs_batch_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        // Get all published products
        $args = [
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ];
        
        $scan_mode = isset($_POST['scan_mode']) ? sanitize_text_field($_POST['scan_mode']) : 'either';

        $product_ids = get_posts($args);
        $missing_meta = [];

        foreach ($product_ids as $product_id) {
            $meta_desc = get_post_meta($product_id, '_yoast_wpseo_metadesc', true);
            $focus_kw = get_post_meta($product_id, '_yoast_wpseo_focuskw', true);

            $missing_desc = empty(trim($meta_desc));
            $missing_focus = empty(trim($focus_kw));

            // Decide whether to include based on scan mode
            $include = false;
            if ($scan_mode === 'meta' && $missing_desc) {
                $include = true;
            } elseif ($scan_mode === 'focus' && $missing_focus) {
                $include = true;
            } elseif ($scan_mode === 'both' && $missing_desc && $missing_focus) {
                $include = true;
            } else {
                // default: 'either' - missing either one
                if ($missing_desc || $missing_focus) {
                    $include = true;
                }
            }

            if ($include) {
                $product = get_post($product_id);
                $missing_list = [];
                if ($missing_desc) $missing_list[] = 'meta';
                if ($missing_focus) $missing_list[] = 'focus';

                $missing_meta[] = [
                    'id' => $product_id,
                    'title' => $product->post_title,
                    'url' => get_permalink($product_id),
                    'edit_url' => get_edit_post_link($product_id, 'raw'),
                    'missing' => $missing_list,
                    'missing_desc' => $missing_desc,
                    'missing_focus' => $missing_focus,
                ];
            }
        }

        wp_send_json_success([
            'products' => $missing_meta,
            'total_products' => count($product_ids),
            'missing_count' => count($missing_meta),
            'scan_mode' => $scan_mode,
        ]);
    }
    
    /**
     * AJAX handler for batch generating and saving meta
     */
    public function ajax_batch_generate_meta() {
        check_ajax_referer('abs_batch_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $post_id = intval($_POST['post_id']);
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(['message' => 'Product not found']);
        }
        
        // Get the live URL
        $url = get_permalink($post_id);
        
        if (!$url) {
            wp_send_json_error(['message' => 'Could not get product URL']);
        }
        
        // Fetch and parse the live page content
        $page_content = $this->fetch_and_parse_url($url);
        
        if (is_wp_error($page_content)) {
            wp_send_json_error(['message' => $page_content->get_error_message()]);
        }
        
        // Determine what to update based on the batch mode
        $update_mode = isset($_POST['update_mode']) ? sanitize_text_field($_POST['update_mode']) : 'both';

        // Respect existing values unless "overwrite" is requested
        $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] === '1';
        $existing_meta = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        $existing_focus = get_post_meta($post_id, '_yoast_wpseo_focuskw', true);

        // Generate meta description + focus keyword via AI
        $result = $this->call_ai_api($page_content, $url);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        $saved_fields = [];

        // Save meta description
        if (in_array($update_mode, ['both', 'meta'], true)) {
            if ($overwrite || empty(trim($existing_meta))) {
                update_post_meta($post_id, '_yoast_wpseo_metadesc', $result['meta_description']);
                $saved_fields[] = 'meta';
            }
        }

        // Save focus keyword
        if (in_array($update_mode, ['both', 'focus'], true)) {
            if (!empty($result['focus_keyword']) && ($overwrite || empty(trim($existing_focus)))) {
                update_post_meta($post_id, '_yoast_wpseo_focuskw', $result['focus_keyword']);
                $saved_fields[] = 'focus';
            }
        }

        wp_send_json_success([
            'meta' => $result['meta_description'],
            'charCount' => strlen($result['meta_description']),
            'focusKeyword' => $result['focus_keyword'],
            'saved' => true,
            'savedFields' => $saved_fields,
        ]);
    }
    
    /**
     * Call the AI API to generate meta description
     */
    private function call_ai_api($page_content, $url) {
        $api_key = get_option('abs_ai_meta_api_key', '');
        $provider = get_option('abs_ai_meta_api_provider', 'openai');
        $custom_prompt = get_option('abs_ai_meta_custom_prompt', '');
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'API key not configured');
        }
        
        // Build the prompt
        $prompt = $this->build_prompt($page_content, $url, $custom_prompt);
        
        if ($provider === 'anthropic') {
            return $this->call_anthropic_api($api_key, $prompt);
        } else {
            return $this->call_openai_api($api_key, $prompt);
        }
    }
    
    /**
     * Build the AI prompt from scanned URL content
     */
    private function build_prompt($page_content, $url, $custom_prompt) {
        $content_text = "URL Scanned: {$url}\n\n";
        
        if (!empty($page_content['product_title'])) {
            $content_text .= "Product Title: {$page_content['product_title']}\n\n";
        } elseif (!empty($page_content['h1'])) {
            $content_text .= "Page Heading: {$page_content['h1']}\n\n";
        } elseif (!empty($page_content['page_title'])) {
            $content_text .= "Page Title: {$page_content['page_title']}\n\n";
        }
        
        if (!empty($page_content['description'])) {
            $content_text .= "Product Description:\n{$page_content['description']}\n\n";
        }
        
        if (!empty($page_content['features'])) {
            $content_text .= "Product Features/Specifications:\n";
            foreach ($page_content['features'] as $feature) {
                $content_text .= "• {$feature}\n";
            }
            $content_text .= "\n";
        }
        
        if (!empty($page_content['paragraphs'])) {
            $content_text .= "Page Content:\n";
            foreach ($page_content['paragraphs'] as $p) {
                $content_text .= "{$p}\n\n";
            }
        } elseif (!empty($page_content['main_content'])) {
            $content_text .= "Page Content:\n{$page_content['main_content']}\n\n";
        }
        
        $base_prompt = "You are an SEO expert for American Biotech Supply, a company that sells scientific and medical refrigeration equipment.

I have scanned a product page URL and extracted the following content. Based ONLY on this scanned content, generate TWO things:

A) A META DESCRIPTION that:
1. Is EXACTLY 155-160 characters (this is critical - count carefully)
2. Accurately reflects what is on the page
3. Includes the key product features and benefits mentioned on the page
4. Uses relevant keywords naturally from the page content
5. Ends with a complete thought (no cut-off sentences)
6. Focuses on what makes this product valuable (temperature control, compliance, reliability, etc.)

Do NOT include in the meta description:
- The company name
- Pricing information
- Call-to-action phrases like \"Shop now\" or \"Buy today\"
- Quotation marks around the description
- Any information NOT found in the scanned content below

B) A FOCUS KEYWORD that:
1. Is 1-4 words long (short phrase preferred)
2. Is the primary SEO search term a customer would use to find this product
3. Is drawn directly from the product title and scanned page content
4. Is lowercase, no punctuation, no quotes
5. Is NOT the company name and NOT a generic word like \"product\" or \"equipment\" alone

";

        if (!empty($custom_prompt)) {
            $base_prompt .= "Additional instructions: {$custom_prompt}\n\n";
        }

        $base_prompt .= "=== SCANNED PAGE CONTENT ===\n{$content_text}\n=== END SCANNED CONTENT ===\n\n";
        $base_prompt .= "Respond with ONLY a valid JSON object, no markdown, no code fences, no explanations. Use exactly this format:\n";
        $base_prompt .= '{"meta_description": "...", "focus_keyword": "..."}';

        return $base_prompt;
    }

    /**
     * Parse AI response - expects JSON with meta_description and focus_keyword.
     * Falls back to treating the whole response as the meta description if JSON parsing fails.
     */
    private function parse_ai_response($raw) {
        $raw = trim($raw);

        // Strip common markdown code fences if the model added them
        $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
        $raw = preg_replace('/\s*```\s*$/', '', $raw);
        $raw = trim($raw);

        // Try to extract a JSON object from within the response
        $json_start = strpos($raw, '{');
        $json_end = strrpos($raw, '}');
        $meta = '';
        $focus = '';

        if ($json_start !== false && $json_end !== false && $json_end > $json_start) {
            $json_str = substr($raw, $json_start, $json_end - $json_start + 1);
            $decoded = json_decode($json_str, true);
            if (is_array($decoded)) {
                if (isset($decoded['meta_description'])) {
                    $meta = trim($decoded['meta_description']);
                }
                if (isset($decoded['focus_keyword'])) {
                    $focus = trim($decoded['focus_keyword']);
                }
            }
        }

        // Fallback: if JSON parse failed, treat entire response as meta description
        if (empty($meta)) {
            $meta = $raw;
        }

        // Clean focus keyword: strip quotes and trailing punctuation
        $focus = trim($focus, " \t\n\r\0\x0B\"'.,;:");

        return [
            'meta_description' => $meta,
            'focus_keyword' => $focus,
        ];
    }
    
    /**
     * Call OpenAI API
     */
    private function call_openai_api($api_key, $prompt) {
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'max_tokens' => 300,
                'temperature' => 0.7,
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']['message']);
        }

        if (!isset($body['choices'][0]['message']['content'])) {
            return new WP_Error('api_error', 'Invalid API response');
        }

        return $this->parse_ai_response($body['choices'][0]['message']['content']);
    }
    
    /**
     * Call Anthropic API
     */
    private function call_anthropic_api($api_key, $prompt) {
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => 30,
            'headers' => [
                'x-api-key' => $api_key,
                'Content-Type' => 'application/json',
                'anthropic-version' => '2023-06-01',
            ],
            'body' => json_encode([
                'model' => 'claude-sonnet-4-20250514',
                'max_tokens' => 300,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]),
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error('api_error', $body['error']['message']);
        }

        if (!isset($body['content'][0]['text'])) {
            return new WP_Error('api_error', 'Invalid API response');
        }

        return $this->parse_ai_response($body['content'][0]['text']);
    }
}

// Initialize the plugin
ABS_AI_Meta_Generator::get_instance();
