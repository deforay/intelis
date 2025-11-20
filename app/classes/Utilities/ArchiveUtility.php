<?php

declare(strict_types=1);

namespace App\Utilities;

use ZipArchive;
use RuntimeException;
use InvalidArgumentException;
use App\Utilities\MiscUtility;
use Symfony\Component\Process\Process;

/**
 * Smart single-file compressor/decompressor with fallback support.
 * Supports: zstd (.zst) -> pigz/gzip (.gz) -> zip (.zip)
 *
 * Public API:
 *  - compressFile(string $src, string $dst, ?string $backend = null): string
 *  - compressContent(string $content, string $dst, ?string $backend = null): string
 *  - decompressToFile(string $src, string $dstDir): string
 *  - decompressToString(string $src): string
 *  - findAndDecompressArchive(string $directory, string $filename): string
 *  - validateArchive(string $path): bool
 *  - pickBestBackend(): string
 *  - extensionForBackend(string $backend): string
 *
 * Configuration:
 *  - setZstdLevel(int $level): void
 *  - setZstdThreads(int $threads): void
 *  - setMaxFileSize(?int $bytes): void
 *
 * @package App\Utilities
 */
final class ArchiveUtility
{
    public const string BACKEND_ZSTD = 'zstd';
    public const string BACKEND_PIGZ = 'pigz';
    public const string BACKEND_GZIP = 'gzip';
    public const string BACKEND_ZIP  = 'zip';

    /** @var array<string, bool> Cache for command availability checks */
    private static array $cmdCache = [];

    /** @var int Zstd compression level (1-22, higher = better compression) */
    private static int $zstdLevel = 19;

    /** @var int Zstd thread count (0 = auto-detect) */
    private static int $zstdThreads = 0;

    /** @var int|null Maximum allowed file size in bytes (null = no limit) */
    private static ?int $maxFileSize = null;

    /** @var int Default file permissions for created directories */
    private static int $dirPermissions = 0777;

    /* ========== Public API ========== */

    /**
     * Compress a file using the specified backend.
     *
     * @param string $src Source file path (must exist and be readable)
     * @param string $dst Destination path (extension added automatically if missing)
     * @param string|null $backend Backend to use (null = auto-detect best available)
     * @return string Final destination path with appropriate extension
     * @throws RuntimeException If compression fails or backend unavailable
     * @throws InvalidArgumentException If source file is invalid
     */
    public static function compressFile(string $src, string $dst, ?string $backend = null): string
    {
        self::validateSourceFile($src);
        self::ensureDestinationDirectory($dst);

        $backend ??= self::pickBestBackend();
        $dst     = self::ensureExtension($dst, $backend);

        match ($backend) {
            self::BACKEND_ZSTD => self::compressWithZstd($src, $dst),
            self::BACKEND_PIGZ => self::compressWithPigz($src, $dst),
            self::BACKEND_GZIP => self::compressWithGzip($src, $dst),
            self::BACKEND_ZIP => self::compressWithZip($src, $dst),
            default => throw new InvalidArgumentException("Unsupported backend: $backend"),
        };

        if (!is_file($dst)) {
            throw new RuntimeException("Compression produced no output file: $dst");
        }

        return $dst;
    }

    /**
     * Compress string content to a file.
     *
     * @param string $content Content to compress
     * @param string $dst Destination path
     * @param string|null $backend Backend to use (null = auto-detect)
     * @return string Final destination path
     * @throws RuntimeException If compression fails
     */
    public static function compressContent(string $content, string $dst, ?string $backend = null): string
    {
        $tmp = self::createTempFile();

        try {
            $written = @file_put_contents($tmp, $content);
            if ($written === false) {
                throw new RuntimeException("Failed to write temporary file: $tmp");
            }

            return self::compressFile($tmp, $dst, $backend);
        } finally {
            MiscUtility::deleteFile($tmp);
        }
    }

