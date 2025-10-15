<?php

namespace App\Utilities;

use Throwable;
use Normalizer;
use ZipArchive;
use Sqids\Sqids;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Uid\Uuid;
use App\Exceptions\SystemException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;

final class MiscUtility
{

    public static function sanitizeFilename($filename, $regex = '/[^a-zA-Z0-9\-]/', $replace = ''): string
    {
        return preg_replace($regex, $replace, $filename);
    }

    public static function sqid($data)
    {
        $data = !is_array($data) ? [$data] : $data;
        $sqids = new Sqids('', 10);
        return $sqids->encode($data);
    }

    public static function desqid(string $data, bool $returnArray = false)
    {
        if (empty($data) || $data == '' || !is_string($data)) {
            return $returnArray ? [] : null;
        }

        $desqid = null;
        $sqids = new Sqids();
        $ids = $sqids->decode($data);
        if ($returnArray === false && count($ids) == 1) {
            $desqid = $ids[0];
        } else {
            $desqid = $ids;
        }
        return $desqid;
    }

    public static function generateRandomString(int $length = 32): string
    {
        try {
            $bytes = random_bytes($length);
            $result = '';

            // Create a character set of alphanumeric characters
            $charSet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            $charSetLength = strlen($charSet);

            // Convert random bytes to characters from our character set
            for ($i = 0; $i < $length; $i++) {
                $result .= $charSet[ord($bytes[$i]) % $charSetLength];
            }

            return $result;
        } catch (Throwable $e) {
            throw new SystemException('Failed to generate random string: ' . $e->getMessage());
        }
    }

