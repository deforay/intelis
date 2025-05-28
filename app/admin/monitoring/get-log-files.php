<?php


use App\Utilities\DateUtility;
use App\Registries\AppRegistry;

$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$logType = $_GET['log_type'] ?? 'application'; // 'application' or 'php_error'
$linesPerPage = 20; // Set to a higher value for PHP error logs
$start = isset($_GET['start']) ? intval($_GET['start']) : 0; // Start line
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : ''; // Search term

// Determine which log file to use
if ($logType === 'php_error') {
    $file = ini_get('error_log'); // Use PHP error log
} else {
    $date = isset($_GET['date']) ? DateUtility::isoDateFormat($_GET['date']) : date('Y-m-d');
    $file = ROOT_PATH . '/logs/' . $date . '-logfile.log'; // Use application log
}

// Function to get the most recent log file by modification time for application logs
function getMostRecentLogFile($logDirectory)
{
    $files = glob($logDirectory . '/*.log');
    if (!$files) {
        return null;
    }

    usort($files, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });

    return $files[0]; // Return the most recently modified log file
}

// Function to check if a line contains the search term
function lineContainsAllSearchTerms($line, $search)
{
    if (empty($search)) {
        return true;
    }

    $terms = array_filter(preg_split('/\s+/', trim($search)), function ($term) {
        return strlen($term) > 0;
    });

    if (empty($terms)) {
        return true;
    }

    $lineLower = strtolower($line);

    foreach ($terms as $term) {
        if (stripos($lineLower, strtolower($term)) === false) {
            return false;
        }
    }

    return true;
}

// Function to detect log level from log text
function detectLogLevel($line)
{
    $line = strtolower($line);
    if (strpos($line, 'error') !== false || strpos($line, 'exception') !== false || strpos($line, 'fatal') !== false) {
        return 'error';
    } elseif (strpos($line, 'warn') !== false) {
        return 'warning';
    } elseif (strpos($line, 'info') !== false) {
        return 'info';
    } elseif (strpos($line, 'debug') !== false) {
        return 'debug';
    }
    return 'info'; // Default to info
}

// Function to format application log entries for better readability
/**
 * Format application log entries for better readability
 * Uses DateUtility::humanReadableDateFormat for ISO timestamps
 */
function formatApplicationLogEntry($entry)
{
    // First, let's handle the stack trace line numbers
    // The line numbers in stack traces may appear in different formats
    $entry = preg_replace('/\\\\n#(\d+)/', '<br/><span style="color:#e83e8c;font-weight:bold;">#$1</span>', $entry);
    $entry = preg_replace('/\n#(\d+)/', '<br/><span style="color:#e83e8c;font-weight:bold;">#$1</span>', $entry);
    $entry = preg_replace('/\\n#(\d+)/', '<br/><span style="color:#e83e8c;font-weight:bold;">#$1</span>', $entry);

    // Also catch literal \n followed by # without escape
    $entry = str_replace('\n#',  '<br/><span style="color:#e83e8c;font-weight:bold;">#', $entry);

    // Format timestamps with DateUtility (using callback function)
    $entry = preg_replace_callback(
        '/(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[+-]\d{2}:\d{2})?)/i',
        function ($matches) {
            // Get the ISO timestamp
            $isoTimestamp = $matches[1];

            try {
                // Convert to human-readable format using DateUtility
                $humanReadable = DateUtility::humanReadableDateFormat($isoTimestamp, includeTime: true, withSeconds: true);

                // Return formatted timestamp
                return '<strong title="' . htmlspecialchars($isoTimestamp) . '">' . $humanReadable . '</strong>';
            } catch (Exception $e) {
                // If conversion fails, return the original timestamp with formatting
                return '<strong>' . $isoTimestamp . '</strong>';
            }
        },
        $entry
    );

    // Replace other patterns for better readability
    $patterns = [
        // Match and highlight specific error keywords
        '/(exception|error|fatal|warning|deprecated)/i' => '<span style="color:#dc3545;font-weight:bold;">$1</span>',

        // Match and highlight file paths and line numbers
        '/(\w+\.php):(\d+)/i' => '<span style="color:#17a2b8;">$1</span>:<span style="color:#fd7e14;font-weight:bold;">$2</span>',

        // Match and highlight SQL statements
        '/(SELECT|INSERT|UPDATE|DELETE|FROM|WHERE|JOIN|GROUP BY|ORDER BY|HAVING)(?=\s)/i' => '<span style="color:#6610f2;font-weight:bold;">$1</span>'
    ];

    // Apply all the patterns
    foreach ($patterns as $pattern => $replacement) {
        $entry = preg_replace($pattern, $replacement, $entry);
    }

    return $entry;
}

