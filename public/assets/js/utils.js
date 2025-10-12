class Utilities {
    /**
     * GENERIC UTILITY FUNCTIONS COLLECTION
     * Solve common patterns once, use everywhere
     */

    // ========================================
    // FUNCTIONAL PROGRAMMING UTILITIES
    // ========================================

    // 1. DEBOUNCE - Delay execution until activity stops
    static debounce(func, wait = 300, immediate = false) {
        if (typeof func !== 'function') {
            throw new TypeError('First argument must be a function');
        }
        let timeout;
        return function executedFunction() {
            const context = this;
            const args = arguments;
            const later = function () {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            const callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    }

    // 2. THROTTLE - Limit execution frequency
    static throttle(func, limit = 100) {
        if (typeof func !== 'function') {
            throw new TypeError('First argument must be a function');
        }
        let inThrottle;
        return function () {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        }
    }

    // 3. MEMOIZE - Cache function results
    static memoize(func, keyGenerator) {
        if (typeof func !== 'function') {
            throw new TypeError('First argument must be a function');
        }
        const cache = new Map();
        return function (...args) {
            const key = keyGenerator ? keyGenerator(...args) : JSON.stringify(args);
            if (cache.has(key)) {
                return cache.get(key);
            }
            const result = func.apply(this, args);
            cache.set(key, result);
            return result;
        }
    }

    // 4. RETRY - Automatic retry with exponential backoff
    static retry(func, maxAttempts = 3, baseDelay = 1000, backoffMultiplier = 2) {
        if (typeof func !== 'function') {
            throw new TypeError('First argument must be a function');
        }
        return function (...args) {
            return new Promise((resolve, reject) => {
                let attempts = 0;
                const attempt = () => {
                    attempts++;
                    Promise.resolve(func.apply(this, args))
                        .then(resolve)
                        .catch(err => {
                            if (attempts < maxAttempts) {
                                const delay = baseDelay * Math.pow(backoffMultiplier, attempts - 1);
                                setTimeout(attempt, delay);
                            } else {
                                reject(err);
                            }
                        });
                };
                attempt();
            });
        }
    }

    // 5. ONCE - Execute function only once
    static once(func) {
        if (typeof func !== 'function') {
            throw new TypeError('First argument must be a function');
        }
        let called = false;
        let result;
        return function (...args) {
            if (!called) {
                called = true;
                result = func.apply(this, args);
            }
            return result;
        }
    }

    // 6. COMPOSE - Combine functions (right to left)
    static compose(...funcs) {
        if (funcs.some(f => typeof f !== 'function')) {
            throw new TypeError('All arguments must be functions');
        }
        if (funcs.length === 0) return arg => arg;
        if (funcs.length === 1) return funcs[0];
        return funcs.reduce((a, b) => (...args) => a(b(...args)));
    }

    // 7. PIPE - Combine functions (left to right)
    static pipe(...funcs) {
        if (funcs.some(f => typeof f !== 'function')) {
            throw new TypeError('All arguments must be functions');
        }
        if (funcs.length === 0) return arg => arg;
        if (funcs.length === 1) return funcs[0];
        return funcs.reduce((a, b) => (...args) => b(a(...args)));
    }

    // 8. CURRY - Transform function to accept arguments one at a time
    static curry(func) {
        if (typeof func !== 'function') {
            throw new TypeError('First argument must be a function');
        }
        return function curried(...args) {
            if (args.length >= func.length) {
                return func.apply(this, args);
            } else {
                return function (...args2) {
                    return curried.apply(this, args.concat(args2));
                }
            }
        };
    }

    // 9. PARTIAL - Pre-fill some arguments
    static partial(func, ...partialArgs) {
        if (typeof func !== 'function') {
            throw new TypeError('First argument must be a function');
        }
        return function (...args) {
            return func.apply(this, partialArgs.concat(args));
        }
    }

    // 10. RATE_LIMIT - Limit function calls per time period
    static rateLimit(func, maxCalls, timeWindow) {
        if (typeof func !== 'function') {
            throw new TypeError('First argument must be a function');
        }
        const calls = [];
        return function (...args) {
            const now = Date.now();
            const windowStart = now - timeWindow;

            // Remove old calls outside the window
            while (calls.length > 0 && calls[0] < windowStart) {
                calls.shift();
            }

            if (calls.length < maxCalls) {
                calls.push(now);
                return func.apply(this, args);
            } else {
                throw new Error(`Rate limit exceeded: ${maxCalls} calls per ${timeWindow}ms`);
            }
        }
    }

    // 11. TIMEOUT - Add timeout to any function
    static withTimeout(func, timeoutMs) {
        if (typeof func !== 'function') {
            throw new TypeError('First argument must be a function');
        }
        return function (...args) {
            return Promise.race([
                Promise.resolve(func.apply(this, args)),
                new Promise((_, reject) =>
                    setTimeout(() => reject(new Error('Function timeout')), timeoutMs)
                )
            ]);
        }
    }

    // 12. CHAIN - Method chaining for any object
    static chain(obj) {
        if (!obj || typeof obj !== 'object') {
            throw new TypeError('Argument must be an object');
        }
        const wrapper = Object.create(obj);
        Object.getOwnPropertyNames(obj).forEach(prop => {
            if (typeof obj[prop] === 'function') {
                wrapper[prop] = function (...args) {
                    const result = obj[prop].apply(obj, args);
                    return result === undefined ? wrapper : result;
                };
            }
        });
        return wrapper;
    }

    // ========================================
    // VALIDATION UTILITIES
    // ========================================

    static validatePhoneNumber(phoneNumber, countryCode = null, minDigits = null, maxDigits = null) {
        if (!phoneNumber) return false;

        // Remove all non-numeric characters from the phone number
        const numericPhoneNumber = phoneNumber.replace(/\D/g, '');

        // Check if the phone number starts with the country code, if countryCode is provided
        if (countryCode && !phoneNumber.startsWith(countryCode)) {
            return false;
        }

        // Calculate the length of the phone number without the country code
        const countryCodeLength = countryCode ? countryCode.replace(/\D/g, '').length : 0;
        const lengthWithoutCountryCode = numericPhoneNumber.length - countryCodeLength;

        // Check the length of the phone number
        if (minDigits && lengthWithoutCountryCode < minDigits) {
            return false;
        }
        if (maxDigits && lengthWithoutCountryCode > maxDigits) {
            return false;
        }
        return true;
    }

    // email validation
    static validateEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // URL validation
    static validateUrl(url) {
        try {
            new URL(url);
            return true;
        } catch {
            return false;
        }
    }

    // Credit card validation (Luhn algorithm)
    static validateCreditCard(cardNumber) {
        const num = cardNumber.replace(/\D/g, '');
        let sum = 0;
        let isEven = false;

        for (let i = num.length - 1; i >= 0; i--) {
            let digit = parseInt(num.charAt(i));

            if (isEven) {
                digit *= 2;
                if (digit > 9) {
                    digit -= 9;
                }
            }

            sum += digit;
            isEven = !isEven;
        }

        return sum % 10 === 0;
    }

    // ========================================
    // BROWSER/DOM UTILITIES
    // ========================================

    static async copyToClipboard(text) {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
                return true;
            } else {
                // Fallback for older browsers
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                textArea.style.top = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                const result = document.execCommand('copy');
                textArea.remove();
                return result;
            }
        } catch (error) {
            console.error('Failed to copy to clipboard:', error);
            return false;
        }
    }

    // Get query parameters as object
    static getQueryParams(url = window.location.href) {
        const params = new URLSearchParams(new URL(url).search);
        const result = {};
        for (const [key, value] of params) {
            result[key] = value;
        }
        return result;
    }

    // Set query parameter
    static setQueryParam(key, value, url = window.location.href) {
        const urlObj = new URL(url);
        urlObj.searchParams.set(key, value);
        return urlObj.toString();
    }

    // Remove query parameter
    static removeQueryParam(key, url = window.location.href) {
        const urlObj = new URL(url);
        urlObj.searchParams.delete(key);
        return urlObj.toString();
    }

    // ========================================
    // STRING UTILITIES
    // ========================================

    static toSnakeCase(str) {
        if (!str) return '';
        return str
            // Handle sequences of uppercase letters as single words
            .replace(/([A-Z]+)([A-Z][a-z])/g, '$1_$2')
            // Add an underscore before any uppercase letter followed by lowercase letters
            .replace(/([a-z\d])([A-Z])/g, '$1_$2')
            // Lowercase the whole string
            .toLowerCase()
            // Replace spaces and any non-alphanumeric characters (excluding underscores) with underscores
            .replace(/[\s\W]+/g, '_')
            // Remove leading/trailing underscores
            .replace(/^_+|_+$/g, '');
    }

    static toCamelCase(str) {
        if (!str) return '';
        return str
            .replace(/[-_\s]+(.)?/g, (_, c) => c ? c.toUpperCase() : '')
            .replace(/^[A-Z]/, c => c.toLowerCase());
    }

    static toPascalCase(str) {
        if (!str) return '';
        return str
            .replace(/[-_\s]+(.)?/g, (_, c) => c ? c.toUpperCase() : '')
            .replace(/^[a-z]/, c => c.toUpperCase());
    }

    static toKebabCase(str) {
        if (!str) return '';
        return str
            .replace(/([a-z])([A-Z])/g, '$1-$2')
            .replace(/[\s_]+/g, '-')
            .toLowerCase()
            .replace(/^-+|-+$/g, '');
    }

    // Capitalize first letter of each word
    static toTitleCase(str) {
        if (!str) return '';
        return str.replace(/\w\S*/g, txt =>
            txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase()
        );
    }

    // Generate slug from string
    static slugify(str) {
        if (!str) return '';
        return str
            .toLowerCase()
            .trim()
            .replace(/[^\w\s-]/g, '')
            .replace(/[\s_-]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    // Truncate string with ellipsis
    static truncate(str, length = 100, suffix = '...') {
        if (!str || str.length <= length) return str;
        return str.substring(0, length).trim() + suffix;
    }

    // ========================================
    // NUMBER UTILITIES
    // ========================================

    // Format number with thousands separator
    static formatNumber(number, decimals = 0, locale = 'en-US') {
        return new Intl.NumberFormat(locale, {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        }).format(number);
    }

    // Format currency
    static formatCurrency(amount, currency = 'USD', locale = 'en-US') {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: currency
        }).format(amount);
    }

    // Generate random number in range
    static randomBetween(min, max, decimals = 0) {
        const random = Math.random() * (max - min) + min;
        return decimals > 0 ? parseFloat(random.toFixed(decimals)) : Math.floor(random);
    }

    // Clamp number between min and max
    static clamp(number, min, max) {
        return Math.min(Math.max(number, min), max);
    }

    // ========================================
    // DATE UTILITIES
    // ========================================

    /**
     *  Age calculation with proper error handling
     */
    static getAgeFromDob(dob, format = 'DD-MMM-YYYY') {
        // Ensure dayjs and its plugin are available
        if (typeof dayjs === 'undefined') {
            console.error("Day.js is not loaded");
            return {
                years: 0,
                months: 0,
                error: 'Day.js not available'
            };
        }

        // Extend dayjs with the customParseFormat plugin if available
        if (dayjs.extend && typeof dayjs_plugin_customParseFormat !== 'undefined') {
            dayjs.extend(dayjs_plugin_customParseFormat);
        }

        if (!dob) {
            return {
                years: 0,
                months: 0,
                error: 'Date of birth is required'
            };
        }

        const dobDate = dayjs(dob, format);
        if (!dobDate.isValid()) {
            return {
                years: 0,
                months: 0,
                error: 'Invalid date format'
            };
        }

        const currentDate = dayjs();
        if (dobDate.isAfter(currentDate)) {
            return {
                years: 0,
                months: 0,
                error: 'Date of birth cannot be in the future'
            };
        }

        const ageInYears = currentDate.diff(dobDate, 'year');
        const ageInMonths = currentDate.diff(dobDate, 'month') % 12;

        return {
            years: ageInYears,
            months: ageInMonths,
            totalMonths: currentDate.diff(dobDate, 'month'),
            totalDays: currentDate.diff(dobDate, 'day')
        };
    }

    // Format date relative to now (e.g., "2 hours ago")
    static timeAgo(date) {
        if (typeof dayjs !== 'undefined') {
            return dayjs(date).fromNow();
        }

        // Fallback implementation
        const now = new Date();
        const diffInSeconds = Math.floor((now - new Date(date)) / 1000);

        if (diffInSeconds < 60) return 'just now';
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} minutes ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`;
        return `${Math.floor(diffInSeconds / 86400)} days ago`;
    }

    // ========================================
    // ARRAY UTILITIES
    // ========================================

    // Remove duplicates from array
    static unique(array, key = null) {
        if (!Array.isArray(array)) return [];
        if (key) {
            const seen = new Set();
            return array.filter(item => {
                const val = item[key];
                if (seen.has(val)) return false;
                seen.add(val);
                return true;
            });
        }
        return [...new Set(array)];
    }

    // Chunk array into smaller arrays
    static chunk(array, size) {
        if (!Array.isArray(array) || size <= 0) return [];
        const chunks = [];
        for (let i = 0; i < array.length; i += size) {
            chunks.push(array.slice(i, i + size));
        }
        return chunks;
    }

    // Shuffle array
    static shuffle(array) {
        if (!Array.isArray(array)) return [];
        const shuffled = [...array];
        for (let i = shuffled.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
        }
        return shuffled;
    }

    static splitPath(path) {
        if (!path) return [];
        const parts = path.split('?');
        const paths = [parts[0]];
        if (parts.length > 1) {
            const queryParams = parts[1].split('&');
            for (let i = 0; i < queryParams.length; i++) {
                paths.push(parts[0] + '?' + queryParams.slice(0, i + 1).join('&'));
            }
        }
        return paths;
    }

    static autoSelectSingleOption(selectId) {
        const $select = $('#' + selectId);
        if ($select.length === 0) {
            console.warn(`Select element with ID '${selectId}' not found`);
            return;
        }

        const nonEmptyOptions = $select.find("option[value!='']");
        if (nonEmptyOptions.length === 1) {
            $select.val(nonEmptyOptions.val()).trigger('change');
            return true;
        }
        return false;
    }

    // ========================================
    // RANDOM STRING UTILITIES
    // ========================================

    static generateRandomString(length = 4, type = 'mixed') {
        const charSets = {
            'alpha': 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz',
            'numeric': '0123456789',
            'alphanumeric': 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',
            'mixed': 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',
            'uppercase': 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
            'safe': 'ABCDEFGHIJKLMNPQRSTUVWXYZ23456789', // Excludes similar looking chars
            'hex': '0123456789ABCDEF'
        };

        const characters = charSets[type] || charSets['mixed'];
        let result = '';

        // Use crypto.getRandomValues for better randomness if available
        if (window.crypto && window.crypto.getRandomValues) {
            const array = new Uint8Array(length);
            window.crypto.getRandomValues(array);
            for (let i = 0; i < length; i++) {
                result += characters.charAt(array[i] % characters.length);
            }
        } else {
            // Fallback to Math.random
            for (let i = 0; i < length; i++) {
                result += characters.charAt(Math.floor(Math.random() * characters.length));
            }
        }

        return result;
    }

    // ========================================
    // PERFORMANCE UTILITIES
    // ========================================

    // Simple performance timer
    static timer(label = 'Timer') {
        const start = performance.now();
        return {
            stop: () => {
                const end = performance.now();
                const duration = end - start;
                console.log(`${label}: ${duration.toFixed(2)}ms`);
                return duration;
            }
        };
    }

    // Sleep function
    static sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    // Add this to your Utilities class

    // ========================================
    // UI/FILTER UTILITIES
    // ========================================

    /**
     * Highlight filled filter inputs in a container
     * @param {string} containerSelector - CSS selector for the filter container (e.g., '#filterDiv')
     * @param {object} options - Configuration options
     * @returns {object} API object with refresh() and destroy() methods
     */
    static initFilterHighlighter(containerSelector, options = {}) {
        const defaults = {
            highlightClass: 'filter-filled',
            checkOnLoad: true,
            debounceDelay: 100
        };

        const settings = { ...defaults, ...options };

        // Main function to check and highlight filled inputs
        function highlightFilledFilters() {
            const $container = $(containerSelector);

            if ($container.length === 0) {
                console.warn(`Container '${containerSelector}' not found`);
                return;
            }

            // Check all input fields (text, date, etc.)
            $container.find('input[type="text"], input[type="date"]').each(function () {
                if ($(this).val() && $(this).val().trim() !== '') {
                    $(this).addClass(settings.highlightClass);
                } else {
                    $(this).removeClass(settings.highlightClass);
                }
            });

            // Check all select dropdowns
            $container.find('select').each(function () {
                const val = $(this).val();
                const isEmpty = !val || val === '' || val === null ||
                    (Array.isArray(val) && val.length === 0) ||
                    (typeof val === 'string' && val.includes('-- Select --'));

                if (!isEmpty) {
                    $(this).addClass(settings.highlightClass);

                    // For Select2, also highlight the container
                    if ($(this).hasClass('select2-hidden-accessible')) {
                        $(this).next('.select2-container')
                            .find('.select2-selection')
                            .addClass(settings.highlightClass);
                    }
                } else {
                    $(this).removeClass(settings.highlightClass);

                    // For Select2
                    if ($(this).hasClass('select2-hidden-accessible')) {
                        $(this).next('.select2-container')
                            .find('.select2-selection')
                            .removeClass(settings.highlightClass);
                    }
                }
            });

            // Check checkboxes
            $container.find('input[type="checkbox"]').each(function () {
                if ($(this).is(':checked')) {
                    $(this).parent().addClass(settings.highlightClass);
                } else {
                    $(this).parent().removeClass(settings.highlightClass);
                }
            });

            // Check radio buttons
            $container.find('input[type="radio"]:checked').each(function () {
                $(this).parent().addClass(settings.highlightClass);
            });
        }

        // Use the existing debounce utility
        const debouncedHighlight = Utilities.debounce(
            highlightFilledFilters,
            settings.debounceDelay
        );

        // Run on page load if enabled
        if (settings.checkOnLoad) {
            highlightFilledFilters();
        }

        // Monitor changes
        $(containerSelector).on('change keyup', 'input, select', function () {
            debouncedHighlight();
        });

        // Special handling for Select2 changes
        $(containerSelector).on(
            'select2:select select2:unselect select2:clear',
            'select.select2-element, .select2-hidden-accessible',
            function () {
                highlightFilledFilters();
            }
        );

        // For daterangepicker
        $(containerSelector).on(
            'apply.daterangepicker cancel.daterangepicker',
            '.daterangefield',
            function () {
                highlightFilledFilters();
            }
        );

        // Return public API
        return {
            refresh: highlightFilledFilters,
            destroy: function () {
                $(containerSelector).off('change keyup', 'input, select');
                $(containerSelector).off('select2:select select2:unselect select2:clear');
                $(containerSelector).off('apply.daterangepicker cancel.daterangepicker');
                $(containerSelector + ' .' + settings.highlightClass).removeClass(settings.highlightClass);
                $(containerSelector).find('.select2-selection.' + settings.highlightClass).removeClass(settings.highlightClass);
            }
        };
    }
}
