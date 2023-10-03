<?php

use App\Services\SystemService;


function _translate($text, $escapeForJavaScript = false)
{
    $translatedString = SystemService::translate($text);

    if ($escapeForJavaScript) {
        // Use htmlspecialchars to convert special characters to HTML entities,
        // and then use json_encode to ensure it's safe for JavaScript.
        $escapedString = json_encode(htmlspecialchars($translatedString, ENT_QUOTES, 'UTF-8'));
        // json_encode will add double quotes around the string, remove them.
        return trim($escapedString, '"');
    }

    return $translatedString;
}