// Process PHP error log entries to properly format them with stack traces
function processPHPErrorLog($entries)
{
    $processedEntries = [];
    $currentEntry = '';
    $inStackTrace = false;

    foreach ($entries as $line) {
        // If line starts with a date in square brackets, it's a new entry
        if (preg_match('/^\[\d{2}-\w{3}-\d{4}/', $line)) {
            // Save the previous entry if it exists
            if (!empty($currentEntry)) {
                $processedEntries[] = $currentEntry;
                $currentEntry = '';
            }
            $inStackTrace = false;
            $currentEntry = $line;

            // Check if this is the start of an error with a stack trace
            if (
                stripos($line, 'Fatal error') !== false ||
                stripos($line, 'Uncaught') !== false ||
                stripos($line, 'Exception') !== false
            ) {
                $inStackTrace = true;
            }
        }
        // If we're in a stack trace or this line is part of the previous entry
        else {
            // If it's a stack trace line (starts with #number)
            if (preg_match('/^#\d+/', $line)) {
                $currentEntry .= "\n" . $line;
                $inStackTrace = true;
            }
            // If it's a continuation of the previous entry
            else if ($inStackTrace || trim($line) !== '') {
                $currentEntry .= "\n" . $line;
            }
        }
    }

    // Add the last entry if it exists
    if (!empty($currentEntry)) {
        $processedEntries[] = $currentEntry;
    }

    return $processedEntries;
}

// Format a PHP error log entry for display
function formatPhpErrorLogEntry($entry)
{
    // Replace newlines with <br> tags for display
    $html = nl2br(htmlspecialchars($entry));

    // Highlight specific parts like timestamps
    $html = preg_replace('/\[(\d{2}-\w{3}-\d{4}\s\d{2}:\d{2}:\d{2}\s\w+)\]/', '<strong>[$1]</strong>', $html);

    // Highlight stack trace numbers (#1, #2, etc.)
    $html = preg_replace('/(#\d+)/', '<span style="color:#e83e8c;font-weight:bold;">$1</span>', $html);

    // Highlight error types
    $html = preg_replace('/(PHP (?:Fatal error|Warning|Notice|Deprecated):)/', '<span style="color:#dc3545;font-weight:bold;">$1</span>', $html);

    // Highlight "thrown in" text
    $html = preg_replace('/(thrown in)/', '<span style="color:#dc3545;">$1</span>', $html);

    // Highlight file paths
    $html = preg_replace('/in (\/[\w\/\.\-]+\.php)/', 'in <span style="color:#17a2b8;">$1</span>', $html);

    // Highlight line numbers
    $html = preg_replace('/(on line |:)(\d+)/', '$1<span style="color:#fd7e14;font-weight:bold;">$2</span>', $html);

    return $html;
}

// Function to create a log line with click handling
function createLogLine($content, $lineNumber, $logLevel)
{
    // Create the log line with onclick handler
    return '<div class="logLine log-' . $logLevel . '" data-linenumber="' . $lineNumber . '" data-level="' . $logLevel . '" onclick="copyToClipboard(this.innerHTML, ' . $lineNumber . ')">
        <span class="lineNumber">' . $lineNumber . '</span>' . $content . '</div>';
}

// Variable to track if we're showing a fallback file
$actualLogDate = '';

