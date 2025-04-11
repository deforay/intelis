<?php

use voku\helper\AntiXSS;
use App\Services\UsersService;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Services\SystemService;
use App\Utilities\LoggerUtility;
use App\Exceptions\SystemException;
use Laminas\Diactoros\UploadedFile;

use App\Registries\ContainerRegistry;
use App\Utilities\MemoUtility;

use function iter\count as iterCount;
use function iter\toArray as iterToArray;


function _translate(?string $text, bool $escapeText = false)
{
    if (empty($text) || !is_string($text) || $_SESSION['APP_LOCALE'] == 'en_US') {
        return $text;
    }

    $translatedString = SystemService::translate($text);

    if ($escapeText && $_SESSION['APP_LOCALE'] != 'en_US') {
        // Use json_encode to ensure it's safe for JavaScript.
        // json_encode will add double quotes around the string, remove them.
        $escapedString = trim(json_encode($translatedString), '"');

        // Use htmlspecialchars to convert special characters to HTML entities,
        $translatedString = htmlspecialchars($escapedString, ENT_QUOTES, 'UTF-8');
    }

    return $translatedString;
}

function _isAllowed($currentRequest, $privileges = null)
{
    /** @var UsersService  $usersService */
    $usersService = ContainerRegistry::get(UsersService::class);
    return $usersService->isAllowed($currentRequest, $privileges);
}

/**
 * Sanitizes input data against XSS and other injection attacks
 *
 * @param mixed $input The input to sanitize
 * @param bool $nullifyEmptyStrings Whether to convert empty strings to null
 * @return mixed The sanitized input
 */
function _sanitizeInput(mixed $input, bool $nullifyEmptyStrings = false): mixed
{
    $antiXss = new AntiXSS();

    // Recursive sanitization
    if (is_array($input)) {
        foreach ($input as $key => $value) {
            $input[$key] = _sanitizeInput($value, $nullifyEmptyStrings);
        }
    } elseif (is_object($input)) {
        $reflection = new ReflectionObject($input);
        $properties = $reflection->getProperties();
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($input);
            $property->setValue($input, _sanitizeInput($value, $nullifyEmptyStrings));
        }
    } elseif (is_string($input)) {
        // Normalize encoding to UTF-8 and remove invisible characters
        $input = MiscUtility::toUtf8($input);

        // Trim and sanitize using AntiXSS
        $input = trim($input);
        $input = $antiXss->xss_clean($input);

        // Convert empty strings to null if $nullifyEmptyStrings is true
        if ($nullifyEmptyStrings && $input === '') {
            $input = null;
        }
    }

    return $input;
}

/**
 * Sanitizes uploaded files, validating type, size, and name
 *
 * @param UploadedFile|array $files The file(s) to sanitize
 * @param array $allowedTypes Allowed file extensions
 * @param bool $sanitizeFileName Whether to sanitize filenames
 * @param int|null $maxSize Maximum file size in bytes
 * @return UploadedFile|array|null The sanitized file(s)
 */
