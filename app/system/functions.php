<?php

use Laminas\Filter\StripTags;
use App\Services\UsersService;
use App\Utilities\MiscUtility;
use Laminas\Filter\StringTrim;
use App\Services\SystemService;
use Laminas\Filter\FilterChain;
use App\Utilities\LoggerUtility;
use App\Exceptions\SystemException;
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

function _sanitizeInput(string|array $data, $customFilters = [])
{
    // Default Laminas filter chain with StripTags and StringTrim
    $defaultFilterChain = new FilterChain();
    $defaultFilterChain->attach(new StripTags())
        ->attach(new StringTrim());

    // Convert single string to array for uniform processing
    $data = is_array($data) ? $data : [$data];

    // Apply filters
    foreach ($data as $key => &$value) {
        if (is_array($value)) {
            // Recursive call for nested arrays
            $value = _sanitizeInput($value, $customFilters[$key] ?? []);
        } else {
            // Use custom filter if defined, otherwise default filter
            $filterChain = $customFilters[$key] ?? $defaultFilterChain;
            $value = $filterChain->filter($value);
        }
    }
    unset($value); // Break reference link

    return $data;
}


function _sanitizeFiles($files, $allowedTypes = [], $maxSize = null)
{
    // Use the max_upload_size from PHP's configuration if not specified
    if ($maxSize === null) {
        $maxSize = MiscUtility::convertToBytes(ini_get('upload_max_filesize'));
    }
    $sanitizedFiles = [];

    foreach ($files as $key => $file) {
        try {
            if ($file['error'] != UPLOAD_ERR_OK) {
                throw new SystemException(_translate('File upload error'), 500);
            }

            if ($file['size'] > $maxSize) {
                throw new SystemException(_translate('File size exceeds the maximum allowed size'), 400);
            }

            if (!empty($allowedTypes)) {
                $allowedMimeTypes = MiscUtility::getMimeTypeStrings($allowedTypes);
                $fileType = strtolower(mime_content_type($file['tmp_name']));
                $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (!in_array($fileType, $allowedMimeTypes) && !isset($allowedMimeTypes[$fileExtension])) {
                    throw new SystemException(_translate('File type is not allowed'), 400);
                }
            }

            $sanitizedFiles[$key] = $file;
        } catch (SystemException $e) {
            LoggerUtility::log('error', $e->getMessage());
            continue;
        } catch (Exception $e) {
            LoggerUtility::log('error', $e->getMessage());
            continue;
        }
    }

    return $sanitizedFiles;
}
