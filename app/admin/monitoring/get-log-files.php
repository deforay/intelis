<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;

$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$date = DateUtility::isoDateFormat($_GET['date']);
$file = ROOT_PATH . '/logs/' . $date . '-logfile.log';
$linesPerPage = 3; // Number of lines per request
$start = isset($_GET['start']) ? intval($_GET['start']) : 0; // Start line

if (file_exists($file)) {
    $fileContent = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $logEntries = array_slice($fileContent, $start, $linesPerPage);
    $logEntries = array_reverse($logEntries); // Reverse the order of log entries

    foreach ($logEntries as $index => $entry) {
        $lineNumber = $start + $index + 1; // Calculate line number
        $entry = htmlspecialchars($entry); // Convert special characters to HTML entities
        $entry = str_replace("\n", "<br>", $entry); // Replace newlines with <br> tags
        echo "<div class='logLine' style='position: relative;' data-linenumber='{$lineNumber}'><span class='lineNumber'>{$lineNumber}</span>{$entry}</div>";
    }
} else {
    $htm = "";
    $notFoundStatus = true;
    $selectedDate = date('d-M-Y');
    foreach (range(1, 365) as $n) {
        $dateObj = new DateTime($selectedDate);
        $dateObj->modify('-1 day');
        $selectedDate = $dateObj->format('Y-m-d');
        $file = ROOT_PATH . '/logs/' . $selectedDate . '-logfile.log';
        if (file_exists($file)) {
            $notFoundStatus = false;
            $fileContent = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $logEntries = array_slice($fileContent, $start, $linesPerPage);
            $logEntries = array_reverse($logEntries); // Reverse the order of log entries

            foreach ($logEntries as $index => $entry) {
                $lineNumber = $start + $index + 1; // Calculate line number
                $entry = htmlspecialchars($entry); // Convert special characters to HTML entities
                $entry = str_replace("\n", "<br>", $entry); // Replace newlines with <br> tags
                $htm .= "<div class='logLine' style='position: relative;' data-linenumber='{$lineNumber}'><span class='lineNumber'>{$lineNumber}</span>{$entry}</div>";
            }
            echo "<h4>" . _translate("No data found for the selected date") . " - " . $_GET['date'] . "</h4><br>" . $htm;
            exit(0);
        }
    }
    if ($notFoundStatus) {
        echo '<div class="logLine">No files found</div>';
    }
}
