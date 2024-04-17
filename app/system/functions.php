<?php

use RuntimeException;
use Laminas\Filter\StripTags;
use App\Services\UsersService;
use App\Utilities\MiscUtility;
use Laminas\Filter\StringTrim;
use App\Services\SystemService;
use Laminas\Filter\FilterChain;
use App\Utilities\LoggerUtility;
use App\Exceptions\SystemException;
use Laminas\Diactoros\UploadedFile;
use App\Registries\ContainerRegistry;

function _translate(?string $text, ?bool $escapeText = false)
{
    if (empty($text) || !is_string($text)) {
        return $text;
    }

    $translatedString = SystemService::translate($text);

    if ($escapeText) {
        // Use htmlspecialchars to convert special characters to HTML entities,
        // and then use json_encode to ensure it's safe for JavaScript.
        $escapedString = json_encode(htmlspecialchars((string) $translatedString, ENT_QUOTES, 'UTF-8'));
        // json_encode will add double quotes around the string, remove them.
        return trim($escapedString, '"');
    }

    return $translatedString;
}

function _isAllowed($currentRequest, $privileges = null)
{
    return (ContainerRegistry::get(UsersService::class))
        ->isAllowed($currentRequest, $privileges);
}

function _sanitizeInput(string|array|null $data, $customFilters = [])
{
    // Check for null, empty array, or empty string and return appropriately
    if ($data === null) {
        return null;
    } elseif (is_array($data) && empty($data)) {
        return [];
    } elseif (is_string($data) && $data === '') {
        return '';
    }

    // Default Laminas filter chain with StripTags and StringTrim
    $defaultFilterChain = new FilterChain();
    $defaultFilterChain->attach(new StripTags())
        ->attach(new StringTrim());

    // Convert single string to array for uniform processing
    $data = is_array($data) ? $data : [$data];

    // Apply filters
    foreach ($data as $key => &$value) {
        // Skip processing for null values
        if ($value === null) {
            continue;
        }

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


function _sanitizeFiles($filesInput, $allowedTypes = [], $sanitizeFileName = true, $maxSize = null)
{
    if ($maxSize === null) {
        $uploadMaxSize = ini_get('upload_max_filesize');
        if ($uploadMaxSize) {
            $maxSize = MiscUtility::convertToBytes($uploadMaxSize);
        } else {
            $maxSize = MiscUtility::convertToBytes('500M');
        }
    }

    $sanitizedFiles = [];
    $allowedMimeTypes = MiscUtility::getMimeTypeStrings($allowedTypes); // Assume this function maps extensions to MIME types

    foreach ($filesInput as $key => $file) {
        if ($file instanceof UploadedFile) {
            try {
                if ($file->getError() !== UPLOAD_ERR_OK) {
                    throw new RuntimeException('File upload error');
                }

                if ($file->getSize() > $maxSize) {
                    throw new RuntimeException('File size exceeds the maximum allowed size');
                }

                $fileExtension = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
                $fileMimeType = $file->getClientMediaType();

                if (!empty($allowedTypes) && (!in_array($fileExtension, $allowedTypes) || !in_array($fileMimeType, $allowedMimeTypes))) {
                    throw new RuntimeException('File type is not allowed');
                }

                if ($sanitizeFileName) {
                    $sanitizedFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientFilename());
                    if ($sanitizedFilename[0] === '.') {
                        $sanitizedFilename = '_' . ltrim($sanitizedFilename, '.');
                    }
                    $file = new UploadedFile(
                        $file->getStream(),
                        $file->getSize(),
                        $file->getError(),
                        $sanitizedFilename,
                        $file->getClientMediaType()
                    );
                }

                $sanitizedFiles[$key] = $file;
            } catch (RuntimeException $e) {
                $sanitizedFiles[$key] = null; // Consider more detailed error handling or logging
            }
        }
    }

    return array_filter($sanitizedFiles);
}


function _castVariable(mixed $variable, ?string $expectedType = null, ?bool $isNullable = true)
{

    if (empty(trim($variable))) {
        if ($isNullable) {
            return null;
        } else {
            switch ($expectedType) {
                case 'array':
                    return [];
                case 'json':
                    return '{}';
                case 'string':
                    return '';
                default:
                    return null;
            }
        }
    } else {
        switch ($expectedType) {
            case 'int':
                return (int) $variable;
            case 'float':
                return (float) $variable;
            case 'string':
                return (string) $variable;
            case 'bool':
                return (bool) $variable;
            case 'array':
                return is_array($variable) ? $variable : (array) $variable;
            case 'json':
                return is_string($variable) ? json_decode($variable, true) : json_encode($variable);
            default:
                return $variable;
        }
    }
}