function _sanitizeFiles($files, array $allowedTypes = [], bool $sanitizeFileName = true, ?int $maxSize = null): UploadedFile|array|null
{
    if ($maxSize === null) {
        $uploadMaxSize = ini_get('upload_max_filesize');
        $uploadMaxSize = $uploadMaxSize ? trim($uploadMaxSize) : '500M';
        $maxSize = MiscUtility::convertToBytes($uploadMaxSize);
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
                    throw new SystemException("No file was uploaded");
                }

                if ($file->getError() !== UPLOAD_ERR_OK) {
                    $errorMessages = [
                        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini',
                        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive specified in the HTML form',
                        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
                    ];

                    $errorMessage = $errorMessages[$file->getError()] ?? 'Unknown upload error';
                    throw new SystemException("File upload error: $errorMessage");
                }

                if ($file->getSize() > $maxSize) {
                    throw new SystemException('File size exceeds the maximum allowed size of ' .
                        round($maxSize / 1048576, 2) . ' MB');
                }

                $fileExtension = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
                $fileMimeType = $file->getClientMediaType();

                if (
                    !empty($allowedTypes) &&
                    (!in_array($fileExtension, $allowedTypes) ||
                        !in_array($fileMimeType, $allowedMimeTypes))
                ) {
                    throw new SystemException('File type is not allowed. Allowed types: ' .
                        implode(', ', $allowedTypes));
                }

                // Additional MIME type validation if possible
                if (function_exists('mime_content_type') && $file->getStream()->isReadable()) {
                    $tempFile = $file->getStream()->getMetadata('uri');
                    if ($tempFile && file_exists($tempFile)) {
                        $actualMimeType = mime_content_type($tempFile);
                        if (!empty($allowedMimeTypes) && !in_array($actualMimeType, $allowedMimeTypes)) {
                            throw new SystemException('File content type does not match the allowed types');
                        }
                    }
                }

                if ($sanitizeFileName) {
                    // Option 1: Preserve original filename with sanitization
                    $sanitizedFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', $file->getClientFilename());
                    if (strlen($sanitizedFilename) > 0 && $sanitizedFilename[0] === '.') {
                        $sanitizedFilename = '_' . ltrim($sanitizedFilename, '.');
                    }

                    // Option 2: Generate a random filename while preserving the extension
                    // Uncomment to use this approach instead
                    /*
                    $fileExtension = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
                    $sanitizedFilename = bin2hex(random_bytes(8)) . '.' . $fileExtension;
                    */

                    $file = new UploadedFile(
                        $file->getStream(),
                        $file->getSize(),
                        $file->getError(),
                        $sanitizedFilename,
                        $file->getClientMediaType()
                    );
                }

                $sanitizedFiles[] = $file;
            } catch (SystemException $e) {
                $sanitizedFiles[] = null;
                LoggerUtility::logError('File validation error: ' . $e->getMessage(), [
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                    'trace' => $e->getTraceAsString()
                ]);
            } catch (Throwable $e) {
                $sanitizedFiles[] = null;
                LoggerUtility::logError('Unexpected file processing error: ' . $e->getMessage(), [
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        } else {
            $sanitizedFiles[] = null;
        }
    }

    return $isSingleFile ? $sanitizedFiles[0] : $sanitizedFiles;
}

/**
 * Sanitizes and validates JSON input
 *
 * @param string $jsonString The JSON string to sanitize
 * @param bool $nullifyEmptyStrings Whether to convert empty strings to null
 * @param bool $returnAsArray Whether to return sanitized data as array instead of JSON string
 * @return string|array Sanitized JSON string or array
 */
function _sanitizeJson(string $jsonString, bool $nullifyEmptyStrings = false, bool $returnAsArray = false): string|array
{
    // Decode JSON string
    $decoded = json_decode($jsonString, true);

    // Check for JSON errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $returnAsArray ? [] : '{}';
    }

    // Sanitize the decoded data
    $sanitized = _sanitizeInput($decoded, $nullifyEmptyStrings);

    return $returnAsArray ? $sanitized : json_encode($sanitized);
}

/**
 * Securely serves a file for download or inline display with proper headers
 *
 * @param string $filePath Path to the file to serve
 * @param string|null $fileName Optional custom filename for the download
 * @param string|null $contentType Optional content type (defaults to autodetect)
 * @param bool $forceDownload Whether to force download (attachment) or allow inline display
 * @return bool Returns false if file doesn't exist, otherwise exits after serving
 */
function _serveSecureFile(
    string $filePath,
    ?string $fileName = null,
    ?string $contentType = null,
    bool $forceDownload = true
): bool {
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return false;
    }

    // Use provided filename or original filename
    $fileName ??= basename($filePath);
    $fileName = _sanitizeInput($fileName);

    // Determine content type if not provided
    if ($contentType === null) {
        $contentType = mime_content_type($filePath) ?: 'application/octet-stream';
    }

    // Prevent caching
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Cache-Control: private', false);
    header('Pragma: public');
    header('Expires: 0');

    // Set security headers
    header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; script-src \'self\'; style-src \'self\'');

    // Set appropriate headers
    header("Content-Type: $contentType");

    // Determine disposition (attachment forces download, inline allows browser display if possible)
    $disposition = $forceDownload ? 'attachment' : 'inline';
    header("Content-Disposition: $disposition; filename=\"$fileName\"");

    header('Content-Length: ' . filesize($filePath));
    header('Content-Transfer-Encoding: binary');

    // Clear output buffer before sending the file
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Send file
    readfile($filePath);
    exit;
}

/**
 * Checks if a string potentially contains an injection attempt
 *
 * @param string $input String to check
 * @return bool True if potentially malicious, false otherwise
 */
