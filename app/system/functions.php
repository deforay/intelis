<?php

use App\Services\UsersService;
use App\Services\SystemService;
use App\Registries\ContainerRegistry;


function _translate(?string $text, ?bool $escapeForJavaScript = false)
{
    if (empty(trim($text)) || !is_string($text)) {
        return $text;
    }
    return once(function () use ($text, $escapeForJavaScript) {
        $translatedString = SystemService::translate($text);

        if ($escapeForJavaScript) {
            // Use htmlspecialchars to convert special characters to HTML entities,
            // and then use json_encode to ensure it's safe for JavaScript.
            $escapedString = json_encode(htmlspecialchars((string) $translatedString, ENT_QUOTES, 'UTF-8'));
            // json_encode will add double quotes around the string, remove them.
            return trim($escapedString, '"');
        }

        return $translatedString;
    });
}

function _isAllowed($currentRequest, $privileges = null)
{
    if (empty($currentRequest)) {
        return false;
    }
    return once(function () use ($currentRequest, $privileges) {
        return ContainerRegistry::get(UsersService::class)
            ->isAllowed($currentRequest, $privileges);
    });
}
