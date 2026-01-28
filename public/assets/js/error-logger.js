/**
 * Client-side error logger
 * Captures JavaScript errors and unhandled promise rejections,
 * then reports them to the server for logging.
 */
(function () {
    'use strict';

    var endpoint = '/system/log-client-error.php';
    var recentErrors = [];
    var maxRecentErrors = 10;

    /**
     * Check if this error was recently reported (deduplication)
     */
    function isDuplicateError(message, source, line) {
        var errorKey = message + '|' + source + '|' + line;
        if (recentErrors.indexOf(errorKey) !== -1) {
            return true;
        }
        recentErrors.push(errorKey);
        if (recentErrors.length > maxRecentErrors) {
            recentErrors.shift();
        }
        return false;
    }

    /**
     * Send error to server
     */
    function reportError(errorData) {
        if (isDuplicateError(errorData.message, errorData.source, errorData.line)) {
            return;
        }

        try {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', endpoint, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.send(JSON.stringify(errorData));
        } catch (e) {
            // Fail silently - don't cause more errors
        }
    }

    /**
     * Global error handler for runtime errors
     */
    window.onerror = function (message, source, lineno, colno, error) {
        reportError({
            message: message || 'Unknown error',
            source: source || '',
            line: lineno || 0,
            column: colno || 0,
            stack: error && error.stack ? error.stack : '',
            url: window.location.href,
            userAgent: navigator.userAgent,
            type: 'error'
        });

        // Return false to allow default browser error handling
        return false;
    };

    /**
     * Handler for unhandled promise rejections
     */
    window.onunhandledrejection = function (event) {
        var reason = event.reason || {};
        var message = reason.message || String(reason) || 'Unhandled promise rejection';
        var stack = reason.stack || '';

        reportError({
            message: message,
            source: '',
            line: 0,
            column: 0,
            stack: stack,
            url: window.location.href,
            userAgent: navigator.userAgent,
            type: 'unhandledrejection'
        });
    };

})();