function _isPotentiallyMalicious(string $input): bool
{
    // Common SQL injection patterns
    $sqlPatterns = [
        '/(\%27)|(\')|(\-\-)|(\%23)|(#)/',
        '/((\%3D)|(=))[^\n]*((\%27)|(\')|(\-\-)|(\%3B)|;)/',
        '/\w*((\%27)|(\'))((\%6F)|o|(\%4F))((\%72)|r|(\%52))/',
        '/((\%27)|(\'))union/',
        '/exec(\s|\+)+(s|x)p\w+/',
        '/UNION\s+ALL\s+SELECT/i',
        '/SELECT\s+.*\s+FROM/i',
        '/INSERT\s+INTO/i',
        '/DELETE\s+FROM/i',
        '/DROP\s+TABLE/i'
    ];

    // Common XSS patterns
    $xssPatterns = [
        '/<script[^>]*>.*?<\/script>/is',
        '/on\w+\s*=\s*["\'][^"\']*["\']/',
        '/<\s*embed[^>]*>.*?<\s*\/\s*embed\s*>/is',
        '/<\s*object[^>]*>.*?<\s*\/\s*object\s*>/is',
        '/<\s*iframe[^>]*>.*?<\s*\/\s*iframe\s*>/is',
        '/javascript\s*:/i',
        '/vbscript\s*:/i',
        '/data\s*:/i'
    ];

    // Check SQL patterns
    foreach ($sqlPatterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return true;
        }
    }

    // Check XSS patterns
    foreach ($xssPatterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return true;
        }
    }

    return false;
}


/**
 * Casts a variable to the specified type
 *
 * @param mixed $variable The variable to cast
 * @param string|null $expectedType The type to cast to ('int', 'float', 'string', 'bool', 'array', 'json')
 * @param bool $isNullable Whether to return null for empty values
 * @return mixed The cast variable
 */
function _castVariable(mixed $variable, ?string $expectedType = null, bool $isNullable = true): mixed
{
    // Check if variable is empty
    if ($variable === null || (is_string($variable) && trim($variable) === '') || $variable === []) {
        if ($isNullable) {
            return null;
        } else {
            return match ($expectedType) {
                'array' => [],
                'json' => '{}',
                'string' => '',
                'int' => 0,
                'float' => 0.0,
                'bool' => false,
                default => null,
            };
        }
    }

    return match ($expectedType) {
        'int' => (int) $variable,
        'float' => (float) $variable,
        'string' => (string) $variable,
        'bool' => (bool) $variable,
        'array' => is_array($variable) ? $variable : (array) $variable,
        'json' => JsonUtility::toJSON($variable),
        default => $variable,
    };
}

/**
 * Capitalizes the first letter of each word in a string
 *
 * @param string $string The input string
 * @param string $encoding The character encoding
 * @return string The string with the first letter of each word capitalized
 */
function _capitalizeWords(string $string, string $encoding = "UTF-8"): string
{
    // Check if string is empty
    if (empty($string)) {
        return $string;
    }

    return mb_convert_case($string, MB_CASE_TITLE, $encoding);
}

/**
 * Capitalizes only the first letter of a string
 *
 * @param string $string The input string
 * @param string $encoding The character encoding
 * @return string The string with only the first letter capitalized
 */
function _capitalizeFirstLetter(string $string, string $encoding = "UTF-8"): string
{
    // Check if string is empty
    if (empty($string)) {
        return $string;
    }

    return mb_convert_case(mb_substr($string, 0, 1, $encoding), MB_CASE_UPPER, $encoding) .
        mb_substr($string, 1, null, $encoding);
}
/**
 * Converts a string to uppercase
 *
 * @param string|null $string The input string
 * @param string $encoding The character encoding
 * @return string|null The uppercase string or null if input was null
 */
function _toUpperCase(?string $string, string $encoding = "UTF-8"): ?string
{
    // Check if string is empty or null
    if (empty($string)) {
        return $string;
    }

    return mb_strtoupper($string, $encoding);
}

/**
 * Converts a string to lowercase
 *
 * @param string|null $string The input string
 * @param string $encoding The character encoding
 * @return string|null The lowercase string or null if input was null
 */
function _toLowerCase(?string $string, string $encoding = "UTF-8"): ?string
{
    // Check if string is empty or null
    if (empty($string)) {
        return $string;
    }

    return mb_strtolower($string, $encoding);
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
    if ($iterator instanceof Iterator) {
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

function _sanitizeOutput($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
