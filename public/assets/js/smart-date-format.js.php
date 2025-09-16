<script type="text/javascript">
    /**
     * Smart Date Format Detection JavaScript Library
     * File: assets/js/smart-date-format.js
     * Description: Intelligent date format detection and user interface
     * Dependencies: jQuery, Utilities (debounce, memoize, retry, rateLimit, unique, copyToClipboard, timer)
     */

    (function(window, $) {
        'use strict';

        // Namespace for Smart Date Format functionality
        window.SmartDateFormat = {
            // Configuration
            config: {
                apiEndpoint: '/includes/smart-date-format.php',
                debounceDelay: 600,
                maxRetries: 3,
                retryDelay: 1000,
                rateLimit: {
                    maxCalls: 10,
                    timeWindow: 60000 // 1 minute
                }
            },

            // Internal state
            debouncedDetectors: {},
            instances: new Map(),

            // Initialize smart date format detection for a specific input
            init: function(options) {
                const settings = $.extend({
                    inputId: null,
                    containerId: null,
                    hiddenFieldId: null,
                    manualInputId: null,
                    rowId: null,
                    defaultFormat: 'd/m/Y H:i',
                    clearWhenEmpty: false, // New option for import page behavior
                    onFormatSelected: null,
                    onError: null
                }, options);

                if (!settings.inputId || !settings.containerId || !settings.hiddenFieldId) {
                    console.error('SmartDateFormat: Required IDs not provided');
                    return false;
                }

                // Store instance
                this.instances.set(settings.rowId, settings);

                // Set up event listeners
                this.setupEventListeners(settings);

                return true;
            },

            // Set up event listeners for an instance
            setupEventListeners: function(settings) {
                const input = $('#' + settings.inputId);

                if (input.length === 0) {
                    console.error('SmartDateFormat: Input element not found');
                    return;
                }

                // Add CSS classes
                input.addClass('smart-date-input');

                // Input event with debouncing
                input.on('input', (e) => {
                    this.handleInput(e.target.value, settings);
                });

                // Focus/blur events for better UX
                input.on('focus', (e) => {
                    this.handleInputFocus(e.target.value, settings);
                });

                input.on('blur', () => {
                    this.handleInputBlur(settings);
                });
            },

            // Handle input focus - show hidden suggestions if available
            handleInputFocus: function(value, settings) {
                const container = this.getContainer(settings);
                const hasSelection = container.hasClass('has-selection');
                const input = $('#' + settings.inputId);

                // If field was pre-configured, make it clear it's now editable
                if (hasSelection) {
                    input.removeClass('format-selected')
                          .addClass('sample-detected')
                          .prop('placeholder', '📅 <?= _translate("Enter new sample date to change format", true); ?>')
                          .prop('readonly', false)
                          .css('cursor', 'text');

                    // Update help text to show it's now editable
                    const helpText = input.siblings('small').first();
                    if (helpText.length) {
                        helpText.html('📝 <?= _translate("Type a new sample date to change the format", true); ?>')
                                .css('color', '#007bff');
                    }

                    this.showHiddenSuggestions(settings);
                } else {
                    this.updateInputGuidance(value, settings);
                }
            },

            // Handle input blur - hide suggestions after selection
            handleInputBlur: function(settings) {
                const container = this.getContainer(settings);
                const hasSelection = container.hasClass('has-selection');

                // Only hide if there's a selection
                if (hasSelection) {
                    setTimeout(() => {
                        // Check if user clicked on a suggestion (don't hide if so)
                        if (!container.is(':hover')) {
                            this.hideSuggestionsAfterSelection(settings);
                        }
                    }, 150); // Small delay to allow clicking on suggestions
                }
            },

            // Handle input changes
            handleInput: function(value, settings) {
                this.updateInputGuidance(value, settings);

                const container = this.getContainer(settings);
                const hasSelection = container.hasClass('has-selection');

                // If input changed after selection, clear selection and detect again
                if (hasSelection) {
                    this.clearSelection(settings);
                }

                this.debounceDetection(value, settings);
            },

            // Update input guidance based on content
            updateInputGuidance: function(input, settings) {
                const inputElement = $('#' + settings.inputId);
                const helpText = inputElement.siblings('small').first();
                const container = this.getContainer(settings);
                const hasSelection = container.hasClass('has-selection');

                // If format is already selected, show format info instead of generic messages
                if (hasSelection) {
                    const selectedFormat = $('#' + settings.hiddenFieldId).val();
                    if (helpText.length && selectedFormat) {
                        helpText.html(`✅ <strong><?= _translate("Selected format:", true); ?></strong> <code>${selectedFormat}</code> | <?= _translate("Click field to change", true); ?>`)
                            .css('color', '#28a745');
                    }
                    return;
                }

                if (this.looksLikeDateFormat(input)) {
                    inputElement.removeClass('sample-detected format-selected')
                        .addClass('format-detected');
                    if (helpText.length) {
                        helpText.html('📝 <?= _translate("Date format detected - analyzing...", true); ?>')
                            .css('color', '#9c27b0');
                    }
                } else if (input.length > 0) {
                    inputElement.removeClass('format-detected format-selected')
                        .addClass('sample-detected');
                    if (helpText.length) {
                        helpText.html('📅 <?= _translate("Sample date detected - finding format...", true); ?>')
                            .css('color', '#007bff');
                    }
                } else {
                    inputElement.removeClass('format-detected sample-detected format-selected');
                    if (helpText.length) {
                        helpText.html('💡 <?= _translate("Enter any date from your instrument to auto-detect format", true); ?>')
                            .css('color', '#666');
                    }
                }
            },

            // Check if input looks like a date format
            looksLikeDateFormat: function(input) {
                const formatChars = ['Y', 'y', 'm', 'n', 'd', 'j', 'H', 'G', 'h', 'g', 'i', 's', 'A', 'M'];
                const hasFormatChars = formatChars.some(char => input.includes(char));
                const hasSeparators = /[\/\-\.\s:]/.test(input);

                return hasFormatChars && hasSeparators && input.length > 3;
            },

            // Debounced detection
            debounceDetection: function(input, settings) {
                const rowId = settings.rowId;

                if (!this.debouncedDetectors[rowId]) {
                    this.debouncedDetectors[rowId] = Utilities.debounce((value, config) => {
                        this.detectDateFormat(value, config);
                    }, this.config.debounceDelay);
                }

                this.debouncedDetectors[rowId](input, settings);
            },

            // Main detection function
            detectDateFormat: async function(input, settings) {
                if (!input || input.trim() === '') {
                    this.clearFormatSuggestions(settings);
                    return;
                }

                this.showLoadingIndicator(settings);

                try {
                    const response = await this.getRateLimitedDetection()(input);

                    if (response.success) {
                        if (response.input_type === 'format') {
                            this.showFormatStringSuggestions(response.suggestions, settings, input);
                        } else if (response.input_type === 'sample' && response.suggestions.length > 0) {
                            this.showFormatSuggestions(response.suggestions, settings, response.regional_preference);
                        } else {
                            this.showNoFormatFound(input, settings);
                        }
                    } else {
                        this.showNoFormatFound(input, settings);
                    }
                } catch (error) {
                    console.error('Date format detection failed:', error);

                    if (error.message && error.message.includes('Rate limit exceeded')) {
                        this.showRateLimitError(settings);
                    } else {
                        this.showDetectionError(input, settings, error);
                    }

                    // Call error callback if provided
                    if (settings.onError) {
                        settings.onError(error);
                    }
                }
            },

            // Get rate-limited detection function
            getRateLimitedDetection: function() {
                if (!this._rateLimitedDetection) {
                    const memoizedDetection = Utilities.memoize(
                        (input) => {
                            return $.post(this.config.apiEndpoint, {
                                input: input.trim(),
                                action: 'smart_detect'
                            });
                        },
                        (input) => input.trim().toLowerCase()
                    );

                    const retryableDetection = Utilities.retry(
                        (input) => memoizedDetection(input),
                        this.config.maxRetries,
                        this.config.retryDelay,
                        1.5
                    );

                    this._rateLimitedDetection = Utilities.rateLimit(
                        retryableDetection,
                        this.config.rateLimit.maxCalls,
                        this.config.rateLimit.timeWindow
                    );
                }

                return this._rateLimitedDetection;
            },

            // Show loading indicator
            showLoadingIndicator: function(settings) {
                const container = this.getContainer(settings);
                container.html(`
                <div class="format-loading">
                    <div class="spinner"></div>
                    🔄 <?= _translate("Analyzing date format...", true); ?>
                </div>
            `);
            },

            // Show suggestions for sample dates (clean version)
            showFormatSuggestions: function(suggestions, settings, regionalPreference) {
                const uniqueSuggestions = Utilities.unique(suggestions, 'format');
                const suggestionsHtml = uniqueSuggestions.map((suggestion, index) => {
                    return this.buildSuggestionHtml(suggestion, index, settings);
                }).join('');

                const container = this.getContainer(settings);
                // Simple, clean display without extra badges and warnings
                container.html(suggestionsHtml);
            },

            // Show suggestions for format strings (clean version)
            showFormatStringSuggestions: function(suggestions, settings, originalInput) {
                const suggestionsHtml = suggestions.map((suggestion, index) => {
                    if (suggestion.error) {
                        return this.buildErrorSuggestionHtml(suggestion);
                    }
                    return this.buildSuggestionHtml(suggestion, index, settings);
                }).join('');

                const container = this.getContainer(settings);
                // Simple display without extra headers and tips
                container.html(suggestionsHtml);
            },

            // Build suggestion HTML
            buildSuggestionHtml: function(suggestion, index, settings) {
                const confidenceClass = `confidence-${suggestion.confidence}`;
                const isUserFormat = suggestion.is_user_format || false;
                const userFormatBadge = isUserFormat ?
                    '<span class="format-badge user-format"><?= _translate("YOUR FORMAT", true); ?></span>' : '';

                const style = isUserFormat ? 'border: 2px solid #007bff; background: #f0f8ff;' : '';

                return `
                <div class="format-suggestion ${confidenceClass}"
                     onclick="SmartDateFormat.selectDateFormat('${suggestion.format}', '${settings.rowId}', ${index})"
                     title="<?= _translate("Click to select this format", true); ?>"
                     style="${style}">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div style="flex: 1;">
                            <strong style="color: #333;">${suggestion.name}${userFormatBadge}</strong><br>
                            <code style="background: #f1f3f4; padding: 1px 4px; border-radius: 2px; font-size: 11px;">${suggestion.format}</code><br>
                            <small style="color: #666;">${suggestion.description}</small>
                            ${suggestion.example ?
                    `<div style="color: #28a745; font-size: 10px; margin-top: 2px;">✅ <?= _translate("Example:", true); ?> ${suggestion.example}</div>` : ''
                }
                            ${isUserFormat ?
                    '<div style="color: #007bff; font-size: 10px; margin-top: 2px;">📝 <?= _translate("Format you entered", true); ?></div>' : ''
                }
                        </div>
                        <span class="format-badge ${suggestion.confidence}">
                            ${suggestion.confidence.toUpperCase()}
                        </span>
                    </div>
                </div>
            `;
            },

            // Build error suggestion HTML
            buildErrorSuggestionHtml: function(suggestion) {
                const correctionsHtml = suggestion.corrections ?
                    suggestion.corrections.map(correction =>
                        `<div class="format-corrections">
                        <div class="correction-item">
                            <strong style="color: #28a745;"><?= _translate("Suggested:", true); ?></strong>
                            <span class="correction-format">${correction.format}</span>
                        </div>
                        <small style="color: #666;">${correction.description}</small>
                    </div>`
                    ).join('') : '';

                return `
                <div class="format-suggestion confidence-low"
                     style="border-left-color: #dc3545 !important; background: #f8d7da;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div style="flex: 1;">
                            <strong style="color: #721c24;">❌ <?= _translate("Invalid Format", true); ?></strong><br>
                            <code style="background: #f5c6cb; padding: 1px 4px; border-radius: 2px; font-size: 11px;">${suggestion.format}</code><br>
                            <small style="color: #721c24;">${suggestion.error}</small>
                            ${correctionsHtml}
                        </div>
                        <span class="format-badge error"><?= _translate("ERROR", true); ?></span>
                    </div>
                </div>
            `;
            },

            // Build multiple interpretations warning
            buildMultipleWarning: function(suggestions) {
                return suggestions.filter(s => s.confidence === 'high').length > 1 ?
                    '<div class="format-warning" style="margin-top: 5px; text-align: center;">' +
                    '<strong><?= _translate("Multiple interpretations possible!", true); ?></strong> <?= _translate("Select the correct one for your instrument.", true); ?>' +
                    '</div>' : '';
            },

            // Build format tip
            buildFormatTip: function() {
                return `
                <div style="margin-top: 8px; padding: 8px; background: #e7f3ff; border-radius: 4px; font-size: 11px; color: #0066cc;">
                    <strong>💡 <?= _translate("Tip:", true); ?></strong> <?= _translate("We detected you entered a date format. You can also enter sample dates for auto-detection.", true); ?>
                </div>
            `;
            },

            // Select a date format
            selectDateFormat: function(format, rowId, suggestionIndex) {
                const settings = this.instances.get(rowId);
                if (!settings) return;

                const container = this.getContainer(settings);
                const input = $('#' + settings.inputId);

                // Update visual states
                container.find('.format-suggestion').removeClass('selected');
                container.addClass('has-selection');

                const suggestions = container.find('.format-suggestion');
                if (suggestions[suggestionIndex]) {
                    $(suggestions[suggestionIndex]).addClass('selected');
                }

                // Update hidden field
                $('#' + settings.hiddenFieldId).val(format);

                // Update input appearance
                if (input.length) {
                    input.removeClass('format-detected sample-detected')
                        .addClass('format-selected')
                        .prop('placeholder', `✅ <?= _translate("Format locked:", true); ?> ${format}`)
                        .prop('readonly', true)
                        .css('cursor', 'default');

                    if (input.val() && !input.val().includes(' ✓')) {
                        input.val(input.val() + ' ✓');
                    }
                }

                // Update help text with format info
                this.updateInputGuidance('', settings);

                // Animate success and hide suggestions
                this.animateSuccessSelection(settings);
                this.hideSuggestionsAfterSelection(settings);

                // Show toast notification
                toast.success(`<?= _translate("Date format applied:", true); ?> ${format}`);

                // Call callback if provided
                if (settings.onFormatSelected) {
                    settings.onFormatSelected(format, settings);
                }
            },

            // Show format confirmation banner (compact version)
            showFormatConfirmation: function(format, settings, suggestionIndex) {
                const confirmationHtml = `
                <div class="format-confirmation-banner" style="
                    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
                    border: 1px solid #c3e6cb;
                    border-radius: 6px;
                    padding: 8px 12px;
                    margin-bottom: 8px;
                    font-size: 12px;
                ">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="
                                background: #28a745;
                                color: white;
                                border-radius: 50%;
                                width: 18px;
                                height: 18px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 12px;
                                font-weight: bold;
                            ">✓</div>
                            <span style="color: #155724; font-weight: 600;">
                                <?= _translate("Applied:", true); ?>
                            </span>
                            <code style="
                                background: #fff;
                                padding: 2px 6px;
                                border-radius: 3px;
                                border: 1px solid #28a745;
                                font-size: 11px;
                                color: #28a745;
                                font-weight: bold;
                                cursor: pointer;
                            " onclick="SmartDateFormat.copyFormatToClipboard('${format}')"
                               title="<?= _translate("Click to copy format", true); ?>">
                                ${format}
                            </code>
                        </div>
                        <div style="display: flex; gap: 6px;">
                            <button onclick="SmartDateFormat.changeSelection(${settings.rowId})"
                                    style="
                                        background: #6c757d;
                                        color: white;
                                        border: none;
                                        padding: 3px 8px;
                                        border-radius: 3px;
                                        font-size: 10px;
                                        cursor: pointer;
                                    "
                                    title="<?= _translate("Change Selection", true); ?>">
                                📝
                            </button>
                            <button onclick="SmartDateFormat.testFormat('${format}', ${settings.rowId})"
                                    style="
                                        background: #17a2b8;
                                        color: white;
                                        border: none;
                                        padding: 3px 8px;
                                        border-radius: 3px;
                                        font-size: 10px;
                                        cursor: pointer;
                                    "
                                    title="<?= _translate("Test Format", true); ?>">
                                🧪
                            </button>
                        </div>
                    </div>
                </div>
            `;

                const container = this.getContainer(settings);

                // Remove existing confirmation
                container.find('.format-confirmation-banner').remove();

                // Add new confirmation at top
                container.prepend(confirmationHtml);

                // Fade other suggestions
                const allSuggestions = container.find('.format-suggestion');
                allSuggestions.each(function(index) {
                    if (index !== suggestionIndex) {
                        $(this).css({
                            'opacity': '0.3',
                            'transform': 'scale(0.95)',
                            'transition': 'all 0.3s ease'
                        });
                    }
                });
            },

            // Clear selection and restore to detection mode
            clearSelection: function(settings) {
                const container = this.getContainer(settings);
                const input = $('#' + settings.inputId);

                // Reset input appearance
                if (input.length) {
                    input.removeClass('format-selected')
                        .prop('readonly', false)
                        .css('cursor', 'text');

                    // Remove checkmark if present
                    if (input.val().includes(' ✓')) {
                        input.val(input.val().replace(' ✓', ''));
                    }

                    // Reset placeholder
                    input.prop('placeholder', '📅 <?= _translate("Enter sample date", true); ?>');
                }

                // Remove selection state
                container.removeClass('has-selection');

                // Restore all suggestions opacity
                const allSuggestions = container.find('.format-suggestion');
                allSuggestions.css({
                    'opacity': '1',
                    'transform': 'scale(1)'
                }).removeClass('selected');

                // Reset hidden field to default
                $('#' + settings.hiddenFieldId).val(settings.defaultFormat);
            },

            // Show hidden suggestions (after selection was made)
            showHiddenSuggestions: function(settings) {
                const container = this.getContainer(settings);
                const allSuggestions = container.find('.format-suggestion');

                if (allSuggestions.length > 0) {
                    allSuggestions.show().css({
                        'opacity': '1',
                        'transform': 'scale(1)'
                    });
                }
            },

            // Hide suggestions after selection (with smooth animation)
            hideSuggestionsAfterSelection: function(settings) {
                const container = this.getContainer(settings);

                setTimeout(() => {
                    const allSuggestions = container.find('.format-suggestion');
                    allSuggestions.fadeOut(300);
                }, 300); // Reduced from 1000ms to 300ms
            },

            // Change selection (reset) - now uses clearSelection
            changeSelection: function(rowId) {
                const settings = this.instances.get(rowId);
                if (!settings) return;

                this.clearSelection(settings);

                // Show hidden suggestions if available
                this.showHiddenSuggestions(settings);
            },

            // Test format
            testFormat: function(format, rowId) {
                const settings = this.instances.get(rowId);
                if (!settings) return;

                const input = $('#' + settings.inputId);
                const sampleDate = input.length ? input.val().replace(' ✓', '') : '';

                if (!sampleDate) {
                    if (toast && toast.error) {
                        toast.error("<?= _translate("Please enter a sample date first to test the format.", true); ?>");
                    }
                    return;
                }

                const testButton = event.target;
                const originalText = testButton.innerHTML;
                testButton.innerHTML = '⏳ <?= _translate("Testing...", true); ?>';
                testButton.disabled = true;

                $.post(this.config.apiEndpoint, {
                        sampleDate: sampleDate,
                        format: format,
                        action: 'validate'
                    })
                    .done(function(response) {
                        if (response.success && response.valid) {
                            toast.success(`<?= _translate("Format test passed! Parsed:", true); ?> ${response.parsed_date}`);
                        } else {
                            toast.error(`<?= _translate("Format test failed:", true); ?> ${response.error || '<?= _translate("Unknown error", true); ?>'}`);
                        }
                    })
                    .fail(function() {
                        toast.error(`<?= _translate("Test failed - network error", true); ?>`);
                    })
                    .always(function() {
                        testButton.innerHTML = originalText;
                        testButton.disabled = false;
                    });
            },

            // Copy format to clipboard
            copyFormatToClipboard: async function(format) {
                try {
                    const success = await Utilities.copyToClipboard(format);
                    if (success) {
                        toast.success("<?= _translate("Format copied to clipboard!", true); ?>");
                    } else {
                        toast.error("<?= _translate("Failed to copy format", true); ?>");
                    }
                } catch (error) {
                    toast.error("<?= _translate("Failed to copy format", true); ?>");
                }
            },

            // Show error states
            showNoFormatFound: function(input, settings) {
                const container = this.getContainer(settings);
                container.html(`
                <div class="no-format-found">
                    <strong>🤔 <?= _translate("Could not detect format", true); ?></strong><br>
                    <small><?= _translate("Try: 06/19/2025, 19.06.2025 23:19, 2025-06-19", true); ?></small>
                    <div style="margin-top: 5px;">
                        <button onclick="SmartDateFormat.suggestCommonFormats('${settings.rowId}')"
                                class="format-action-btn">
                            💡 <?= _translate("Show common formats", true); ?>
                        </button>
                        <button onclick="SmartDateFormat.toggleManualFormat('${settings.rowId}')"
                                class="format-action-btn">
                            📝 <?= _translate("Enter manually", true); ?>
                        </button>
                    </div>
                </div>
            `);
            },

            showRateLimitError: function(settings) {
                const container = this.getContainer(settings);
                container.html(`
                <div class="no-format-found">
                    <strong>⏳ <?= _translate("Too many requests", true); ?></strong><br>
                    <small><?= _translate("Please wait a moment before trying again", true); ?></small>
                    <div style="margin-top: 5px;">
                        <button onclick="SmartDateFormat.toggleManualFormat('${settings.rowId}')"
                                class="format-action-btn">
                            📝 <?= _translate("Enter format manually", true); ?>
                        </button>
                    </div>
                </div>
            `);
            },

            showDetectionError: function(input, settings, error) {
                const container = this.getContainer(settings);
                container.html(`
                <div class="no-format-found">
                    <strong>❌ <?= _translate("Detection failed", true); ?></strong><br>
                    <small><?= _translate("Network error or server issue", true); ?></small>
                    <div style="margin-top: 5px;">
                        <button onclick="SmartDateFormat.retryDetection('${input}', '${settings.rowId}')"
                                class="format-action-btn primary">
                            🔄 <?= _translate("Retry", true); ?>
                        </button>
                        <button onclick="SmartDateFormat.toggleManualFormat('${settings.rowId}')"
                                class="format-action-btn">
                            📝 <?= _translate("Manual entry", true); ?>
                        </button>
                    </div>
                </div>
            `);
            },

            // Utility functions
            retryDetection: function(input, rowId) {
                const settings = this.instances.get(rowId);
                if (!settings) return;

                delete this.debouncedDetectors[rowId];
                this.detectDateFormat(input, settings);
            },

            suggestCommonFormats: function(rowId) {
                const settings = this.instances.get(rowId);
                if (!settings) return;

                const commonFormats = [{
                        name: '<?= _translate("US Format", true); ?>',
                        format: 'm/d/Y H:i',
                        example: '06/19/2025 14:30',
                        confidence: 'medium',
                        description: '<?= _translate("Common format", true); ?>'
                    },
                    {
                        name: '<?= _translate("European Format", true); ?>',
                        format: 'd/m/Y H:i',
                        example: '19/06/2025 14:30',
                        confidence: 'medium',
                        description: '<?= _translate("Common format", true); ?>'
                    },
                    {
                        name: '<?= _translate("ISO Format", true); ?>',
                        format: 'Y-m-d H:i:s',
                        example: '2025-06-19 14:30:00',
                        confidence: 'medium',
                        description: '<?= _translate("Common format", true); ?>'
                    },
                    {
                        name: '<?= _translate("German Format", true); ?>',
                        format: 'd.m.Y H:i',
                        example: '19.06.2025 14:30',
                        confidence: 'medium',
                        description: '<?= _translate("Common format", true); ?>'
                    }
                ];

                this.showFormatSuggestions(commonFormats, settings, 'Common');
            },

            toggleManualFormat: function(rowId) {
                const settings = this.instances.get(rowId);
                if (!settings) return;

                const manualDiv = $('#' + settings.manualInputId);
                const suggestionsDiv = $('#' + settings.containerId);
                const sampleInput = $('#' + settings.inputId);

                if (manualDiv.length && manualDiv.is(':hidden')) {
                    manualDiv.show();
                    suggestionsDiv.hide();
                    sampleInput.hide();
                } else {
                    if (manualDiv.length) manualDiv.hide();
                    suggestionsDiv.show();
                    sampleInput.show();
                }
            },

            clearFormatSuggestions: function(settings) {
                const container = this.getContainer(settings);
                container.html('').removeClass('has-selection');
            },

            animateSuccessSelection: function(settings) {
                const container = this.getContainer(settings);
                container.css('animation', 'successFlash 0.6s ease-out');
                setTimeout(() => {
                    container.css('animation', '');
                }, 600);
            },

            getContainer: function(settings) {
                return $('#' + settings.containerId);
            },

            // Public API methods
            getInstance: function(rowId) {
                return this.instances.get(rowId);
            },

            destroy: function(rowId) {
                const settings = this.instances.get(rowId);
                if (settings) {
                    // Clean up event listeners
                    $('#' + settings.inputId).off('input focus blur');

                    // Clear debounced detector
                    delete this.debouncedDetectors[rowId];

                    // Remove instance
                    this.instances.delete(rowId);
                }
            },

            // Batch initialization for multiple inputs
            initMultiple: function(configs) {
                const results = [];
                configs.forEach(config => {
                    results.push(this.init(config));
                });
                return results;
            }
        };

        // Auto-initialize if data attributes are present
        $(document).ready(function() {
            $('[data-smart-date-format]').each(function() {
                const $this = $(this);
                const config = {
                    inputId: $this.attr('id'),
                    containerId: $this.data('suggestions-container'),
                    hiddenFieldId: $this.data('hidden-field'),
                    manualInputId: $this.data('manual-input'),
                    rowId: $this.data('row-id'),
                    defaultFormat: $this.data('default-format') || 'd/m/Y H:i'
                };

                SmartDateFormat.init(config);
            });
        });

    })(window, jQuery);
</script>