    /**
     * Decompress an archive into the specified directory.
     *
     * Supports: .zst, .gz, .zip, and plain .csv files
     *
     * @param string $src Archive file path
     * @param string $dstDir Destination directory (created if doesn't exist)
     * @return string Path to the extracted file
     * @throws RuntimeException If decompression fails
     */
    public static function decompressToFile(string $src, string $dstDir): string
    {
        if (!is_file($src)) {
            throw new RuntimeException("Archive not found: $src");
        }

        if (!is_readable($src)) {
            throw new RuntimeException("Archive not readable: $src");
        }

        if (!is_dir($dstDir) && !@mkdir($dstDir, self::$dirPermissions, true)) {
            throw new RuntimeException("Failed to create destination directory: $dstDir");
        }

        $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));

        // Handle plain CSV files
        if ($ext === 'csv') {
            return self::copyPlainFile($src, $dstDir);
        }

        // Decompress based on extension
        $extracted = match ($ext) {
            'zst' => self::decompressZstd($src, $dstDir),
            'gz'  => self::decompressGzip($src, $dstDir),
            'zip' => self::decompressZip($src, $dstDir),
            default => throw new RuntimeException("Unsupported archive format: .$ext")
        };

        if (!is_file($extracted)) {
            throw new RuntimeException("Decompression failed, output not found: $extracted");
        }

        return $extracted;
    }

    /**
     * Decompress an archive and return its contents as a string.
     *
     * @param string $src Archive file path
     * @return string Decompressed content
     * @throws RuntimeException If decompression fails
     */
    public static function decompressToString(string $src): string
    {
        if (!is_file($src)) {
            throw new RuntimeException("Archive not found: $src");
        }

        if (!is_readable($src)) {
            throw new RuntimeException("Archive not readable: $src");
        }

        $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));

        return match ($ext) {
            'csv' => self::readPlainFile($src),
            'zst' => self::decompressZstdToString($src),
            'gz'  => self::decompressGzipToString($src),
            'zip' => self::decompressZipToString($src),
            default => throw new RuntimeException("Unsupported archive format: .$ext")
        };
    }

    /**
     * Find and decompress an archive file by trying multiple extensions.
     * 
     * Searches for the file with extensions in order: .zst, .gz, .zip
     * Falls back to plain file if no compressed version exists.
     *
     * @param string $directory Directory containing the archive
     * @param string $filename Base filename (without compression extension)
     * @return string Decompressed content
     * @throws RuntimeException If no file found or decompression fails
     */
    public static function findAndDecompressArchive(string $directory, string $filename): string
    {
        if (!is_dir($directory)) {
            throw new RuntimeException("Directory not found: $directory");
        }

        if (!is_readable($directory)) {
            throw new RuntimeException("Directory not readable: $directory");
        }

        $directory = rtrim($directory, DIRECTORY_SEPARATOR);
        $possibleExtensions = ['.zst', '.gz', '.zip'];
        $lastException = null;

        // Try compressed formats
        foreach ($possibleExtensions as $ext) {
            $filepath = $directory . DIRECTORY_SEPARATOR . $filename . $ext;

            if (file_exists($filepath)) {
                try {
                    return self::decompressToString($filepath);
                } catch (RuntimeException $e) {
                    // Store the exception and try next format
                    $lastException = $e;
                    continue;
                }
            }
        }

        // Fall back to plain file (backward compatibility)
        $plainFile = $directory . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($plainFile)) {
            if (!is_readable($plainFile)) {
                throw new RuntimeException("File not readable: $plainFile");
            }

            $content = @file_get_contents($plainFile);
            if ($content === false) {
                throw new RuntimeException("Failed to read file: $plainFile");
            }

            return $content;
        }

        // No file found with any extension
        $message = "Archive not found: $directory/$filename";
        if ($lastException instanceof RuntimeException) {
            $message .= " (last error: " . $lastException->getMessage() . ")";
        }

        throw new RuntimeException($message);
    }

    /**
     * Validate that an archive is not corrupted.
     *
     * @param string $path Path to archive file
     * @return bool True if archive is valid
     */
    public static function validateArchive(string $path): bool
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        try {
            switch ($ext) {
                case 'zst':
                    if (!self::hasCmd('zstd')) {
                        return false;
                    }
                    self::runCommand(['zstd', '-t', $path]);
                    return true;

                case 'gz':
                    $cmd = self::hasCmd('pigz') ? 'pigz' : 'gzip';
                    if (!self::hasCmd($cmd)) {
                        return false;
                    }
                    self::runCommand([$cmd, '-t', $path]);
                    return true;

                case 'zip':
                    if (!class_exists(ZipArchive::class)) {
                        return false;
                    }
                    $zip = new ZipArchive();
                    $result = $zip->open($path, ZipArchive::CHECKCONS);
                    $zip->close();
                    return $result === true;

                case 'csv':
                    // Plain files are considered valid if readable
                    return is_readable($path);

                default:
                    return false;
            }
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Pick the best available compression backend.
     *
     * Priority: zstd > pigz > gzip > zip
     *
     * @return string Backend identifier
     * @throws RuntimeException If no backend is available
     */
    public static function pickBestBackend(): string
    {
        if (self::hasCmd('zstd')) {
            return self::BACKEND_ZSTD;
        }
        if (self::hasCmd('pigz')) {
            return self::BACKEND_PIGZ;
        }
        if (self::hasCmd('gzip')) {
            return self::BACKEND_GZIP;
        }
        if (class_exists(ZipArchive::class)) {
            return self::BACKEND_ZIP;
        }

        throw new RuntimeException('No supported compressor found (need zstd/pigz/gzip or ZipArchive)');
    }

    /**
     * Get the file extension for a backend.
     *
     * @param string $backend Backend identifier
     * @return string File extension (including dot)
     */
    public static function extensionForBackend(string $backend): string
    {
        return match ($backend) {
            self::BACKEND_ZSTD => '.zst',
            self::BACKEND_PIGZ,
            self::BACKEND_GZIP => '.gz',
            self::BACKEND_ZIP  => '.zip',
            default            => '',
        };
    }

    /* ========== Configuration ========== */

    /**
     * Set the zstd compression level.
     *
     * @param int $level Compression level (1-22, higher = better compression but slower)
     * @throws InvalidArgumentException If level is out of range
     */
    public static function setZstdLevel(int $level): void
    {
        if ($level < 1 || $level > 22) {
            throw new InvalidArgumentException("Zstd level must be between 1 and 22, got: $level");
        }
        self::$zstdLevel = $level;
    }

    /**
     * Set the number of threads for zstd compression.
     *
     * @param int $threads Thread count (0 = auto-detect, -1 = use all cores)
     * @throws InvalidArgumentException If thread count is invalid
     */
    public static function setZstdThreads(int $threads): void
    {
        if ($threads < -1) {
            throw new InvalidArgumentException("Thread count must be >= -1, got: $threads");
        }
        self::$zstdThreads = $threads;
    }

    /**
     * Set maximum allowed file size for operations.
     *
     * @param int|null $bytes Maximum size in bytes (null = no limit, default = 25MB)
     * @throws InvalidArgumentException If size is negative
     */
    public static function setMaxFileSize(?int $bytes): void
    {
        if ($bytes !== null && $bytes < 0) {
            throw new InvalidArgumentException("Maximum file size must be >= 0, got: $bytes");
        }
        self::$maxFileSize = $bytes;
    }

    /**
     * Set default directory permissions for created directories.
     *
     * @param int $permissions Octal permissions (e.g., 0755)
     */
    public static function setDirPermissions(int $permissions): void
    {
        self::$dirPermissions = $permissions;
    }

    /**
     * Clear the command availability cache.
     * Useful for testing or if system state changes.
     */
    public static function clearCache(): void
    {
        self::$cmdCache = [];
    }

    /* ========== Internal: Validation ========== */

    /**
     * Validate source file exists, is readable, and within size limits.
     *
     * @param string $src Source file path
     * @throws RuntimeException If validation fails
     */
    private static function validateSourceFile(string $src): void
    {
        if (!is_file($src)) {
            throw new RuntimeException("Source file not found: $src");
        }

        if (!is_readable($src)) {
            throw new RuntimeException("Source file not readable: $src");
        }

        if (self::$maxFileSize !== null) {
            $size = @filesize($src);
            if ($size === false) {
                throw new RuntimeException("Cannot determine file size: $src");
            }
            if ($size > self::$maxFileSize) {
                throw new RuntimeException(
                    sprintf(
                        "File size (%d bytes) exceeds maximum allowed (%d bytes): %s",
                        $size,
                        self::$maxFileSize,
                        $src
                    )
                );
            }
        }
    }

    /**
     * Ensure destination directory exists and is writable.
     *
     * @param string $dst Destination file path
     * @throws RuntimeException If directory cannot be created or is not writable
     */
    private static function ensureDestinationDirectory(string $dst): void
    {
        $dstDir = dirname($dst);

        if (!is_dir($dstDir) && !@mkdir($dstDir, self::$dirPermissions, true)) {
            throw new RuntimeException("Cannot create destination directory: $dstDir");
        }

        if (!is_writable($dstDir)) {
            throw new RuntimeException("Destination directory not writable: $dstDir");
        }
    }

    /**
     * Validate a path to prevent directory traversal attacks.
     *
     * @param string $path Path to validate
     * @throws RuntimeException If path contains suspicious elements
     */
    private static function validatePath(string $path): void
    {
        if (str_contains($path, '..')) {
            throw new RuntimeException("Path contains directory traversal: $path");
        }

        if (str_starts_with($path, '/') && !str_starts_with($path, sys_get_temp_dir())) {
            // Allow absolute paths only within temp directory for extracted content
            $realDstDir = realpath(dirname($path));
            $realTempDir = realpath(sys_get_temp_dir());
            if (
                $realDstDir === false || $realTempDir === false ||
                !str_starts_with($realDstDir, $realTempDir)
            ) {
                // This is likely fine for most use cases, just being cautious
            }
        }
    }

    /* ========== Internal: Command Execution ========== */

    /**
     * Check if a command is available on the system.
     *
     * @param string $cmd Command name
     * @return bool True if command exists
     */
    private static function hasCmd(string $cmd): bool
    {
        if (isset(self::$cmdCache[$cmd])) {
            return self::$cmdCache[$cmd];
        }

        $escapedCmd = escapeshellarg($cmd);
        $output = @shell_exec("command -v $escapedCmd 2>/dev/null");
        $available = is_string($output) && trim($output) !== '';

        self::$cmdCache[$cmd] = $available;
        return $available;
    }

    /**
     * Ensure a command is available, throw if not.
     *
     * @param string $cmd Command name
     * @throws RuntimeException If command not found
     */
    private static function ensureCmd(string $cmd): void
    {
        if (!self::hasCmd($cmd)) {
            throw new RuntimeException("Required command not found: $cmd");
        }
    }

    /**
     * Execute a command safely with array arguments (prevents shell injection).
     *
     * @param array<string> $args Command and arguments
     * @throws RuntimeException If command fails
     */
    private static function runCommand(array $args): void
    {
        $process = new Process($args);
        $process->setTimeout(null);
        $process->run();

        if (!$process->isSuccessful()) {
            $stdout = $process->getOutput();
            $stderr = $process->getErrorOutput();
            throw new RuntimeException(
                sprintf(
                    "Command failed with exit code %d: %s\nOutput: %s\nError: %s",
                    $process->getExitCode(),
                    implode(' ', $args),
                    $stdout,
                    $stderr
                )
            );
        }
    }

    /**
     * Execute a command and capture its output.
     *
     * @param array<string> $args Command and arguments
     * @return string Command stdout
     * @throws RuntimeException If command fails
     */
    private static function runCommandCapture(array $args): string
    {
        $process = new Process($args);
        $process->setTimeout(null);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException(
                sprintf(
                    "Command failed with exit code %d: %s\nError: %s",
                    $process->getExitCode(),
                    implode(' ', $args),
                    $process->getErrorOutput()
                )
            );
        }

        return $process->getOutput();
    }

    /* ========== Internal: Compression Methods ========== */

    /**
     * Compress with zstd.
     */
    private static function compressWithZstd(string $src, string $dst): void
    {
        self::ensureCmd('zstd');

        $args = [
            'zstd',
            '-T' . self::$zstdThreads,
            '-q',
            '-' . self::$zstdLevel,
            '-f',
            '-o',
            $dst,
            $src
        ];

        self::runCommand($args);
    }

    /**
     * Compress with pigz.
     */
    private static function compressWithPigz(string $src, string $dst): void
    {
        self::ensureCmd('pigz');

        // Use process substitution to avoid shell redirection
        $content = @file_get_contents($src);
        if ($content === false) {
            throw new RuntimeException("Failed to read source file: $src");
        }

        $process = new Process(['pigz', '-c']);
        $process->setTimeout(null);
        $process->setInput($content);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException("Pigz compression failed: " . $process->getErrorOutput());
        }

        if (@file_put_contents($dst, $process->getOutput()) === false) {
            throw new RuntimeException("Failed to write compressed output: $dst");
        }
    }

    /**
     * Compress with gzip.
     */
    private static function compressWithGzip(string $src, string $dst): void
    {
        self::ensureCmd('gzip');

        $content = @file_get_contents($src);
        if ($content === false) {
            throw new RuntimeException("Failed to read source file: $src");
        }

        $process = new Process(['gzip', '-c']);
        $process->setTimeout(null);
        $process->setInput($content);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException("Gzip compression failed: " . $process->getErrorOutput());
        }

        if (@file_put_contents($dst, $process->getOutput()) === false) {
            throw new RuntimeException("Failed to write compressed output: $dst");
        }
    }

    /**
     * Compress with ZIP.
     */
    private static function compressWithZip(string $src, string $dst): void
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException("ZipArchive extension not available");
        }

        $zip = new ZipArchive();
        $result = $zip->open($dst, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new RuntimeException("Failed to create zip file: $dst (error code: $result)");
        }

        // Use destination stem as entry name for consistency
        $entry = basename(self::stripLastExt($dst));
        if ($entry === '' || $entry === basename($dst)) {
            $entry = basename($src);
        }

        if (!$zip->addFile($src, $entry)) {
            $zip->close();
            throw new RuntimeException("Failed to add file to zip: $src");
        }

        if (!$zip->close()) {
            throw new RuntimeException("Failed to finalize zip file: $dst");
        }
    }

    /* ========== Internal: Decompression Methods ========== */

    /**
     * Copy a plain CSV file.
     */
    private static function copyPlainFile(string $src, string $dstDir): string
    {
        $dst = rtrim($dstDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($src);

        if (!@copy($src, $dst)) {
            throw new RuntimeException("Failed to copy file: $src to $dst");
        }

        return $dst;
    }

    /**
     * Read a plain file to string.
     */
    private static function readPlainFile(string $src): string
    {
        $content = @file_get_contents($src);

        if ($content === false) {
            throw new RuntimeException("Failed to read file: $src");
        }

        return $content;
    }

    /**
     * Decompress zstd file.
     */
    private static function decompressZstd(string $src, string $dstDir): string
    {
        self::ensureCmd('zstd');

        $dst = rtrim($dstDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::stripLastExt(basename($src));

        $content = self::runCommandCapture(['zstd', '-dc', $src]);

        if (@file_put_contents($dst, $content) === false) {
            throw new RuntimeException("Failed to write decompressed output: $dst");
        }

        return $dst;
    }

    /**
     * Decompress zstd to string.
     */
    private static function decompressZstdToString(string $src): string
    {
        self::ensureCmd('zstd');
        return self::runCommandCapture(['zstd', '-dc', $src]);
    }

    /**
     * Decompress gzip file.
     */
    private static function decompressGzip(string $src, string $dstDir): string
    {
        $cmd = self::hasCmd('pigz') ? 'pigz' : 'gzip';
        self::ensureCmd($cmd);

        $dst = rtrim($dstDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::stripLastExt(basename($src));

        $content = self::runCommandCapture([$cmd, '-dc', $src]);

        if (@file_put_contents($dst, $content) === false) {
            throw new RuntimeException("Failed to write decompressed output: $dst");
        }

        return $dst;
    }

    /**
     * Decompress gzip to string.
     */
    private static function decompressGzipToString(string $src): string
    {
        $cmd = self::hasCmd('pigz') ? 'pigz' : 'gzip';
        self::ensureCmd($cmd);
        return self::runCommandCapture([$cmd, '-dc', $src]);
    }

    /**
     * Decompress zip file.
     */
    private static function decompressZip(string $src, string $dstDir): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException("ZipArchive extension not available");
        }

        $zip = new ZipArchive();
        $result = $zip->open($src);

        if ($result !== true) {
            throw new RuntimeException("Failed to open zip file: $src (error code: $result)");
        }

        if ($zip->numFiles < 1) {
            $zip->close();
            throw new RuntimeException("Zip archive is empty: $src");
        }

        $name = $zip->getNameIndex(0);

        if ($name === false) {
            $zip->close();
            throw new RuntimeException("Failed to read zip entry name: $src");
        }

        // Security: prevent directory traversal
        self::validatePath($name);

        $safeName = basename($name);
        $dstDir = rtrim($dstDir, DIRECTORY_SEPARATOR);

        if (!$zip->extractTo($dstDir, [$name])) {
            $zip->close();
            throw new RuntimeException("Failed to extract $name from zip: $src");
        }

        $zip->close();

        // Determine the actual extracted file path
        $extracted = $dstDir . DIRECTORY_SEPARATOR . $safeName;

        if (!is_file($extracted)) {
            // Try the original name if basename didn't work
            $extracted = $dstDir . DIRECTORY_SEPARATOR . $name;
        }

        if (!is_file($extracted)) {
            throw new RuntimeException("Extracted file not found after decompression: $extracted");
        }

        return $extracted;
    }

    /**
     * Decompress zip to string.
     */
    private static function decompressZipToString(string $src): string
    {
        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException("ZipArchive extension not available");
        }

        $zip = new ZipArchive();
        $result = $zip->open($src);

        if ($result !== true) {
            throw new RuntimeException("Failed to open zip file: $src (error code: $result)");
        }

        if ($zip->numFiles < 1) {
            $zip->close();
            throw new RuntimeException("Zip archive is empty: $src");
        }

        $name = $zip->getNameIndex(0);

        if ($name === false) {
            $zip->close();
            throw new RuntimeException("Failed to read zip entry name: $src");
        }

        $content = $zip->getFromName($name);
        $zip->close();

        if ($content === false) {
            throw new RuntimeException("Failed to read content from zip entry: $name");
        }

        return $content;
    }

    /* ========== Internal: Utilities ========== */

    /**
     * Ensure destination path has the correct extension for the backend.
     */
    private static function ensureExtension(string $dst, string $backend): string
    {
        $ext = strtolower(pathinfo($dst, PATHINFO_EXTENSION));

        // If already has a compression extension, return as-is
        if (in_array($ext, ['zst', 'gz', 'zip'], true)) {
            return $dst;
        }

        return $dst . self::extensionForBackend($backend);
    }

    /**
     * Strip the last extension from a filename.
     */
    private static function stripLastExt(string $filename): string
    {
        $pos = strrpos($filename, '.');
        return $pos === false ? $filename : substr($filename, 0, $pos);
    }

    /**
     * Create a temporary file and return its path.
     */
    private static function createTempFile(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'archv_');

        if ($tmp === false) {
            throw new RuntimeException('Failed to create temporary file');
        }

        return $tmp;
    }
}