    public static function generateRandomNumber(int $length = 8): string
    {
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= random_int(0, 9);
        }
        return $result;
    }

    public static function randomHexColor(): string
    {
        $hexColorPart = fn() => str_pad(dechex(random_int(0, 255)), 2, '0', STR_PAD_LEFT);

        return strtoupper($hexColorPart() . $hexColorPart() . $hexColorPart());
    }

    public static function makeDirectory($path, $mode = 0755, $recursive = true): bool
    {
        $filesystem = new Filesystem();

        if ($filesystem->exists($path)) {
            return true; // Directory already exists
        }

        try {
            $filesystem->mkdir($path, $mode); // Handles recursive creation automatically
            return true;
        } catch (Throwable $exception) {
            return false; // Directory creation failed
        }
    }

    public static function detectCSVDelimiter(string $csvLine): string
    {
        $delimiters = [',', ';', "\t", '|'];
        $counts = [];

        foreach ($delimiters as $delimiter) {
            $counts[$delimiter] = substr_count($csvLine, $delimiter);
        }

        arsort($counts);
        return array_key_first($counts);
    }



    public static function removeDirectory($dirname): bool
    {
        $filesystem = new Filesystem();

        if (!$filesystem->exists($dirname)) {
            return false; // Directory doesn't exist, so nothing to remove
        }

        try {
            // This handles both files and directories recursively
            $filesystem->remove($dirname);
            return true; // Removal was successful
        } catch (Throwable $exception) {
            return false; // Removal failed
        }
    }

    public static function deleteFile(string $filePath): bool
    {
        $filesystem = new Filesystem();

        if (!$filesystem->exists($filePath) || !is_file($filePath)) {
            return false; // File doesn't exist or is not a file
        }

        try {
            $filesystem->remove($filePath);
            return true;
        } catch (Throwable $exception) {
            // Optionally, you can log the error here
            return false; // Deletion failed
        }
    }


    //dump the contents of a variable to the error log in a readable format
    public static function dumpToErrorLog($object = null, $useVarDump = true): void
    {
        // Get caller information - fetch 3 frames to show 2 layers
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        // First caller (immediate caller)
        $caller = $backtrace[0];
        $callerFile = basename($caller['file'] ?? 'unknown');
        $callerLine = $caller['line'] ?? 'unknown';

        $callerInfo = "[{$callerFile}:{$callerLine}]";

        ob_start();
        if ($useVarDump) {
            var_dump($object);
            $output = ob_get_clean();
            // Remove newline characters
            $output = str_replace("\n", "", $output);
        } else {
            print_r($object);
            $output = ob_get_clean();
        }

        // Additional context with caller info
        $output = "[{" . DateUtility::getCurrentDateTime() . "}]{$callerInfo}:::DUMP:::$output";

        LoggerUtility::logInfo($output);
    }
    /**
     * Checks if the array contains any null or empty string values.
     *
     * @param array $array The array to check.
     * @return bool Returns true if any value is null or an empty string, false otherwise.
     */
    public static function hasEmpty(array $array): bool
    {
        foreach ($array as $value) {
            if ($value === null || trim((string) $value) === "") {
                return true;
            }
        }
        return false;
    }

    public static function fileExists($filePath): bool
    {
        $filesystem = new Filesystem();

        // The exists() method checks if the file exists (whether it's a file or directory)
        return $filesystem->exists($filePath) && is_file($filePath) && is_readable($filePath);
    }

    /**
     * Check if a file is a valid, non-corrupted image with caching support.
     *
     * @param string $filePath Path to the image file
     * @param array $allowedMimeTypes Array of allowed MIME types
     * @param int $minWidth Minimum valid width in pixels
     * @param int $minHeight Minimum valid height in pixels
     * @param int $maxFileSize Maximum file size in bytes
     * @param int $cacheDays Number of days to cache validation results (0 to disable cache)
     * @param string $cacheDir Directory to store cache files
     * @return bool True if the image is valid, false otherwise
     */
    public static function isImageValid(
        string $filePath,
        array $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
        int $minWidth = 1,
        int $minHeight = 1,
        int $maxFileSize = 0,
        int $cacheDays = 7,
        string $cacheDir = CACHE_PATH . '/image_validation_cache'
    ): bool {
        // Skip cache if cacheDays is 0
        if ($cacheDays <= 0) {
            return self::validateImageWithoutCache($filePath, $allowedMimeTypes, $minWidth, $minHeight, $maxFileSize);
        }

        // Check if file exists and is readable
        if (!self::fileExists($filePath)) {
            return false;
        }

        // Create cache directory if it doesn't exist
        if (!self::makeDirectory($cacheDir)) {
            // If we can't create the cache directory, fall back to non-cached validation
            return self::validateImageWithoutCache($filePath, $allowedMimeTypes, $minWidth, $minHeight, $maxFileSize);
        }

        // Generate a unique cache key based on file path and modification time
        $fileModTime = filemtime($filePath);
        $fileSize = filesize($filePath);
        $cacheKey = md5($filePath . $fileModTime . $fileSize . implode(',', $allowedMimeTypes) .
            $minWidth . $minHeight . $maxFileSize);
        $cacheFile = "$cacheDir/$cacheKey.cache";

        // Check if valid cache entry exists
        if (file_exists($cacheFile)) {
            $cacheAge = time() - filemtime($cacheFile);
            $cacheExpiryTime = $cacheDays * 86400; // Convert days to seconds

            if ($cacheAge < $cacheExpiryTime) {
                // Cache is still valid, return cached result
                return (bool) file_get_contents($cacheFile);
            }
        }

        // Cache doesn't exist or is expired, perform validation
        $isValid = self::validateImageWithoutCache($filePath, $allowedMimeTypes, $minWidth, $minHeight, $maxFileSize);

        // Store result in cache
        file_put_contents($cacheFile, $isValid ? '1' : '0');

        return $isValid;
    }

    /**
     * Internal helper method to validate an image without caching.
     *
     * @param string $filePath Path to the image file
     * @param array $allowedMimeTypes Array of allowed MIME types
     * @param int $minWidth Minimum valid width in pixels
     * @param int $minHeight Minimum valid height in pixels
     * @param int $maxFileSize Maximum file size in bytes
     * @return bool True if the image is valid, false otherwise
     */
    private static function validateImageWithoutCache(
        string $filePath,
        array $allowedMimeTypes,
        int $minWidth,
        int $minHeight,
        int $maxFileSize
    ): bool {
        // Check if file exists and is readable
        if (!self::fileExists($filePath)) {
            return false;
        }

        // Check file size if specified
        if ($maxFileSize > 0 && filesize($filePath) > $maxFileSize) {
            return false;
        }

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return false;
        }

        $mimeType = finfo_file($finfo, $filePath);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedMimeTypes)) {
            return false;
        }

        // Special handling for SVG files
        if ($mimeType === 'image/svg+xml') {
            // Try to parse the SVG to check if it's valid
            $svgContent = @file_get_contents($filePath);
            if ($svgContent === false) {
                return false;
            }

            $isValidXml = @simplexml_load_string($svgContent) !== false;
            return $isValidXml;
        }

        // For raster images, use getimagesize() to validate the image data
        $imageInfo = @getimagesize($filePath);

        // Check if the image data is valid and meets dimension requirements
        if (
            $imageInfo === false ||
            !isset($imageInfo[0]) ||
            !isset($imageInfo[1]) ||
            $imageInfo[0] < $minWidth ||
            $imageInfo[1] < $minHeight
        ) {
            return false;
        }

        // Additional check for JPEG files - try to create a valid image resource
        if ($mimeType === 'image/jpeg') {
            $image = @imagecreatefromjpeg($filePath);
            if ($image === false) {
                return false;
            }
            imagedestroy($image);
        }

        // Additional check for PNG files
        if ($mimeType === 'image/png') {
            $image = @imagecreatefrompng($filePath);
            if ($image === false) {
                return false;
            }
            imagedestroy($image);
        }

        // Additional check for GIF files
        if ($mimeType === 'image/gif') {
            $image = @imagecreatefromgif($filePath);
            if ($image === false) {
                return false;
            }
            imagedestroy($image);
        }

        // Additional check for WebP files if supported
        if ($mimeType === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $image = @imagecreatefromwebp($filePath);
            if ($image === false) {
                return false;
            }
            imagedestroy($image);
        }

        return true;
    }

    /**
     * Clears the image validation cache
     *
     * @param int $maxAge Maximum age of cache files to keep in seconds (0 to clear all)
     * @param string $cacheDir Directory where cache files are stored
     * @return int Number of cache files removed
     */
    public static function clearImageValidationCache(
        int $maxAge = 0,
        string $cacheDir = CACHE_PATH . '/image_validation_cache'
    ): int {
        if (!is_dir($cacheDir)) {
            return 0;
        }

        $count = 0;
        $now = time();

        foreach (new \DirectoryIterator($cacheDir) as $fileInfo) {
            if ($fileInfo->isDot() || !$fileInfo->isFile() || !str_ends_with($fileInfo->getFilename(), '.cache')) {
                continue;
            }

            // If maxAge is 0, delete all cache files, otherwise check file age
            if ($maxAge <= 0 || ($now - $fileInfo->getMTime() > $maxAge)) {
                if (unlink($fileInfo->getPathname())) {
                    $count++;
                }
            }
        }

        return $count;
    }
    public static function getMimeType($file, $allowedMimeTypes)
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if ($finfo === false) {
            return false;
        }

        $mime = finfo_file($finfo, $file);
        finfo_close($finfo);

        return in_array($mime, $allowedMimeTypes) ? $mime : false;
    }

    public static function generateCsv($headings, $data, $filename, $delimiter = ',', $enclosure = '"')
    {
        $handle = fopen($filename, 'w'); // Open file for writing

        // Write the UTF-8 BOM
        fwrite($handle, "\xEF\xBB\xBF");

        // The headings first
        if (!empty($headings)) {
            fputcsv($handle, $headings, $delimiter, $enclosure);
        }
        // Then the data
        if (!empty($data)) {
            foreach ($data as $line) {
                fputcsv($handle, $line, $delimiter, $enclosure);
            }
        }

        //Clear Memory
        unset($data);
        fclose($handle);
        return $filename;
    }

    /**
     * Generate CSV file by streaming data directly to disk
     * Memory-efficient for large datasets
     * 
     * @param array $headings Column headers
     * @param iterable $dataGenerator Generator or iterator that yields rows
     * @param string $filename Full path to output file
     * @param string $delimiter CSV delimiter (default: ',')
     * @param string $enclosure CSV enclosure (default: '"')
     * @param callable|null $rowTransformer Optional callback to transform each row before writing
     * @return string The filename
     */
    public static function generateCsvStream(
        array $headings,
        iterable $dataGenerator,
        string $filename,
        string $delimiter = ',',
        string $enclosure = '"',
        ?callable $rowTransformer = null
    ): string {
        $handle = fopen($filename, 'w');

        if ($handle === false) {
            throw new \RuntimeException("Unable to open file: $filename");
        }

        // Write UTF-8 BOM
        fwrite($handle, "\xEF\xBB\xBF");

        // Write headings
        if (!empty($headings)) {
            fputcsv($handle, $headings, $delimiter, $enclosure);
        }

        // Stream data rows
        $rowCount = 0;
        foreach ($dataGenerator as $data) {
            // Transform row if callback provided
            $row = $rowTransformer ? $rowTransformer($data, $rowCount) : $data;

            fputcsv($handle, $row, $delimiter, $enclosure);
            $rowCount++;

            // Periodic garbage collection for very large datasets
            if ($rowCount % 1000 === 0) {
                gc_collect_cycles();
            }
        }

        fclose($handle);

        return $filename;
    }
    public static function getGenderFromString(?string $gender)
    {
        return match (strtolower($gender)) {
            'male', 'm' => _translate('Male'),
            'female', 'f' => _translate('Female'),
            default => _translate('Unreported')
        };
    }
    public static function excludeKeys(array $fullArray, array $unwantedKeys)
    {
        return array_diff_key($fullArray, array_flip($unwantedKeys));
    }

    // Updates entries in targetArray with values from sourceArray where keys exist in targetArray
    public static function updateMatchingKeysOnly(?array $targetArray, ?array $sourceArray)
    {

        if (empty($targetArray) || empty($sourceArray)) {
            return $targetArray;
        }
        return array_merge($targetArray, array_intersect_key($sourceArray, $targetArray));
    }


    // Helper function to convert file size string to bytes
    public static function convertToBytes(string $sizeString): int
    {
        return match (substr($sizeString, -1)) {
            'M', 'm' => (int)$sizeString * 1048576,
            'K', 'k' => (int)$sizeString * 1024,
            'G', 'g' => (int)$sizeString * 1073741824,
            default => (int)$sizeString,
        };
    }

    public static function getMimeTypeStrings(array $extensions): array
    {
        $mimeTypesMap = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'txt' => 'text/plain',
            'csv' => 'text/csv',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'html' => 'text/html',
            'xml' => 'application/xml',
            'json' => 'application/json'
        ];

        $mappedMimeTypes = [];
        foreach ($extensions as $ext) {
            $ext = strtolower($ext);
            if (isset($mimeTypesMap[$ext])) {
                $mappedMimeTypes[$ext] = $mimeTypesMap[$ext];
            } else {
                // If it's already a MIME type, just use it
                $mappedMimeTypes[$ext] = $ext;
            }
        }
        return $mappedMimeTypes;
    }

    public static function arrayToGenerator(array $array)
    {
        foreach ($array as $item) {
            yield $item;
        }
    }

    public static function removeMatchingElements(array $array, array $removeArray): array
    {
        return array_values(array_diff($array, $removeArray));
    }

    public static function arrayEmptyStringsToNull(?array $array, bool $convertEmptyJson = false): array
    {
        if (!$array) {
            return $array;
        }

        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = empty($value) ? null : self::arrayEmptyStringsToNull($value, $convertEmptyJson);
            } elseif ($value === '' || ($convertEmptyJson && in_array($value, ['{}', '[]'], true))) {
                $value = null;
            }
        }
        return $array;
    }


    // Generate a UUIDv4 with an optional extra string
    public static function generateUUID($attachExtraString = true): string
    {
        $uuid = Uuid::v4()->toRfc4122();
        if ($attachExtraString) {
            $uuid .= '-' . self::generateRandomString(6);
        }
        return $uuid;
    }


    /**
     * Generate a UUIDv5 based on a name and optional namespace.
     *
     * @param string $name The input string for UUID generation
     * @param string|null $namespace The namespace UUID (or null to use NAMESPACE_OID)
     * @return string The generated UUIDv5 as an RFC 4122 string
     */
    public static function generateUUIDv5(string $name, ?string $namespace = Uuid::NAMESPACE_OID): string
    {
        $namespaceUuid = is_string($namespace) ? Uuid::fromString($namespace) : $namespace;
        return Uuid::v5($namespaceUuid, $name)->toRfc4122();
    }


    // Generate a ULID
    public static function generateULID($attachExtraString = true): string
    {
        $ulid = (new Ulid())->toRfc4122();
        if ($attachExtraString) {
            $ulid .= '-' . self::generateRandomString(6);
        }
        return $ulid;
    }

    public static function generatePrefixedULID(string $prefix = '', int $length = 16): string
    {
        $ulid = self::generateULID(attachExtraString: false); // Full 26-char ULID

        // Truncate the ULID only if total length allows
        $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', $prefix));
        $remaining = max(0, $length - strlen($prefix));
        return $prefix . substr($ulid, 0, $remaining);
    }

    /**
     * String to a file inside a zip archive.
     *
     * @param string $stringData
     * @param string $fileName The FULL PATH of the file inside the zip archive.
     * @return bool Returns true on success, false on failure.
     */
    public static function dataToZippedFile(string $stringData, string $fileName): bool
    {
        if (empty($stringData) || empty($fileName)) {
            return false;
        }

        $zip = new ZipArchive();
        $zipPath = "$fileName.zip";

        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            $zip->addFromString(basename($fileName), $stringData);
            $result = $zip->status == ZipArchive::ER_OK;
            $zip->close();
            return $result;
        }

        return false;
    }

    /**
     * Unzips an archive and returns contents of a file inside it.
     *
     * @param string $zipFile The path to the zip file.
     * @param string $fileName The name of the JSON file inside the zip archive.
     * @return string
     */
    public static function getDataFromZippedFile(string $zipFile, string $fileName): string
    {
        if (!file_exists($zipFile)) {
            return "{}";
        }
        $zip = new ZipArchive;
        if ($zip->open($zipFile) === true) {
            $json = $zip->getFromName($fileName);
            $zip->close();

            return $json !== false ? $json : "{}";
        } else {
            return "{}";
        }
    }

    public static function getFileExtension($filename): string
    {
        if (empty($filename)) {
            return '';
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        return strtolower($extension);
    }


    public static function isFileType(string $filePath, string $expectedMimeType): bool
    {
        // Check if the file exists
        if (!file_exists($filePath)) {
            return false;
        }

        // Get the MIME type of the file
        $actualMimeType = mime_content_type($filePath);

        // Compare the actual MIME type with the expected MIME type
        return $actualMimeType === $expectedMimeType;
    }

    public static function progressBar($current, $total = null, $size = 30): void
    {
        static $startTime;

        // Initialize the timer on the first call
        if (!isset($startTime)) {
            $startTime = time();
        }

        // Calculate elapsed time
        $elapsed = time() - $startTime;

        if ($total !== null) {
            // Calculate progress percentage
            $progress = ($current / $total);
            $barLength = (int) floor($progress * $size);

            // Generate the progress bar
            $progressBar = str_repeat('=', $barLength) . str_repeat(' ', $size - $barLength);

            // Output the progress bar
            printf("\r[%s] %3d%% Complete (%d/%d) - %d sec elapsed", $progressBar, $progress * 100, $current, $total, $elapsed);
        } else {
            // Output the progress without percentage
            printf("\rProcessed %d items - %d sec elapsed", $current, $elapsed);
        }

        // Flush output for real-time updates
        fflush(STDOUT);

        // Print a newline and reset the timer when done
        if ($total !== null && $current === $total) {
            echo PHP_EOL;
            $startTime = null; // Reset timer for reuse
        }
    }

    public static function removeDuplicates($input)
    {
        // Check if the input is a string
        if (is_string($input)) {
            // Split the string into an array
            $inputArray = explode(',', $input);
        } elseif (is_array($input)) {
            // Use the input array directly
            $inputArray = $input;
        } else {
            // Invalid input type
            return $input;
        }

        // Remove duplicate values
        $uniqueArray = array_unique($inputArray);

        // Optionally, remove any empty values
        $uniqueArray = array_filter($uniqueArray);

        // Return the same type as the input
        if (is_string($input)) {
            // Convert the array back to a comma-separated string
            return implode(',', $uniqueArray);
        } else {
            // Return the unique array
            return $uniqueArray;
        }
    }

    public static function getMacAddress(): ?string
    {
        $commands = (strncasecmp(PHP_OS, 'WIN', 3) == 0)
            ? ['getmac']
            : ['ifconfig -a', 'ip addr show'];

        foreach ($commands as $command) {
            $output = [];
            @exec($command, $output);

            foreach ($output as $line) {
                if (preg_match('/([0-9A-F]{2}[:-]){5}([0-9A-F]{2})/i', $line, $matches)) {
                    return $matches[0]; // Return the MAC address as soon as it's found
                }
            }
        }

        return null; // Return null if no MAC address was found
    }


    public static function getLockFile(string $file): string
    {
        if (!str_starts_with($file, TEMP_PATH)) {
            $fileName = ltrim($file, DIRECTORY_SEPARATOR);

            // Normalize to use only single hyphens
            $fileName = preg_replace('/[\/\\\\]+/', '-', $fileName); // Convert slashes to hyphen
            $fileName = preg_replace('/-+/', '-', $fileName); // Collapse multiple hyphens
            $fileName = strtolower(trim($fileName, '-')); // Remove leading/trailing hyphens

            $file = rtrim(TEMP_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName . '.lock';
        }

        return $file;
    }


    public static function touchLockFile(string $file): void
    {
        touch(self::getLockFile($file));
    }

    public static function deleteLockFile(string $file): void
    {
        $lockFile = self::getLockFile($file);
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }

    public static function isLockFileExpired(string $file, int $maxAgeInSeconds = 900): bool
    {
        $lockFile = self::getLockFile($file);
        if (!file_exists($lockFile)) return true;

        return (time() - filemtime($lockFile)) > $maxAgeInSeconds;
    }

    public static function setupSignalHandler(string $file): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        // Make sure ticks is declared globally
        if (!ini_get('zend.enable_gc')) {
            echo "Note: Garbage collection should be enabled for better signal handling" . PHP_EOL;
        }

        pcntl_signal(SIGINT, function () use ($file) {
            echo PHP_EOL . "Interrupted. Cleaning up lock file..." . PHP_EOL;
            self::deleteLockFile($file);
            exit(130);
        });

        pcntl_signal(SIGTERM, function () use ($file) {
            echo PHP_EOL . "Terminated. Cleaning up lock file..." . PHP_EOL;
            self::deleteLockFile($file);
            exit(143);
        });

        // Add SIGQUIT handler too
        pcntl_signal(SIGQUIT, function () use ($file) {
            echo PHP_EOL . "Quit signal received. Cleaning up lock file..." . PHP_EOL;
            self::deleteLockFile($file);
            exit(131);
        });
    }

    /**
     * Checks if the given string is base64 encoded.
     *
     * @param string $data The string to check.
     * @return bool Returns true if $data is base64 encoded, false otherwise.
     */
    public static function isBase64(string $data): bool
    {
        // Ensure the length is a multiple of 4 by adding necessary padding
        $paddedData = str_pad($data, strlen($data) % 4 === 0 ? strlen($data) : strlen($data) + 4 - (strlen($data) % 4), '=');

        $decodedData = base64_decode($paddedData, true);

        // Check if decoding was successful and if re-encoding matches (ignoring padding)
        return $decodedData !== false && base64_encode($decodedData) === $paddedData;
    }

    /**
     * Safely constructs a file path by combining predefined and user-supplied components.
     * Recursively creates the folder structure if it doesn't exist.
     *
     * @param string $baseDirectory The predefined base directory.
     * @param array $pathComponents An array of path components, where some may be user-supplied.
     * @return string|bool Returns the constructed, sanitized path if valid, or false if the path is invalid.
     */
    public static function buildSafePath($baseDirectory, array $pathComponents)
    {
        if (!is_dir($baseDirectory) && !self::makeDirectory($baseDirectory)) {
            return false; // Failed to create the directory
        }

        // Normalize the base directory
        $baseDirectory = realpath($baseDirectory);

        // Clean and sanitize each component of the path
        $cleanComponents = [];
        foreach ($pathComponents as $component) {
            // Remove dangerous characters from user-supplied components
            $cleanComponent = preg_replace('/[^a-zA-Z0-9-_]/', '', $component);
            $cleanComponents[] = $cleanComponent;
        }

        // Join the base directory with the cleaned components to create the full path
        $fullPath = $baseDirectory . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $cleanComponents);

        // Check if the directory exists, if not, create it recursively
        if (!is_dir($fullPath) && !self::makeDirectory($fullPath)) {
            return false; // Failed to create the directory
        }

        return realpath($fullPath); // Clean and validated path
    }

    /**
     * Cleans up the input file name, removing any unsafe characters and returning the base file name with its extension.
     *
     * @param string $filePath The input file name or full path.
     * @return string The cleaned base file name with its extension.
     */
    public static function cleanFileName($filePath)
    {
        // Extract the base file name (removes the path if provided)
        $baseFileName = basename($filePath);

        // Separate the file name from its extension
        $extension = strtolower(pathinfo($baseFileName, PATHINFO_EXTENSION));
        $fileNameWithoutExtension = pathinfo($baseFileName, PATHINFO_FILENAME);

        // Clean the file name, keeping only alphanumeric characters, dashes, and underscores
        $cleanFileName = preg_replace('/[^a-zA-Z0-9-_]/', '', $fileNameWithoutExtension);

        // Reconstruct the file name with its extension
        return $cleanFileName . ($extension ? '.' . $extension : '');
    }


    // Functions for metadata management
    public static function loadMetadata($metadataPath)
    {
        if (file_exists($metadataPath)) {
            return json_decode(file_get_contents($metadataPath), true);
        }
        return [];
    }

    public static function saveMetadata($metadataPath, $newData)
    {
        self::makeDirectory(dirname($metadataPath));
        $existingData = self::loadMetadata($metadataPath);
        $mergedData = array_merge($existingData ?? [], $newData ?? []);
        file_put_contents($metadataPath, json_encode($mergedData, JSON_PRETTY_PRINT));
    }

    public static function readCSVFile($filename)
    {
        if (($handle = fopen($filename, 'r')) !== false) {
            $headers = fgetcsv($handle); // Read the header row
            while (($row = fgetcsv($handle)) !== false) {
                yield array_combine($headers, $row);
            }
            fclose($handle);
        }
    }

    public static function redirect(string $url): void
    {
        if (str_contains(strtolower($url), 'location:')) {
            header($url);
        } else {
            header("Location: $url");
        }
        exit;
    }


    /**
     * Recursively convert input to valid UTF-8 and remove invisible characters.
     *
     * @param array|string|null $input
     * @return array|string|null
     */
    /**
     * Recursively convert input to valid UTF-8 and (optionally) remove invisible characters.
     *
     * @param array|string|object|int|float|bool|null $input
     * @param bool $trim                Trim leading/trailing whitespace for strings
     * @param bool $stripInvisible      Remove zero-width, NBSP, and control chars (except \t\r\n)
     */
    public static function toUtf8(
        array|string|object|int|float|bool|null $input,
        bool $trim = false,
        bool $stripInvisible = true
    ): array|string|object|int|float|bool|null {
        if (is_array($input)) {
            foreach ($input as $k => $v) {
                $input[$k] = self::toUtf8($v, $trim, $stripInvisible);
            }
            return $input;
        }

        if (is_object($input)) {
            foreach (get_object_vars($input) as $prop => $val) {
                $input->{$prop} = self::toUtf8($val, $trim, $stripInvisible);
            }
            return $input;
        }

        if (!is_string($input)) {
            return $input;
        }

        $s = $trim ? trim($input) : $input;

        if (function_exists('mb_check_encoding') && !mb_check_encoding($s, 'UTF-8')) {
            $order = function_exists('mb_detect_order') ? (mb_detect_order() ?: ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'UTF-16']) : ['UTF-8', 'Windows-1252', 'ISO-8859-1', 'UTF-16'];
            $enc = function_exists('mb_detect_encoding') ? mb_detect_encoding($s, $order, true) : false;
            if ($enc === false) {
                foreach (['UTF-16', 'Windows-1252', 'ISO-8859-1', 'UTF-8'] as $fallback) {
                    if (mb_check_encoding($s, $fallback)) {
                        $enc = $fallback;
                        break;
                    }
                }
                $enc = $enc ?: 'UTF-8';
            }
            $s = mb_convert_encoding($s, 'UTF-8', $enc);
        }

        // Strip raw UTF-8 BOM bytes
        if (strncmp($s, "\xEF\xBB\xBF", 3) === 0) {
            $s = substr($s, 3);
        }

        // Normalize Unicode NFC if intl is available
        if (class_exists('Normalizer')) {
            $norm = Normalizer::normalize($s, Normalizer::FORM_C);
            if ($norm !== false) {
                $s = $norm;
            }
        }

        if ($stripInvisible) {
            // Remove zero-width & NBSP
            $clean = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00A0}]/u', '', $s);
            if ($clean !== null) {
                $s = $clean;
            }

            // Remove other control chars except TAB/CR/LF
            $cleanCtl = preg_replace('/[^\P{C}\t\r\n]/u', '', $s);
            if ($cleanCtl !== null) {
                $s = $cleanCtl;
            }
        }

        return $s;
    }




    public static function cleanString($string)
    {
        if ($string === null || $string === '') {
            return null;
        }

        if (class_exists('Normalizer')) {
            $string = Normalizer::normalize($string, Normalizer::FORM_C);
        }

        // Remove common invisible Unicode characters
        $string = preg_replace('/[\x{00A0}\x{200B}\x{FEFF}\x{202F}\x{2060}\x{00AD}]/u', '', $string);

        // Remove ASCII control characters (0–31 and 127)
        $string = preg_replace('/[\x00-\x1F\x7F]/u', '', $string);

        // Trim Unicode whitespace and control characters from both ends
        $string = preg_replace('/^[\p{Z}\p{C}]+|[\p{Z}\p{C}]+$/u', '', $string);

        return $string;
    }

    public static function getFullImagePath(string $imageName, string $basePath): ?string
    {
        if (empty($imageName)) {
            return null;
        }

        // Normalize slashes for comparison
        $normalizedImageName = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $imageName);
        $normalizedBasePath = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $basePath), DIRECTORY_SEPARATOR);

        if (str_starts_with($normalizedImageName, $normalizedBasePath)) {
            return $normalizedImageName;
        }

        return $normalizedBasePath . DIRECTORY_SEPARATOR . $imageName;
    }

    public static function isArrayEqual(array $a, array $b, array $excludeKeys = []): bool
    {
        foreach ($excludeKeys as $key) {
            unset($a[$key], $b[$key]);
        }

        // Determine which array has fewer keys — treat it as reference
        if (count($a) <= count($b)) {
            $reference = $a;
            $other = $b;
        } else {
            $reference = $b;
            $other = $a;
        }

        // Trim the other array to only contain keys from the reference
        $otherSubset = array_intersect_key($other, $reference);

        return $reference == $otherSubset;
    }


    public static function generateErrorId($prefix = 'ERR'): string
    {
        return $prefix . '-' . date('Ymd-His') . '-' . substr(uniqid(), -6);
    }

    /** Lazily-created global console output */
    public static function console(): ConsoleOutput
    {
        static $out = null;
        if (!$out) $out = new ConsoleOutput();
        return $out;
    }

    /**
     * Start a reusable spinner/progress bar.
     * - If $maxSteps is null → indeterminate spinner
     * - If $maxSteps is int   → determinate progress
     */
    public static function spinnerStart(
        ?int $maxSteps = null,
        string $message = 'Working…',
        string $barChar = '█',
        string $emptyChar = '░',
        string $progressChar = '█',
        string $color = 'cyan'
    ): ProgressBar {
        $out = self::console();
        $bar = $maxSteps === null ? new ProgressBar($out) : new ProgressBar($out, $maxSteps);

        $bar->setFormat("%message%\n <fg={$color}>[%bar%]</> %percent:3s%%  %current%/%max%  %elapsed:6s%  %memory:6s%");
        $bar->setBarCharacter($barChar);
        $bar->setEmptyBarCharacter($emptyChar);
        $bar->setProgressCharacter($progressChar);

        if (method_exists($bar, 'setRedrawFrequency')) $bar->setRedrawFrequency(1);
        if (method_exists($bar, 'minSecondsBetweenRedraws')) {
            $bar->minSecondsBetweenRedraws(0.0);
            $bar->maxSecondsBetweenRedraws(0.25);
        }
        if (method_exists($bar, 'setOverwrite')) $bar->setOverwrite(true);

        $bar->setMessage($message);
        $bar->start();
        $bar->display();
        return $bar;
    }



    /** Update the message (table/column, etc.) without moving progress */
    public static function spinnerUpdate(ProgressBar $bar, string $table, ?string $column = null, ?int $idx = null, ?int $total = null): void
    {
        $t = strlen($table) > 40 ? substr($table, 0, 37) . '…' : $table;
        if ($column !== null) {
            $c = strlen($column) > 36 ? substr($column, 0, 33) . '…' : $column;
            $suffix = ($idx !== null && $total !== null) ? " ($idx/$total)" : "";
            $bar->setMessage("Converting {$t}  →  {$c}{$suffix}");
        } else {
            $bar->setMessage("Converting {$t} …");
        }
        $bar->display(); // <-- force repaint now
    }


    /** Advance the bar by a number of steps (default 1) */
    public static function spinnerAdvance(ProgressBar $bar, int $steps = 1): void
    {
        $bar->advance($steps);
    }

    /**
     * Temporarily clear the bar, run a printer (echo/log), then restore the bar.
     * Use this for verbose lines so they don’t fight with the bar.
     */
    public static function spinnerPausePrint(ProgressBar $bar, callable $printer): void
    {
        $bar->clear();
        $printer();        // e.g., echo "message\n";
        $bar->display();   // restore bar to the console
    }

    /** Finish the bar and print a trailing newline */
    public static function spinnerFinish(ProgressBar $bar): void
    {
        $bar->finish();
        self::console()->writeln(""); // newline after the bar
    }
}
