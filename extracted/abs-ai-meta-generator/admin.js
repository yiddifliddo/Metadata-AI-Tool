(function($) {
    'use strict';

    var AbsAiMeta = {

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $(document).on('click', '#abs-generate-meta-btn', this.generateMeta.bind(this));
            $(document).on('click', '#abs-regenerate-meta-btn', this.generateMeta.bind(this));
            $(document).on('click', '#abs-apply-meta-btn', this.applyMetaOnly.bind(this));
            $(document).on('click', '#abs-apply-focus-btn', this.applyFocusOnly.bind(this));
            $(document).on('click', '#abs-apply-both-btn', this.applyBoth.bind(this));
            $(document).on('input', '#abs-generated-meta', this.updateCharCount.bind(this));
        },

        generateMeta: function(e) {
            e.preventDefault();

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
                        $('#abs-generated-focus').val(response.data.focusKeyword || '');
                        AbsAiMeta.updateCharCount();
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

        updateCharCount: function() {
            var count = ($('#abs-generated-meta').val() || '').length;
            $('#abs-meta-char-count').html(
                '<strong>' + count + '</strong> characters ' +
                (count <= 160 ?
                    '<span style="color: #00a32a;">✓ Good length</span>' :
                    '<span style="color: #d63638;">⚠ Too long</span>')
            );
        },

        applyMetaOnly: function(e) {
            e.preventDefault();
            var metaDescription = $('#abs-generated-meta').val();
            if (!metaDescription) { alert('No meta description to apply'); return; }
            this.applyMetaToYoast(metaDescription);
            this.saveDirectly(metaDescription, '');
            this.showSuccess('#abs-apply-meta-btn', 'Meta applied!');
        },

        applyFocusOnly: function(e) {
            e.preventDefault();
            var focusKeyword = $('#abs-generated-focus').val();
            if (!focusKeyword) { alert('No focus keyword to apply'); return; }
            this.applyFocusToYoast(focusKeyword);
            this.saveDirectly('', focusKeyword);
            this.showSuccess('#abs-apply-focus-btn', 'Focus applied!');
        },

        applyBoth: function(e) {
            e.preventDefault();
            var metaDescription = $('#abs-generated-meta').val();
            var focusKeyword = $('#abs-generated-focus').val();
            if (!metaDescription && !focusKeyword) { alert('Nothing to apply'); return; }
            if (metaDescription) this.applyMetaToYoast(metaDescription);
            if (focusKeyword) this.applyFocusToYoast(focusKeyword);
            this.saveDirectly(metaDescription, focusKeyword);
            this.showSuccess('#abs-apply-both-btn', 'Applied!');
            this.scrollToYoast();
        },

        /**
         * Try multiple methods to set Yoast meta description in the live UI.
         */
        applyMetaToYoast: function(metaDescription) {
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
                    console.log('Yoast dispatch (meta) failed:', e);
                }
            }

            // Method 2: Direct input field
            if (!success) {
                var $yoastInput = $('#yoast_wpseo_metadesc');
                if ($yoastInput.length) {
                    $yoastInput.val(metaDescription).trigger('change').trigger('input');
                    success = true;
                }
            }

            // Method 3: Hidden input
            if (!success) {
                var $hiddenInput = $('input[name="yoast_wpseo_metadesc"]');
                if ($hiddenInput.length) {
                    $hiddenInput.val(metaDescription).trigger('change');
                    success = true;
                }
            }

            // Method 4: Snippet editor replacevar input
            if (!success) {
                var $snippetTextarea = $('.yst-replacevar__input[data-id="metadesc"]');
                if ($snippetTextarea.length) {
                    $snippetTextarea.val(metaDescription).trigger('change').trigger('input');
                    success = true;
                }
            }

            // Method 5: Premium/newer editable field
            if (!success) {
                var $editableDiv = $('[id*="snippet-editor-field-description"]');
                if ($editableDiv.length) {
                    if ($editableDiv.attr('contenteditable')) {
                        $editableDiv.text(metaDescription).trigger('input');
                    } else {
                        $editableDiv.val(metaDescription).trigger('change');
                    }
                    success = true;
                }
            }

            return success;
        },

        /**
         * Try multiple methods to set Yoast focus keyword in the live UI.
         */
        applyFocusToYoast: function(focusKeyword) {
            var success = false;

            // Method 1: Yoast data store
            if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
                try {
                    var yoastStore = wp.data.dispatch('yoast-seo/editor');
                    if (yoastStore) {
                        // Newer Yoast versions use setFocusKeyphrase
                        if (typeof yoastStore.setFocusKeyphrase === 'function') {
                            yoastStore.setFocusKeyphrase(focusKeyword);
                            success = true;
                        } else if (typeof yoastStore.updateData === 'function') {
                            yoastStore.updateData({ focusKeyphrase: focusKeyword, keyword: focusKeyword });
                            success = true;
                        }
                    }
                } catch(e) {
                    console.log('Yoast dispatch (focus) failed:', e);
                }
            }

            // Method 2: Classic editor input
            if (!success) {
                var $focusInput = $('#yoast_wpseo_focuskw');
                if ($focusInput.length) {
                    $focusInput.val(focusKeyword).trigger('change').trigger('input');
                    success = true;
                }
            }

            // Method 3: Hidden input (multiple possible names)
            if (!success) {
                var $hiddenFocus = $('input[name="yoast_wpseo_focuskw"], input[name="_yoast_wpseo_focuskw"]');
                if ($hiddenFocus.length) {
                    $hiddenFocus.val(focusKeyword).trigger('change');
                    success = true;
                }
            }

            // Method 4: React snippet editor focus keyphrase input
            if (!success) {
                var $keyphraseInput = $('#focus-keyword-input-metabox, #focus-keyword-input, input[id*="focus-keyword"]');
                if ($keyphraseInput.length) {
                    // Use native setter to make React notice the change
                    $keyphraseInput.each(function() {
                        var el = this;
                        var nativeSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value');
                        if (nativeSetter && nativeSetter.set) {
                            nativeSetter.set.call(el, focusKeyword);
                        } else {
                            el.value = focusKeyword;
                        }
                        el.dispatchEvent(new Event('input', { bubbles: true }));
                        el.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                    success = true;
                }
            }

            return success;
        },

        saveDirectly: function(metaDescription, focusKeyword) {
            // Always persist to post meta as a reliable fallback
            $.ajax({
                url: absAiMeta.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'abs_save_yoast_meta',
                    nonce: absAiMeta.nonce,
                    post_id: absAiMeta.postId,
                    meta_description: metaDescription || '',
                    focus_keyword: focusKeyword || ''
                }
            });
        },

        showSuccess: function(buttonSelector, message) {
            var $btn = $(buttonSelector);
            var originalText = $btn.text();
            $btn.text('✓ ' + message).addClass('button-success');
            setTimeout(function() {
                $btn.text(originalText).removeClass('button-success');
            }, 2000);
        },

        scrollToYoast: function() {
            var $yoastBox = $('#wpseo_meta');
            if ($yoastBox.length) {
                $('html, body').animate({
                    scrollTop: $yoastBox.offset().top - 50
                }, 500);
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
