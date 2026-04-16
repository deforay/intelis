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
     * Parse the first meaningful frame from a stack trace string.
     * Returns { source, line, column } or nulls if unparseable.
     */
    function parseStack(stack) {
        if (!stack) return { source: '', line: 0, column: 0 };

        // Match patterns like:
        //   at functionName (http://host/path/file.js:10:5)
        //   at http://host/path/file.js:10:5
        //   http://host/path/file.js:10:5  (Firefox)
        var lines = stack.split('\n');
        for (var i = 0; i < lines.length; i++) {
            var line = lines[i].trim();
            // Skip the error message line itself
            if (line.indexOf('Error') === 0 && line.indexOf('at ') === -1) continue;

            var match = line.match(/(?:at\s+.*?\(|at\s+|@)?(https?:\/\/[^\s]+?|\/[^\s]+?):(\d+):(\d+)/);
            if (match) {
                return {
                    source: match[1],
                    line: parseInt(match[2], 10),
                    column: parseInt(match[3], 10)
                };
            }
        }

        return { source: '', line: 0, column: 0 };
    }

    /**
     * Send error data to the server.
     * Internal function — use reportError() for the public API.
     */
    function sendError(errorData) {
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
     * Report an error to the server for logging.
     *
     * Accepts either:
     *   1. A string message:
     *        reportError("Something went wrong")
     *
     *   2. A string message + extra context object:
     *        reportError("Sync failed for manifest", {
     *            type: 'sync_error',
     *            context: 'Server response: ' + data
     *        })
     *
     *   3. An Error object (or any caught error):
     *        reportError(err)
     *
     *   4. An Error object + extra context:
     *        reportError(err, { context: 'raw response: ' + data })
     *
     *   5. Full object (legacy/advanced):
     *        reportError({ message: '...', source: '...', ... })
     *
     * Line number, column, source file, stack trace, URL, and userAgent
     * are auto-captured when not explicitly provided.
     */
    function reportError(messageOrData, extra) {
        var errorData;

        if (typeof messageOrData === 'string') {
            // String message — create an Error to capture the call site stack
            var callSiteError = new Error(messageOrData);
            var parsed = parseStack(callSiteError.stack);
            errorData = {
                message: messageOrData,
                source: parsed.source,
                line: parsed.line,
                column: parsed.column,
                stack: callSiteError.stack || '',
                url: window.location.href,
                userAgent: navigator.userAgent,
                type: (extra && extra.type) || 'error'
            };
            if (extra && extra.context) {
                errorData.stack += '\n--- Context ---\n' + extra.context;
            }
        } else if (messageOrData instanceof Error) {
            // Error object
            var parsed2 = parseStack(messageOrData.stack);
            errorData = {
                message: messageOrData.message || String(messageOrData),
                source: parsed2.source,
                line: parsed2.line,
                column: parsed2.column,
                stack: messageOrData.stack || '',
                url: window.location.href,
                userAgent: navigator.userAgent,
                type: (extra && extra.type) || 'error'
            };
            if (extra && extra.context) {
                errorData.stack += '\n--- Context ---\n' + extra.context;
            }
        } else if (messageOrData && typeof messageOrData === 'object') {
            // Legacy full object — fill in missing fields
            errorData = messageOrData;
            if (!errorData.url) errorData.url = window.location.href;
            if (!errorData.userAgent) errorData.userAgent = navigator.userAgent;
            if (!errorData.type) errorData.type = 'error';

            // If line/source are missing and we have a stack, try to parse them
            if ((!errorData.line || !errorData.source) && errorData.stack) {
                var parsed3 = parseStack(errorData.stack);
                if (!errorData.source) errorData.source = parsed3.source;
                if (!errorData.line) errorData.line = parsed3.line;
                if (!errorData.column) errorData.column = parsed3.column;
            }
        } else {
            return; // Nothing useful to report
        }

        sendError(errorData);
    }

    /**
     * Global error handler for runtime errors
     */
    window.onerror = function (message, source, lineno, colno, error) {
        sendError({
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

        sendError({
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

    // Expose reportError globally for explicit error logging from application code
    window.reportError = reportError;

})();
