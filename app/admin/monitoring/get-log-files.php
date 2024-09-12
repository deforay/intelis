<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;

$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$logType = $_GET['log_type'] ?? 'application'; // 'application' or 'php_error'
$linesPerPage = 20; // Set to a higher value for PHP error logs
$start = isset($_GET['start']) ? intval($_GET['start']) : 0; // Start line

// Determine which log file to use
if ($logType === 'php_error') {
    $file = ini_get('error_log'); // Use PHP error log
} else {
    $date = DateUtility::isoDateFormat($_GET['date']);
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

if (file_exists($file)) {

    // If the log file exists, read and display it
    $fileContent = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    // Reverse the order of the entire file first to get the latest entries at the top
    $fileContent = array_reverse($fileContent);

    // Then slice it to get the required number of lines starting from $start
    $logEntries = array_slice($fileContent, $start, $linesPerPage);

    // Show which log is being viewed
    if ($logType === 'php_error') {
        echo "<h4>" . _translate("Viewing PHP Error Log") . "</h4><br>";
    } else {
        echo "<h4>" . _translate("Viewing Application Log for Date") . " - " . $_GET['date'] . "</h4><br>";
    }

    // If no new entries are available, stop further loading
    if (empty($logEntries)) {
        echo "<div class='logLine'>No more logs.</div>";
        exit();
    }

    foreach ($logEntries as $index => $entry) {
        $lineNumber = $start + $index + 1; // Calculate line number
        $entry = htmlspecialchars($entry); // Convert special characters to HTML entities
        echo "<div class='logLine' style='position: relative;' data-linenumber='{$lineNumber}'>
                <span class='lineNumber'>{$lineNumber}</span>{$entry}</div>";
    }

    // Check if this is the last set of logs
    if (count($logEntries) < $linesPerPage) {
        echo "<div class='logLine'>No more logs.</div>";
    }
} else {
    // If no log file is found for the selected date, fallback to the most recent log file (for application logs)
    if ($logType === 'application') {
        $recentFile = getMostRecentLogFile(ROOT_PATH . '/logs');

        if ($recentFile) {
            $fileContent = file($recentFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $logEntries = array_slice($fileContent, $start, $linesPerPage);
            $logEntries = array_reverse($logEntries); // Reverse the order of log entries
            echo "<h4>" . _translate("No data found for the selected date") . " - " . $_GET['date'] . "</h4>";
            echo "<h4>" . _translate("Showing the most recent log file: ") . basename($recentFile) . "</h4><br>";

            foreach ($logEntries as $index => $entry) {
                $lineNumber = $start + $index + 1; // Calculate line number
                $entry = htmlspecialchars($entry); // Convert special characters to HTML entities
                echo "<div class='logLine' style='position: relative;' data-linenumber='{$lineNumber}'>
                        <span class='lineNumber'>{$lineNumber}</span>{$entry}</div>";
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
