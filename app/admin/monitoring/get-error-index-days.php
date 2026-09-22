<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Utilities\ErrorIndexUtility;

// The days that logged an error matching the log viewer's search, from the error
// index. The viewer searches one day's file at a time; this says which days to open.

/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$query = $request->getQueryParams();
$search = isset($query['search']) && is_string($query['search']) ? trim($query['search']) : '';

$days = [];
if ($search !== '' && mb_strlen($search) <= 200) {
    foreach (ErrorIndexUtility::searchDays($search) as $day) {
        $days[] = [
            'date' => DateUtility::humanReadableDateFormat($day['log_date']),
            'matches' => $day['matches'],
            'lastSeen' => DateUtility::humanReadableDateFormat($day['last_seen'], includeTime: true),
            'message' => mb_substr(trim(($day['exception_class'] ?? '') . ' ' . $day['message']), 0, 300),
        ];
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(
    ['days' => $days],
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
