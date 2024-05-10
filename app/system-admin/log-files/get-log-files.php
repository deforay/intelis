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
        echo "<div class='logLine' style='position: relative;'><span class='lineNumber'>{$lineNumber}</span>{$entry}</div>";
    }
} else {
    $notFoundStatus = true;
    $selectedDate = $_GET['date']; $list = [];
    foreach(range(1,31) as $n){
        $dateObj = new DateTime($selectedDate);
        $dateObj->modify('-1 day');
        $selectedDate = $dateObj->format('Y-m-d');
        $_SESSION['selectedDate'] = date('d-M-Y', strtotime($selectedDate));
        $file = ROOT_PATH . '/logs/' . $selectedDate . '-logfile.log';
        if(file_exists($file)){
            $notFoundStatus = false;
            $list[] = $selectedDate;
        }
    }
    $htm = "";
    if(isset($list) && !empty($list)){
        $htm = '<h4>Selected date not found Any logs. Kindly select following date to see the logs.</h4><br><div class="logLine">';
        foreach($list as $d){
            $d = trim(date('d-M-Y', strtotime($d)));
            $htm .= '<span class=""><a href="javascript:void(0);" class="btn btn-small btn-default" onclick="listDate(\''.$d.'\');">'.$d.'</a></span><br><br>';
        }
        $htm .= '</div>';
        echo $htm;
    }else{
        echo '<div class="logLine">No files found</div>';
    }
    if($notFoundStatus){
        echo '<div class="logLine">No files found</div>';
    }
}