if (file_exists($file)) {
    // If the log file exists, read and display it efficiently
    if ($logType === 'php_error') {
        // For PHP error logs, we need to process them differently to handle stack traces
        $fileContent = file($file, FILE_IGNORE_NEW_LINES);

        // Process the PHP error log to properly separate entries with stack traces
        $logEntries = processPHPErrorLog($fileContent);

        // Reverse the order to get the latest entries at the top
        $logEntries = array_reverse($logEntries);

        // Filter by search term if provided
        if (!empty($searchTerm)) {
            $logEntries = array_filter($logEntries, function ($entry) use ($searchTerm) {
                return lineContainsAllSearchTerms($entry, $searchTerm);
            });
            $logEntries = array_values($logEntries); // Reset array keys
        }

        // Slice to get the required lines
        $logEntries = array_slice($logEntries, $start, $linesPerPage);

        // Show which log is being viewed
        echo "<div class='log-header'>" . _translate("Viewing PHP Error Log") . "</div>";

        // If no entries are available, stop further loading
        if (empty($logEntries)) {
            echo "<div class='logLine'>No more logs.</div>";
            exit();
        }

        foreach ($logEntries as $index => $entry) {
            $lineNumber = $start + $index + 1; // Calculate line number
            $logLevel = 'error'; // PHP error logs are errors by default

            // Format the PHP error log entry
            $formattedEntry = formatPhpErrorLogEntry($entry);

            // Output the log line with click handler
            echo createLogLine($formattedEntry, $lineNumber, $logLevel);
        }

        // Check if this is the last set of logs
        if (count($logEntries) < $linesPerPage) {
            echo "<div class='logLine'>No more logs.</div>";
        }
    } else {
        // For application logs, use the original approach
        $fileHandle = fopen($file, 'r');
        if ($fileHandle) {
            // Get file size for more efficient processing
            $fileSize = filesize($file);

            // For very large files, use a different approach
            if ($fileSize > 10 * 1024 * 1024) { // If file is larger than 10MB
                // Store log entries that match search criteria
                $matchingEntries = [];
                $lineCount = 0;
                $matchCount = 0;

                // Read the file line by line from the end
                $lines = [];
                $pos = -2;
                $currentLine = '';
                $lastChar = '';

                while (abs($pos) < $fileSize) {
                    fseek($fileHandle, $pos, SEEK_END);
                    $char = fgetc($fileHandle);

                    if ($char === PHP_EOL && $lastChar === PHP_EOL) {
                        // Found an empty line, process the current line
                        if (!empty($currentLine)) {
                            $lines[] = $currentLine;
                            $lineCount++;
                            $currentLine = '';

                            // Check if we have enough lines to process
                            if ($lineCount - 1 >= $start && count($lines) >= $linesPerPage) {
                                break;
                            }
                        }
                    } elseif ($char === PHP_EOL) {
                        // End of line, prepend to current line
                        $currentLine = $char . $currentLine;
                    } else {
                        // Add character to current line
                        $currentLine = $char . $currentLine;
                    }

                    $lastChar = $char;
                    $pos--;

                    // Prevent infinite loops or excessive processing
                    if (abs($pos) >= $fileSize) {
                        break;
                    }
                }

                // Add the last line if it exists
                if (!empty($currentLine)) {
                    $lines[] = $currentLine;
                }

                // Filter lines by search term if provided
                if (!empty($searchTerm)) {
                    $lines = array_filter($lines, function ($line) use ($searchTerm) {
                        return lineContainsAllSearchTerms($line, $searchTerm);
                    });
                    $lines = array_values($lines); // Reset array keys
                }

                // Slice the array to get the needed lines
                $logEntries = array_slice($lines, 0, $linesPerPage);
            } else {
                // For smaller files, read the entire content
                $fileContent = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

                // Reverse the order to get the latest entries at the top
                $fileContent = array_reverse($fileContent);

                // Filter by search term if provided
                if (!empty($searchTerm)) {
                    $fileContent = array_filter($fileContent, function ($line) use ($searchTerm) {
                        return lineContainsAllSearchTerms($line, $searchTerm);
                    });
                    $fileContent = array_values($fileContent); // Reset array keys
                }

                // Slice to get the required lines
                $logEntries = array_slice($fileContent, $start, $linesPerPage);
            }

            fclose($fileHandle);

            // Show which log is being viewed
            echo "<div class='log-header'>" . _translate("Viewing System Log for Date") . " - " . $_GET['date'] . "</div>";

            // The requested log exists, so use that date
            $actualLogDate = $_GET['date'] ?? date('d-M-Y');

            // If no entries are available, stop further loading
            if (empty($logEntries)) {
                echo "<div class='logLine'>No more logs.</div>";
                exit();
            }

            foreach ($logEntries as $index => $entry) {
                $lineNumber = $start + $index + 1; // Calculate line number
                $logLevel = detectLogLevel($entry); // Detect log level
                $entry = htmlspecialchars($entry); // Convert special characters to HTML entities

                // Format the HTML to ensure proper text placement and line number position
                // First, split the entry by actual newlines or newline characters before formatting
                $lines = preg_split('/\\\\n|\\n|\n/', $entry);
                $formattedEntry = '';

                // Process each line with formatting
                foreach ($lines as $i => $line) {
                    // If line starts with #number, it's a stack trace line
                    if (preg_match('/^#(\d+)/', $line, $matches)) {
                        $line = '<span style="color:#e83e8c;font-weight:bold;">#' . $matches[1] . '</span>' . substr($line, strlen($matches[0]));
                        $formattedEntry .= ($i > 0 ? '<br/>' : '') . $line;
                    } else {
                        $formattedEntry .= ($i > 0 ? '<br/>' : '') . $line;
                    }
                }

                // Apply general formatting
                $formattedEntry = formatApplicationLogEntry($formattedEntry);

                // Output the log line with click handler
                echo createLogLine($formattedEntry, $lineNumber, $logLevel);
            }

            // Check if this is the last set of logs
            if (count($logEntries) < $linesPerPage) {
                echo "<div class='logLine'>No more logs.</div>";
            }
        } else {
            echo "<div class='error'>" . _translate("Error opening log file.") . "</div>";
        }
    }
} else {
    // If no log file is found for the selected date, fallback to the most recent log file (for application logs)
    if ($logType === 'application') {
        $recentFile = getMostRecentLogFile(ROOT_PATH . '/logs');

        if ($recentFile) {
            // Extract date from recent log filename to update the date field
            if (preg_match('/(\d{4}-\d{2}-\d{2})/', basename($recentFile), $matches)) {
                $isoDate = $matches[1];
                $dateObj = new DateTime($isoDate);
                $actualLogDate = $dateObj->format('d-M-Y');
            }

            $fileContent = file($recentFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            // Reverse the order to get the latest entries at the top
            $fileContent = array_reverse($fileContent);

            // Filter by search term if provided
            if (!empty($searchTerm)) {
                $fileContent = array_filter($fileContent, function ($line) use ($searchTerm) {
                    return lineContainsAllSearchTerms($line, $searchTerm);
                });
                $fileContent = array_values($fileContent); // Reset array keys
            }

            $logEntries = array_slice($fileContent, $start, $linesPerPage);

            echo "<div class='log-header'>" . _translate("No data found for the selected date") . " - " . ($_GET['date'] ?? date('d-M-Y')) . "<br>" .
                _translate("Showing the most recent log file") . " : " . basename($recentFile) . "</div>";

            if (empty($logEntries)) {
                echo "<div class='logLine'>No logs found.</div>";
                exit();
            }

            foreach ($logEntries as $index => $entry) {
                $lineNumber = $start + $index + 1; // Calculate line number
                $logLevel = detectLogLevel($entry); // Detect log level
                $entry = htmlspecialchars($entry); // Convert special characters to HTML entities

                // Format application log entries
                // Format the HTML to ensure proper text placement and line number position
                // First, split the entry by actual newlines or newline characters before formatting
                $lines = preg_split('/\\\\n|\\n|\n/', $entry);
                $formattedEntry = '';

                // Process each line with formatting
                foreach ($lines as $i => $line) {
                    // If line starts with #number, it's a stack trace line
                    if (preg_match('/^#(\d+)/', $line, $matches)) {
                        $line = '<span style="color:#e83e8c;font-weight:bold;">#' . $matches[1] . '</span>' . substr($line, strlen($matches[0]));
                        $formattedEntry .= ($i > 0 ? '<br/>' : '') . $line;
                    } else {
                        $formattedEntry .= ($i > 0 ? '<br/>' : '') . $line;
                    }
                }

                // Apply general formatting
                $formattedEntry = formatApplicationLogEntry($formattedEntry);

                // Output the log line with click handler
                echo createLogLine($formattedEntry, $lineNumber, $logLevel);
            }

            // Check if this is the last set of logs
            if (count($logEntries) < $linesPerPage) {
                echo "<div class='logLine'>No more logs.</div>";
            }
        } else {
            echo '<div class="logLine">No log files found.</div>';
        }
    } else {
        echo '<div class="logLine">No PHP error log found.</div>';
    }
}

// Always output a hidden field with the actual log date value
// This ensures it's present even if we're just now showing the fallback file
if ($start === 0) {
    echo "<input type='hidden' id='actualLogDate' value='{$actualLogDate}'>";
}
