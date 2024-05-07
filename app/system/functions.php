<?php

use App\Services\UsersService;
use App\Utilities\MiscUtility;
use Laminas\Filter\StringTrim;
use App\Services\SystemService;
use Laminas\Filter\FilterChain;
use App\Exceptions\SystemException;
use Laminas\Diactoros\UploadedFile;
use App\Registries\ContainerRegistry;
use function iter\count as iterCount;
use function iter\toArray as iterToArray;

function _translate(?string $text, bool $escapeText = false)
{
    if (empty($text) || !is_string($text)) {
        return $text;
    }

    $translatedString = SystemService::translate($text);

    // Use json_encode to ensure the string is safe for JavaScript
    return $escapeText ? json_encode($translatedString) : $translatedString;
}


function _isAllowed($currentRequest, $privileges = null)
{
    /** @var UsersService  $usersService */
    $usersService = ContainerRegistry::get(UsersService::class);
    return $usersService->isAllowed($currentRequest, $privileges);
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
    $defaultFilterChain->attach(new StringTrim());

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

function _sanitizeFiles($files, $allowedTypes = [], $sanitizeFileName = true, $maxSize = null)
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
    $allowedMimeTypes = MiscUtility::getMimeTypeStrings($allowedTypes);

    $isSingleFile = !is_array($files);
    if ($isSingleFile) {
        $files = [$files];
    }

    foreach ($files as $file) {
        if ($file instanceof UploadedFile) {
            try {
                if ($file->getError() === UPLOAD_ERR_NO_FILE) {
                    // No file was uploaded
                    throw new SystemException("No file was uploaded");
                }

                if ($file->getError() !== UPLOAD_ERR_OK) {
                    throw new SystemException('File upload error');
                }

                if ($file->getSize() > $maxSize) {
                    throw new SystemException('File size exceeds the maximum allowed size');
                }

                $fileExtension = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
                $fileMimeType = $file->getClientMediaType();

                if (!empty($allowedTypes) && (!in_array($fileExtension, $allowedTypes) || !in_array($fileMimeType, $allowedMimeTypes))) {
                    throw new SystemException('File type is not allowed');
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

                $sanitizedFiles[] = $file;
            } catch (Throwable $e) {
                //error_log($e->getMessage());
                // You can choose to handle the error differently, e.g., add null to the array or skip the file
                $sanitizedFiles[] = null;
            }
        }
    }

    return $isSingleFile ? $sanitizedFiles[0] : $sanitizedFiles;
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

function _capitalizeWords($string)
{
    if (empty($string) || $string == '') {
        return $string;
    }
    return mb_convert_case($string, MB_CASE_TITLE, "UTF-8");
}

function _capitalizeFirstLetter($string, $encoding = "UTF-8")
{
    if (empty($string || $string == '')) {
        return $string;
    }
    $firstChar = mb_substr($string, 0, 1, $encoding);
    $rest = mb_substr($string, 1, null, $encoding);
    return mb_strtoupper($firstChar, $encoding) . $rest;
}


/**
 * Safely converts an iterator to an array and retrieves a specified key.
 *
 * @param mixed $iterator The potential iterator.
 * @param string $key The key to retrieve from the array.
 * @return mixed Returns the value associated with the key, or null if not found or not an iterator.
 */
function _getIteratorKey($iterator, $key)
{
    if ($iterator instanceof Traversable) {
        $array = iterToArray($iterator);
        return $array[$key] ?? null;
    }
    return null;
}

/**
 * Safely counts the elements of an iterator or Traversable object.
 *
 * @param mixed $iterator The potential iterator or Traversable object.
 * @return int Returns the count of elements. Returns 0 if the input is not Traversable.
 */
function _getIteratorCount($iterator): int
{
    if ($iterator instanceof Traversable) {
        return iterCount($iterator);
    }
    return 0;
}
