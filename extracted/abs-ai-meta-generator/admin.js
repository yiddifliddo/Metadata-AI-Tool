(function($) {
    'use strict';
    
    var AbsAiMeta = {
        
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            $(document).on('click', '#abs-generate-meta-btn', this.generateMeta.bind(this));
            $(document).on('click', '#abs-regenerate-meta-btn', this.generateMeta.bind(this));
            $(document).on('click', '#abs-apply-meta-btn', this.applyToYoast.bind(this));
        },
        
        generateMeta: function(e) {
            e.preventDefault();
            
            var $container = $('#abs-ai-meta-container');
            var $preview = $('#abs-meta-preview');
            var $loading = $('#abs-meta-loading');
            var $error = $('#abs-meta-error');
            var $generateBtn = $('#abs-generate-meta-btn');
            
            // Reset UI
            $preview.hide();
            $error.hide();
            $loading.show();
            $generateBtn.prop('disabled', true);
            
            $.ajax({
                url: absAiMeta.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'abs_generate_meta',
                    nonce: absAiMeta.nonce,
                    post_id: absAiMeta.postId
                },
                success: function(response) {
                    $loading.hide();
                    $generateBtn.prop('disabled', false);
                    
                    if (response.success) {
                        $('#abs-generated-meta').val(response.data.meta);
                        $('#abs-meta-char-count').html(
                            '<strong>' + response.data.charCount + '</strong> characters ' +
                            (response.data.charCount <= 160 ? 
                                '<span style="color: #00a32a;">✓ Good length</span>' : 
                                '<span style="color: #d63638;">⚠ Too long</span>')
                        );
                        $preview.show();
                    } else {
                        $error.html('Error: ' + response.data.message).show();
                    }
                },
                error: function(xhr, status, error) {
                    $loading.hide();
                    $generateBtn.prop('disabled', false);
                    $error.html('Request failed: ' + error).show();
                }
            });
        },
        
        applyToYoast: function(e) {
            e.preventDefault();
            
            var metaDescription = $('#abs-generated-meta').val();
            
            if (!metaDescription) {
                alert('No meta description to apply');
                return;
            }
            
            // Try multiple methods to set the Yoast meta description
            var success = false;
            
            // Method 1: Yoast SEO newer versions (React-based editor)
            if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
                try {
                    var yoastStore = wp.data.dispatch('yoast-seo/editor');
                    if (yoastStore && typeof yoastStore.updateData === 'function') {
                        yoastStore.updateData({ description: metaDescription });
                        success = true;
                    }
                } catch(e) {
                    console.log('Yoast dispatch method failed:', e);
                }
            }
            
            // Method 2: Direct input field (classic editor or fallback)
            if (!success) {
                // Try the snippet editor input
                var $yoastInput = $('#yoast_wpseo_metadesc');
                if ($yoastInput.length) {
                    $yoastInput.val(metaDescription).trigger('change').trigger('input');
                    success = true;
                }
            }
            
            // Method 3: Hidden input that Yoast uses
            if (!success) {
                var $hiddenInput = $('input[name="yoast_wpseo_metadesc"]');
                if ($hiddenInput.length) {
                    $hiddenInput.val(metaDescription).trigger('change');
                    success = true;
                }
            }
            
            // Method 4: Try to find the Yoast snippet editor textarea
            if (!success) {
                var $snippetTextarea = $('.yst-replacevar__input[data-id="metadesc"]');
                if ($snippetTextarea.length) {
                    $snippetTextarea.val(metaDescription).trigger('change').trigger('input');
                    success = true;
                }
            }
            
            // Method 5: For Yoast Premium / newer UI
            if (!success) {
                var $editableDiv = $('[id*="snippet-editor-field-description"]');
                if ($editableDiv.length) {
                    // It might be a contenteditable div
                    if ($editableDiv.attr('contenteditable')) {
                        $editableDiv.text(metaDescription).trigger('input');
                    } else {
                        $editableDiv.val(metaDescription).trigger('change');
                    }
                    success = true;
                }
            }
            
            // Method 6: Try clicking the edit button first, then setting value
            if (!success) {
                // Click on the snippet editor to open it
                var $snippetPreview = $('.yst-snippet-editor__preview, .snippet-editor__preview');
                if ($snippetPreview.length) {
                    $snippetPreview.trigger('click');
                    
                    // Wait for editor to open, then try again
                    setTimeout(function() {
                        var $textarea = $('textarea[id*="metadesc"], textarea[name*="metadesc"]');
                        if ($textarea.length) {
                            $textarea.val(metaDescription).trigger('change').trigger('input');
                            success = true;
                        }
                    }, 300);
                }
            }
            
            // Method 7: Store in post meta directly via AJAX (most reliable fallback)
            this.saveMetaDirectly(metaDescription);
            
            if (success) {
                this.showSuccess();
            } else {
                // Even if we couldn't find the field, we saved it directly
                this.showSuccess('Meta saved! Refresh to see in Yoast.');
            }
        },
        
        saveMetaDirectly: function(metaDescription) {
            // Also save directly to post meta as a reliable fallback
            $.ajax({
                url: absAiMeta.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'abs_save_yoast_meta',
                    nonce: absAiMeta.nonce,
                    post_id: absAiMeta.postId,
                    meta_description: metaDescription
                }
            });
        },
        
        showSuccess: function(message) {
            message = message || 'Meta description applied to Yoast!';
            
            var $applyBtn = $('#abs-apply-meta-btn');
            var originalText = $applyBtn.text();
            
            $applyBtn.text('✓ Applied!').addClass('button-success');
            
            setTimeout(function() {
                $applyBtn.text(originalText).removeClass('button-success');
            }, 2000);
            
            // Also scroll to Yoast metabox to show user
            var $yoastBox = $('#wpseo_meta');
            if ($yoastBox.length) {
                $('html, body').animate({
                    scrollTop: $yoastBox.offset().top - 50
                }, 500);
                
                // Try to expand the Yoast metabox if collapsed
                if ($yoastBox.hasClass('closed')) {
                    $yoastBox.find('.hndle, .handlediv').trigger('click');
                }
            }
        }
    };
    
    $(document).ready(function() {
        AbsAiMeta.init();
    });
    
})(jQuery);
